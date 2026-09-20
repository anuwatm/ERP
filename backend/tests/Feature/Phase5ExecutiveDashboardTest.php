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
        $this->attachRole($owner, 'director', ['dashboard.view', 'executive.dashboard.view', 'customers.view', 'deals.view', 'invoices.view', 'payments.view', 'expenses.view', 'projects.view', 'tasks.view']);

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
            'base_currency' => 'THB',
            'exchange_rate' => 1,
            'base_total' => '1200.00',
            'base_paid_amount' => '350.00',
            'base_balance_due' => '850.00',
            'created_by' => $owner->id,
        ]);

        Payment::create([
            'org_id' => $owner->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => 'receipt',
            'amount' => '400.00',
            'base_amount' => '400.00',
            'payment_date' => now()->toDateString(),
            'payment_method' => 'bank_transfer',
            'created_by' => $owner->id,
        ]);

        Payment::create([
            'org_id' => $owner->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => 'reversal',
            'amount' => '50.00',
            'base_amount' => '50.00',
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
            'base_amount' => '300.00',
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
                ->where('executiveSummary', null)
                ->where('dashboardWidgets.executive.sales.open_deals', 1)
                ->where('dashboardWidgets.executive.sales.pipeline_by_currency.0.amount', 1000)
                ->where('dashboardWidgets.executive.sales.won_deals', 1)
                ->where('dashboardWidgets.executive.sales.won_by_currency.0.amount', 500)
                ->where('dashboardWidgets.delivery.projects.active_projects', 1)
                ->where('dashboardWidgets.delivery.tasks.overdue_tasks', 1)
                ->where('dashboardWidgets.executive.project_risks.count', 1)
                ->where('dashboardWidgets.executive.project_risks.items.0.reasons', ['Past due', 'High-priority open task'])
                ->has('dashboardWidgets.finance.revenue')
                ->has('dashboardWidgets.finance.cash_in')
                ->has('dashboardWidgets.finance.ar')
                ->has('dashboardWidgets.finance.expense')
            );

        $this->get(route('executive.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('executiveSummary', null)
                ->has('dashboardWidgets.executive.sales')
                ->has('dashboardWidgets.executive.revenue_expense_trend', 12)
                ->has('dashboardWidgets.executive.top_customers', 1)
                ->has('dashboardWidgets.finance.overdue_invoices', 1)
                ->has('dashboardWidgets.executive.project_risks.items', 1));
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

    public function test_executive_widgets_keep_sales_scope_and_currencies_separate(): void
    {
        $director = User::factory()->create();
        $other = User::factory()->create(['org_id' => $director->org_id]);
        $this->attachRole($director, 'director', ['dashboard.view', 'executive.dashboard.view', 'deals.view']);
        $customer = Customer::create(['org_id' => $director->org_id, 'customer_code' => 'EXEC-SCOPE', 'company_name' => 'Scoped', 'owner_id' => $director->id]);
        foreach ([
            ['title' => 'Own THB', 'stage' => 'proposal', 'currency' => 'THB', 'amount' => 100, 'owner' => $director->id],
            ['title' => 'Own USD', 'stage' => 'proposal', 'currency' => 'USD', 'amount' => 50, 'owner' => $director->id],
            ['title' => 'Other THB', 'stage' => 'proposal', 'currency' => 'THB', 'amount' => 900, 'owner' => $other->id],
            ['title' => 'Own won', 'stage' => 'won', 'currency' => 'THB', 'amount' => 200, 'owner' => $director->id],
        ] as $item) {
            Deal::create([
                'org_id' => $director->org_id,
                'title' => $item['title'],
                'customer_id' => $customer->id,
                'stage' => $item['stage'],
                'currency' => $item['currency'],
                'value_amount' => $item['amount'],
                'owner_id' => $item['owner'],
                'won_at' => $item['stage'] === 'won' ? now() : null,
            ]);
        }

        $this->actingAsOrgUser($director)->get(route('executive.dashboard', ['period' => 'month', 'month' => now()->format('Y-m')]))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('executiveSummary', null)
            ->where('dashboardWidgets.executive.sales.open_deals', 2)
            ->where('dashboardWidgets.executive.sales.won_deals', 1)
            ->has('dashboardWidgets.executive.sales.pipeline_by_currency', 2)
            ->where('dashboardWidgets.executive.sales.pipeline_by_currency.0.amount', 100)
            ->where('dashboardWidgets.executive.sales.pipeline_by_currency.1.amount', 50)
            ->where('dashboardWidgets.executive.sales.won_by_currency.0.amount', 200)
            ->missing('dashboardWidgets.executive.top_customers')
            ->missing('dashboardWidgets.executive.revenue_expense_trend')
            );
    }

    public function test_project_risk_uses_same_currency_approved_cost_and_project_scope(): void
    {
        $director = User::factory()->create();
        $other = User::factory()->create(['org_id' => $director->org_id]);
        $this->attachRole($director, 'director', ['dashboard.view', 'executive.dashboard.view', 'projects.view', 'expenses.view']);
        foreach ([
            ['code' => 'RISK-THB', 'owner' => $director->id, 'cost_currency' => 'THB'],
            ['code' => 'RISK-FX', 'owner' => $director->id, 'cost_currency' => 'USD'],
            ['code' => 'RISK-HIDDEN', 'owner' => $other->id, 'cost_currency' => 'THB'],
        ] as $item) {
            $project = Project::create([
                'org_id' => $director->org_id, 'project_code' => $item['code'], 'name' => $item['code'],
                'owner_id' => $item['owner'], 'status' => 'active', 'due_date' => now()->addWeek()->toDateString(),
                'budget_amount' => 100, 'currency' => 'THB',
            ]);
            Expense::create([
                'org_id' => $director->org_id, 'expense_no' => 'EXP-'.$item['code'],
                'category' => 'delivery', 'title' => $item['code'], 'amount' => 150,
                'currency' => $item['cost_currency'], 'expense_date' => now()->toDateString(),
                'project_id' => $project->id, 'status' => 'approved',
            ]);
        }

        $this->actingAsOrgUser($director)->get(route('executive.dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardWidgets.executive.project_risks.count', 1)
                ->has('dashboardWidgets.executive.project_risks.items', 1)
                ->where('dashboardWidgets.executive.project_risks.items.0.project_code', 'RISK-THB')
                ->where('dashboardWidgets.executive.project_risks.items.0.reasons', ['Over budget']));
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
