<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Deal;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase5ExecutiveDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_executive_permission_sees_sales_finance_delivery_metrics_without_cash_balance(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'director', ['dashboard.view', 'executive.dashboard.view']);

        $customer = Customer::create([
            'org_id' => $owner->org_id,
            'customer_code' => 'C000001',
            'company_name' => 'Executive Customer',
            'owner_id' => $owner->id,
        ]);

        Deal::create([
            'org_id' => $owner->org_id,
            'title' => 'Open Deal',
            'customer_id' => $customer->id,
            'stage' => 'proposal',
            'value_amount' => '1000.00',
            'currency' => 'THB',
            'probability' => 60,
            'owner_id' => $owner->id,
        ]);

        Deal::create([
            'org_id' => $owner->org_id,
            'title' => 'Won Deal',
            'customer_id' => $customer->id,
            'stage' => 'won',
            'value_amount' => '500.00',
            'currency' => 'THB',
            'probability' => 100,
            'owner_id' => $owner->id,
            'won_at' => now(),
        ]);

        $invoice = Invoice::create([
            'org_id' => $owner->org_id,
            'invoice_no' => 'INV-000001',
            'customer_id' => $customer->id,
            'status' => 'sent',
            'issue_date' => now()->subDays(15)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'subtotal' => '1200.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => '1200.00',
            'paid_amount' => '350.00',
            'balance_due' => '850.00',
            'currency' => 'THB',
            'created_by' => $owner->id,
        ]);

        Payment::create([
            'org_id' => $owner->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => 'receipt',
            'amount' => '400.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'created_by' => $owner->id,
        ]);

        Payment::create([
            'org_id' => $owner->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => 'reversal',
            'amount' => '50.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'created_by' => $owner->id,
        ]);

        $project = Project::create([
            'org_id' => $owner->org_id,
            'project_code' => 'P000001',
            'name' => 'Executive Project',
            'customer_id' => $customer->id,
            'owner_id' => $owner->id,
            'status' => 'active',
            'due_date' => now()->subDay()->toDateString(),
            'progress_percent' => 0,
            'budget_amount' => '1000.00',
            'currency' => 'THB',
        ]);

        Expense::create([
            'org_id' => $owner->org_id,
            'expense_no' => 'EXP-000001',
            'category' => 'delivery',
            'title' => 'Approved Cost',
            'amount' => '300.00',
            'expense_date' => now()->toDateString(),
            'project_id' => $project->id,
            'status' => 'approved',
            'created_by' => $owner->id,
        ]);

        Expense::create([
            'org_id' => $owner->org_id,
            'expense_no' => 'EXP-000002',
            'category' => 'delivery',
            'title' => 'Draft Cost',
            'amount' => '999.00',
            'expense_date' => now()->toDateString(),
            'project_id' => $project->id,
            'status' => 'draft',
            'created_by' => $owner->id,
        ]);

        Task::create([
            'org_id' => $owner->org_id,
            'project_id' => $project->id,
            'title' => 'Overdue Task',
            'status' => 'todo',
            'priority' => 'urgent',
            'assignee_id' => $owner->id,
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAsOrgUser($owner)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('executiveSummary.sales.customers', 1)
                ->where('executiveSummary.sales.open_deals', 1)
                ->where('executiveSummary.sales.pipeline_value', 1000)
                ->where('executiveSummary.sales.won_deals', 1)
                ->where('executiveSummary.sales.won_value', 500)
                ->where('executiveSummary.finance.invoiced_revenue', 1200)
                ->where('executiveSummary.finance.cash_in', 350)
                ->where('executiveSummary.finance.outstanding_ar', 850)
                ->where('executiveSummary.finance.overdue_ar', 850)
                ->where('executiveSummary.finance.recognized_expense', 300)
                ->where('executiveSummary.finance.gross_profit', 900)
                ->where('executiveSummary.delivery.active_projects', 1)
                ->where('executiveSummary.delivery.overdue_tasks', 1)
                ->where('executiveSummary.delivery.project_profit', 700)
                ->where('executiveSummary.delivery.delivery_risk_count', 1)
                ->missing('executiveSummary.cash_balance')
            );
    }

    public function test_non_executive_finance_user_does_not_receive_executive_summary(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['dashboard.view', 'expenses.view']);

        $this->actingAsOrgUser($finance)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('executiveSummary', null)
                ->has('financeSummary')
            );
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
