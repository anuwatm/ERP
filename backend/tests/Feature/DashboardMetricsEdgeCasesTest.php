<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardMetricsEdgeCasesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_empty_organization_dashboards_render_safely_without_errors(): void
    {
        $user = User::factory()->create();
        $this->attachAllDashboardPermissions($user);

        $routes = [
            route('dashboard'),
            route('executive.dashboard'),
            route('finance.dashboard'),
            route('sales.dashboard'),
            route('procurement.dashboard'),
            route('delivery.dashboard'),
            route('hr.dashboard'),
            route('my-work.dashboard'),
        ];

        foreach ($routes as $url) {
            $this->actingAsOrgUser($user)->get($url)->assertOk();
        }

        // Specifically test finance metrics with zero data
        $this->actingAsOrgUser($user)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardWidgets.finance.revenue.invoiced_revenue', 0)
                ->where('dashboardWidgets.finance.ar.outstanding_ar', 0)
                ->where('dashboardWidgets.finance.cash_in.receipts', 0)
                ->where('dashboardWidgets.finance.cash_in.reversals', 0)
                ->where('dashboardWidgets.finance.expense.recognized_expense', 0)
                ->where('financeSummary.invoiced_revenue', 0)
                ->where('financeSummary.cash_in', 0)
                ->where('financeSummary.outstanding_ar', 0)
                ->where('financeSummary.recognized_expense', 0)
            );
    }

    public function test_date_boundaries_strictly_filter_month_metrics(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 15)->startOfDay());

        $user = User::factory()->create();
        $this->attachAllDashboardPermissions($user);
        $customer = $this->createCustomer($user, 'CUST-BOUNDARY');

        // July 31 (Outside August)
        $invJuly = $this->createInvoice($user, $customer, 'INV-JUL31', '1000.00', '1000.00', '2026-07-31');
        $this->createPayment($user, $invJuly, 'receipt', '1000.00', '2026-07-31');
        $this->createExpense($user, 'EXP-JUL31', '300.00', 'paid', '2026-07-31');

        // August 1 (First day of August - Inside)
        $invAugStart = $this->createInvoice($user, $customer, 'INV-AUG01', '2000.00', '2000.00', '2026-08-01');
        $this->createPayment($user, $invAugStart, 'receipt', '500.00', '2026-08-01');
        $this->createExpense($user, 'EXP-AUG01', '400.00', 'approved', '2026-08-01');

        // August 31 (Last day of August - Inside)
        $invAugEnd = $this->createInvoice($user, $customer, 'INV-AUG31', '3000.00', '3000.00', '2026-08-31');
        $this->createPayment($user, $invAugEnd, 'receipt', '700.00', '2026-08-31');
        $this->createExpense($user, 'EXP-AUG31', '600.00', 'paid', '2026-08-31');

        // September 1 (Outside August)
        $invSep = $this->createInvoice($user, $customer, 'INV-SEP01', '4000.00', '4000.00', '2026-09-01');
        $this->createPayment($user, $invSep, 'receipt', '900.00', '2026-09-01');
        $this->createExpense($user, 'EXP-SEP01', '800.00', 'approved', '2026-09-01');

        $this->actingAsOrgUser($user)->get(route('finance.dashboard', ['period' => 'month', 'month' => '2026-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Revenue: Only Aug 1 (2000) + Aug 31 (3000) = 5000
                ->where('dashboardWidgets.finance.revenue.invoiced_revenue', 5000)
                ->where('financeSummary.invoiced_revenue', 5000)
                // Cash In: Only Aug 1 (500) + Aug 31 (700) = 1200
                ->where('dashboardWidgets.finance.cash_in.receipts', 1200)
                ->where('financeSummary.cash_in', 1200)
                // Expense: Only Aug 1 (400) + Aug 31 (600) = 1000
                ->where('dashboardWidgets.finance.expense.recognized_expense', 1000)
                ->where('financeSummary.recognized_expense', 1000)
            );
    }

    public function test_negative_reversals_and_partial_payments_calculation(): void
    {
        $user = User::factory()->create();
        $this->attachAllDashboardPermissions($user);
        $customer = $this->createCustomer($user, 'CUST-REVERSAL');

        $invoice = $this->createInvoice($user, $customer, 'INV-REV-01', '2000.00', '1300.00', now()->toDateString());

        // Receipt of 1000
        $receipt = $this->createPayment($user, $invoice, 'receipt', '1000.00', now()->toDateString());

        // Partial Reversal of 300
        $this->createPayment($user, $invoice, 'reversal', '300.00', now()->toDateString(), $receipt->id);

        $this->actingAsOrgUser($user)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Net cash should be 1000 - 300 = 700 in financeSummary
                ->where('financeSummary.cash_in_receipts', 1000)
                ->where('financeSummary.cash_in_reversals', 300)
                ->where('financeSummary.cash_in', 700)
                ->where('dashboardWidgets.finance.cash_in.receipts', 1000)
                ->where('dashboardWidgets.finance.cash_in.reversals', 300)
                // Outstanding balance due
                ->where('dashboardWidgets.finance.ar.outstanding_ar', 1300)
                ->where('financeSummary.outstanding_ar', 1300)
            );
    }

    public function test_cancelled_void_and_rejected_documents_are_strictly_excluded(): void
    {
        $user = User::factory()->create();
        $this->attachAllDashboardPermissions($user);
        $customer = $this->createCustomer($user, 'CUST-EXCLUDE');
        $supplier = Supplier::create(['org_id' => $user->org_id, 'supplier_code' => 'SUP-EX', 'name' => 'Supplier Ex', 'status' => 'active']);

        // Valid Invoice
        $this->createInvoice($user, $customer, 'INV-VALID', '1500.00', '1500.00', now()->toDateString(), 'sent');
        // Void / Cancelled Invoices
        $this->createInvoice($user, $customer, 'INV-VOID', '5000.00', '5000.00', now()->toDateString(), 'void');
        $this->createInvoice($user, $customer, 'INV-CANCELLED', '3000.00', '3000.00', now()->toDateString(), 'cancelled');

        // Valid Expenses (approved & paid)
        $this->createExpense($user, 'EXP-APPROVED', '250.00', 'approved', now()->toDateString());
        $this->createExpense($user, 'EXP-PAID', '150.00', 'paid', now()->toDateString());
        // Excluded Expenses (draft & rejected)
        $this->createExpense($user, 'EXP-DRAFT', '9999.00', 'draft', now()->toDateString());
        $this->createExpense($user, 'EXP-REJECTED', '8888.00', 'rejected', now()->toDateString());

        // Deals: Won, Open, Lost
        $this->createDeal($user, $customer, 'Deal Won', 'won', '1000.00');
        $this->createDeal($user, $customer, 'Deal Open', 'proposal', '2000.00');
        $this->createDeal($user, $customer, 'Deal Lost', 'lost', '99000.00');

        // Purchase Orders: Approved vs Cancelled
        PurchaseOrder::create([
            'org_id' => $user->org_id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-ACTIVE',
            'status' => 'approved',
            'order_date' => now()->toDateString(),
            'total' => '500.00',
            'currency' => 'THB',
        ]);
        PurchaseOrder::create([
            'org_id' => $user->org_id,
            'supplier_id' => $supplier->id,
            'po_no' => 'PO-CANCELLED',
            'status' => 'cancelled',
            'order_date' => now()->toDateString(),
            'total' => '88000.00',
            'currency' => 'THB',
        ]);

        // Finance check
        $this->actingAsOrgUser($user)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Invoiced: only 1500 (void and cancelled excluded)
                ->where('dashboardWidgets.finance.revenue.invoiced_revenue', 1500)
                ->where('dashboardWidgets.finance.ar.outstanding_ar', 1500)
                ->where('financeSummary.invoiced_revenue', 1500)
                ->where('financeSummary.outstanding_ar', 1500)
                // Expense: only 250 + 150 = 400 (draft and rejected excluded)
                ->where('dashboardWidgets.finance.expense.recognized_expense', 400)
                ->where('financeSummary.recognized_expense', 400)
            );

        // Sales check
        $this->actingAsOrgUser($user)->get(route('sales.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.open_deals', 1)
                ->where('summary.won_deals', 1)
                ->where('pipelineByStage.0.stage', 'proposal')
            );

        // Procurement check
        $this->actingAsOrgUser($user)->get(route('procurement.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('openPoCount', 1)
                ->where('openPos.0.po_no', 'PO-ACTIVE')
            );
    }

    public function test_fx_base_currency_preserves_converted_values(): void
    {
        $user = User::factory()->create();
        $this->attachAllDashboardPermissions($user);
        $customer = $this->createCustomer($user, 'CUST-FX');

        // THB invoice (same currency)
        $invThb = Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => 'INV-THB',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => '10000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => '10000.00',
            'paid_amount' => '0.00',
            'balance_due' => '10000.00',
            'currency' => 'THB',
            'exchange_rate' => 1.0,
            'base_currency' => 'THB',
            'base_subtotal' => '10000.00',
            'base_tax_amount' => '0.00',
            'base_total' => '10000.00',
            'base_paid_amount' => '0.00',
            'base_balance_due' => '10000.00',
            'created_by' => $user->id,
        ]);

        // USD invoice with exchange rate 35.0
        $invUsd = Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => 'INV-USD',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'subtotal' => '100.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => '100.00',
            'paid_amount' => '0.00',
            'balance_due' => '100.00',
            'currency' => 'USD',
            'exchange_rate' => 35.0,
            'base_currency' => 'THB',
            'base_subtotal' => '3500.00',
            'base_tax_amount' => '0.00',
            'base_total' => '3500.00',
            'base_paid_amount' => '0.00',
            'base_balance_due' => '3500.00',
            'created_by' => $user->id,
        ]);

        $this->actingAsOrgUser($user)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                // Invoiced base total: 10,000 + 3,500 = 13,500 THB
                ->where('dashboardWidgets.finance.revenue.invoiced_revenue', 13500)
                ->where('dashboardWidgets.finance.ar.outstanding_ar', 13500)
            );
    }

    public function test_reconciliation_between_kpi_and_filtered_source_queries(): void
    {
        $user = User::factory()->create();
        $this->attachAllDashboardPermissions($user);
        $customer = $this->createCustomer($user, 'CUST-RECON');

        $this->createInvoice($user, $customer, 'INV-RC-1', '5000.00', '2000.00', now()->toDateString(), 'partially_paid');
        $this->createInvoice($user, $customer, 'INV-RC-2', '3000.00', '3000.00', now()->toDateString(), 'sent');
        $this->createExpense($user, 'EXP-RC-1', '1200.00', 'approved', now()->toDateString());
        $this->createExpense($user, 'EXP-RC-2', '800.00', 'paid', now()->toDateString());

        // Reconcile Invoice balance_due
        $expectedOutstanding = (int) Invoice::where('org_id', $user->org_id)
            ->whereIn('status', ['sent', 'partially_paid', 'overdue'])
            ->sum('base_balance_due');

        // Reconcile Expense amount
        $expectedExpense = (int) Expense::where('org_id', $user->org_id)
            ->whereIn('status', ['approved', 'partially_paid', 'paid'])
            ->sum('base_amount');

        $this->actingAsOrgUser($user)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardWidgets.finance.ar.outstanding_ar', $expectedOutstanding)
                ->where('dashboardWidgets.finance.expense.recognized_expense', $expectedExpense)
                ->where('financeSummary.outstanding_ar', $expectedOutstanding)
                ->where('financeSummary.recognized_expense', $expectedExpense)
            );
    }

    private function createCustomer(User $user, string $code): Customer
    {
        return Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => $code,
            'company_name' => $code,
            'owner_id' => $user->id,
        ]);
    }

    private function createInvoice(User $user, Customer $customer, string $no, string $total, string $balance, string $date, string $status = 'sent'): Invoice
    {
        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => $no,
            'customer_id' => $customer->id,
            'status' => $status,
            'issue_date' => $date,
            'due_date' => $date,
            'subtotal' => $total,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => $total,
            'paid_amount' => bcsub($total, $balance, 2),
            'balance_due' => $balance,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'base_subtotal' => $total,
            'base_tax_amount' => '0.00',
            'base_total' => $total,
            'base_paid_amount' => bcsub($total, $balance, 2),
            'base_balance_due' => $balance,
            'created_by' => $user->id,
        ]);
    }

    private function createPayment(User $user, Invoice $invoice, string $type, string $amount, string $date, ?string $reversalOf = null): Payment
    {
        return Payment::create([
            'org_id' => $user->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => $type,
            'amount' => $amount,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1.0,
            'base_amount' => $amount,
            'invoice_base_amount' => $amount,
            'payment_date' => $date,
            'payment_method' => 'bank_transfer',
            'reversal_of_payment_id' => $reversalOf,
            'created_by' => $user->id,
        ]);
    }

    private function createExpense(User $user, string $no, string $amount, string $status, string $date): Expense
    {
        return Expense::create([
            'org_id' => $user->org_id,
            'expense_no' => $no,
            'category' => 'delivery',
            'title' => $no,
            'amount' => $amount,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1.0,
            'base_amount' => $amount,
            'payable_total' => $amount,
            'base_payable_total' => $amount,
            'expense_date' => $date,
            'status' => $status,
            'created_by' => $user->id,
        ]);
    }

    private function createDeal(User $user, Customer $customer, string $title, string $stage, string $amount): Deal
    {
        return Deal::create([
            'org_id' => $user->org_id,
            'title' => $title,
            'customer_id' => $customer->id,
            'stage' => $stage,
            'value_amount' => $amount,
            'currency' => 'THB',
            'probability' => $stage === 'won' ? 100 : ($stage === 'lost' ? 0 : 50),
            'won_at' => $stage === 'won' ? now() : null,
            'lost_at' => $stage === 'lost' ? now() : null,
            'owner_id' => $user->id,
        ]);
    }

    private function attachAllDashboardPermissions(User $user): void
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => 'super_viewer',
            'name' => 'Super Viewer',
            'is_system' => true,
        ]);

        $permissions = [
            'dashboard.view',
            'executive.dashboard.view',
            'sales.dashboard.view',
            'customers.view',
            'deals.view',
            'invoices.view',
            'payments.view',
            'expenses.view',
            'purchase_orders.view',
            'inventory.view',
            'projects.view',
            'tasks.view',
            'hr.view',
            'payroll.view',
            'users.view',
            'roles.view',
            'audit.view',
        ];

        foreach ($permissions as $code) {
            $perm = Permission::firstOrCreate(
                ['code' => $code],
                ['module' => 'all', 'action' => $code, 'description' => $code]
            );
            $role->permissions()->syncWithoutDetaching([$perm->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);
    }
}
