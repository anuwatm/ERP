<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorPayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase3FinanceDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_finance_dashboard_calculates_finance_metrics_with_reversal_breakdown(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['dashboard.view', 'invoices.view', 'payments.view', 'expenses.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Dashboard Customer',
            'owner_id' => $finance->id,
        ]);

        $sent = $this->invoice($finance, $customer, '000001', 'sent', 1000, 700, now()->addDays(7)->toDateString());
        $overdue = $this->invoice($finance, $customer, '000002', 'overdue', 500, 500, now()->subDay()->toDateString());
        $paid = $this->invoice($finance, $customer, '000003', 'paid', 300, 0, now()->subDays(10)->toDateString());
        $this->invoice($finance, $customer, '000004', 'void', 999, 999, now()->subDay()->toDateString());

        $receipt = Payment::create([
            'org_id' => $finance->org_id,
            'invoice_id' => $sent->id,
            'entry_type' => 'receipt',
            'amount' => '400.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'created_by' => $finance->id,
        ]);
        Payment::create([
            'org_id' => $finance->org_id,
            'invoice_id' => $sent->id,
            'entry_type' => 'reversal',
            'reversal_of_payment_id' => $receipt->id,
            'amount' => '100.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'created_by' => $finance->id,
        ]);
        Payment::create([
            'org_id' => $finance->org_id,
            'invoice_id' => $paid->id,
            'entry_type' => 'receipt',
            'amount' => '300.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'created_by' => $finance->id,
        ]);

        Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => '000001',
            'category' => 'hosting',
            'title' => 'Paid host',
            'amount' => '200.00',
            'expense_date' => now()->toDateString(),
            'status' => 'paid',
            'paid_at' => now(),
            'created_by' => $finance->id,
        ]);
        Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => '000002',
            'category' => 'software',
            'title' => 'Approved tool',
            'amount' => '50.00',
            'expense_date' => now()->toDateString(),
            'status' => 'approved',
            'created_by' => $finance->id,
        ]);
        Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => '000003',
            'category' => 'misc',
            'title' => 'Draft ignored',
            'amount' => '999.00',
            'expense_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $finance->id,
        ]);

        $this->actingAsOrgUser($finance)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('financeSummary.invoiced_revenue', 1800)
                ->where('financeSummary.cash_in_receipts', 700)
                ->where('financeSummary.cash_in_reversals', 100)
                ->where('financeSummary.cash_in', 600)
                ->where('financeSummary.outstanding_ar', 1200)
                ->where('financeSummary.overdue_ar', 500)
                ->where('financeSummary.recognized_expense', 250)
                ->where('financeSummary.cash_out', 200)
                ->where('financeSummary.net_cash_flow', 400)
                ->where('financeSummary.billed_minus_expense_estimate', 1550)
                ->where('financeSummary.payment_reversals.count', 1)
                ->where('financeSummary.payment_reversals.amount', 100)
            );
    }

    public function test_invoice_only_user_does_not_receive_company_wide_finance_summary(): void
    {
        $sales = User::factory()->create();
        $this->attachRole($sales, 'sales', ['dashboard.view', 'invoices.view']);

        $this->actingAsOrgUser($sales)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('financeSummary', null)
            );
    }

    public function test_non_finance_dashboard_does_not_receive_finance_summary(): void
    {
        $member = User::factory()->create();
        $this->attachRole($member, 'member', ['dashboard.view']);

        $this->actingAsOrgUser($member)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('financeSummary', null)
            );
    }

    public function test_finance_dashboard_is_org_scoped(): void
    {
        $finance = User::factory()->create();
        $other = User::factory()->create();
        $this->attachRole($finance, 'finance', ['dashboard.view', 'invoices.view', 'payments.view', 'expenses.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Visible Customer',
            'owner_id' => $finance->id,
        ]);
        $otherCustomer = Customer::create([
            'org_id' => $other->org_id,
            'customer_code' => '000001',
            'company_name' => 'Hidden Customer',
            'owner_id' => $other->id,
        ]);
        $this->invoice($finance, $customer, '000001', 'sent', 100, 100, now()->addDay()->toDateString());
        $this->invoice($other, $otherCustomer, '000001', 'sent', 900, 900, now()->addDay()->toDateString());

        $this->actingAsOrgUser($finance)->get(route('finance.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('financeSummary.invoiced_revenue', 100)
                ->where('financeSummary.outstanding_ar', 100)
            );
    }

    public function test_new_finance_widgets_use_base_currency_and_current_ar_snapshot(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance_widgets', ['dashboard.view', 'invoices.view', 'payments.view', 'expenses.view']);
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'FX Customer',
            'owner_id' => $finance->id,
        ]);

        $oldInvoice = $this->invoice($finance, $customer, 'FX-OLD', 'overdue', 100, 60, now()->subDays(5)->toDateString());
        $oldInvoice->update([
            'issue_date' => now()->subMonths(2)->toDateString(),
            'base_total' => 200,
            'base_balance_due' => 120,
        ]);
        $currentInvoice = $this->invoice($finance, $customer, 'FX-NEW', 'sent', 50, 50, now()->addDays(5)->toDateString());
        $currentInvoice->update(['base_total' => 100, 'base_balance_due' => 100]);

        $payment = Payment::create([
            'org_id' => $finance->org_id,
            'invoice_id' => $currentInvoice->id,
            'entry_type' => 'receipt',
            'amount' => 10,
            'base_amount' => 20,
            'payment_date' => now()->toDateString(),
            'payment_method' => 'cash',
            'created_by' => $finance->id,
        ]);
        $paidExpense = Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => 'FX-EXP',
            'category' => 'software',
            'title' => 'Partial expense',
            'amount' => 15,
            'base_amount' => 30,
            'expense_date' => now()->toDateString(),
            'status' => 'partially_paid',
            'created_by' => $finance->id,
        ]);
        VendorPayment::create([
            'org_id' => $finance->org_id,
            'expense_id' => $paidExpense->id,
            'entry_type' => 'payment',
            'payment_date' => now()->toDateString(),
            'amount' => 8,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_amount' => 16,
            'expense_base_amount' => 16,
            'idempotency_key' => 'dashboard-ap-payment',
            'created_by' => $finance->id,
        ]);
        VendorPayment::create([
            'org_id' => $finance->org_id,
            'expense_id' => $paidExpense->id,
            'entry_type' => 'reversal',
            'payment_date' => now()->toDateString(),
            'amount' => 3,
            'currency' => 'THB',
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_amount' => 6,
            'expense_base_amount' => 6,
            'idempotency_key' => 'dashboard-ap-reversal',
            'created_by' => $finance->id,
        ]);
        Expense::create([
            'org_id' => $finance->org_id,
            'expense_no' => 'FX-DRAFT',
            'category' => 'software',
            'title' => 'Awaiting approval',
            'amount' => 7,
            'base_amount' => 14,
            'expense_date' => now()->toDateString(),
            'status' => 'draft',
            'created_by' => $finance->id,
        ]);

        $this->actingAsOrgUser($finance)->get(route('finance.dashboard', ['period' => 'month', 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardWidgets.finance.revenue.invoiced_revenue', 100)
                ->where('dashboardWidgets.finance.ar.outstanding_ar', 220)
                ->where('dashboardWidgets.finance.ar.overdue_ar', 120)
                ->where('dashboardWidgets.finance.ar.aging.current', 100)
                ->where('dashboardWidgets.finance.ar.aging.days_1_30', 120)
                ->where('dashboardWidgets.finance.cash_in.receipts', 20)
                ->where('dashboardWidgets.finance.expense.recognized_expense', 30)
                ->where('dashboardWidgets.finance.expense.ap_cash_out', 10)
                ->where('dashboardWidgets.finance.cash_flow_trend.0.label', now()->format('Y-m'))
                ->where('dashboardWidgets.finance.cash_flow_trend.0.cash_in', 20)
                ->where('dashboardWidgets.finance.cash_flow_trend.0.ap_cash_out', 10)
                ->where('dashboardWidgets.finance.overdue_invoices.0.id', $oldInvoice->id)
                ->where('dashboardWidgets.finance.recent_payments.0.id', $payment->id)
                ->where('dashboardWidgets.finance.expenses_awaiting_approval.0.expense_no', 'FX-DRAFT'));

        $this->actingAsOrgUser($finance)->get(route('payments.index', ['payment_id' => $payment->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 1)->where('payments.data.0.id', $payment->id));
        $this->actingAsOrgUser($finance)->get(route('vendor-payments.index', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->endOfMonth()->toDateString()]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('payments.data', 2));
        $this->actingAsOrgUser($finance)->get(route('invoices.index', ['aging_bucket' => 'days_1_30']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('invoices', 1)->where('invoices.0.id', $oldInvoice->id));
        $this->actingAsOrgUser($finance)->get(route('invoices.index', ['overdue' => '1', 'search' => 'FX-OLD']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('invoices', 1)->where('invoices.0.id', $oldInvoice->id));
        $this->actingAsOrgUser($finance)->get(route('expenses.index', ['status' => 'draft', 'search' => 'FX-DRAFT']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('expenses', 1)->where('expenses.0.expense_no', 'FX-DRAFT'));
    }

    public function test_legacy_same_currency_amounts_are_backfilled_without_touching_fx_rows(): void
    {
        $finance = User::factory()->create();
        $customer = Customer::create([
            'org_id' => $finance->org_id,
            'customer_code' => '000001',
            'company_name' => 'Legacy Customer',
            'owner_id' => $finance->id,
        ]);
        $sameCurrency = $this->invoice($finance, $customer, 'LEGACY-THB', 'sent', 100, 75, now()->addDay()->toDateString());
        $foreignCurrency = $this->invoice($finance, $customer, 'LEGACY-FX', 'sent', 100, 75, now()->addDay()->toDateString());
        $foreignCurrency->update(['currency' => 'USD', 'base_currency' => 'THB']);

        $migration = require database_path('migrations/2026_09_17_000001_backfill_same_currency_finance_base_amounts.php');
        $migration->up();
        $migration->up();

        $this->assertEquals(100, $sameCurrency->fresh()->base_total);
        $this->assertEquals(75, $sameCurrency->fresh()->base_balance_due);
        $this->assertEquals(0, $foreignCurrency->fresh()->base_total);
        $this->assertEquals(0, $foreignCurrency->fresh()->base_balance_due);
    }

    private function invoice(User $user, Customer $customer, string $invoiceNo, string $status, int $total, int $balance, string $dueDate): Invoice
    {
        return Invoice::create([
            'org_id' => $user->org_id,
            'invoice_no' => $invoiceNo,
            'customer_id' => $customer->id,
            'status' => $status,
            'tax_mode' => 'no_tax',
            'issue_date' => now()->toDateString(),
            'due_date' => $dueDate,
            'subtotal' => $total,
            'total' => $total,
            'paid_amount' => $total - $balance,
            'balance_due' => $balance,
        ]);
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => Str::headline($code),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $parts = explode('.', $permissionCode);
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => $parts[0], 'action' => $parts[count($parts) - 1]]
            );
            $role->permissions()->attach($permission->id);
        }

        $user->roles()->attach($role->id);

        return $role;
    }
}
