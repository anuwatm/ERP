<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
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

        $this->actingAsOrgUser($finance)->get(route('dashboard'))
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
                ->where('financeSummary.gross_profit', 1550)
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
        $this->attachRole($finance, 'finance', ['dashboard.view', 'expenses.view']);
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

        $this->actingAsOrgUser($finance)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('financeSummary.invoiced_revenue', 100)
                ->where('financeSummary.outstanding_ar', 100)
            );
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
