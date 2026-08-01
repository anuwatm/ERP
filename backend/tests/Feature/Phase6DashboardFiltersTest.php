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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase6DashboardFiltersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_dashboard_month_filter_limits_executive_finance_and_delivery_metrics(): void
    {
        $this->travelTo(now()->setDate(2026, 8, 15)->startOfDay());

        $owner = User::factory()->create();
        $this->attachRole($owner, 'director', ['dashboard.view', 'executive.dashboard.view', 'expenses.view', 'projects.view', 'tasks.view']);

        $augustCustomer = $this->customer($owner, 'AUG-CUSTOMER', '2026-08-03');
        $julyCustomer = $this->customer($owner, 'JUL-CUSTOMER', '2026-07-03');

        $this->deal($owner, $augustCustomer, 'Aug Open', 'proposal', '1000.00', '2026-08-04');
        $this->deal($owner, $augustCustomer, 'Aug Won', 'won', '500.00', '2026-08-05');
        $this->deal($owner, $julyCustomer, 'July Won', 'won', '700.00', '2026-07-05');

        $augustInvoice = $this->invoice($owner, $augustCustomer, 'AUG-INV', '1200.00', '850.00', '2026-08-06', '2026-08-10');
        $julyInvoice = $this->invoice($owner, $julyCustomer, 'JUL-INV', '900.00', '900.00', '2026-07-06', '2026-07-10');

        $this->payment($owner, $augustInvoice, 'receipt', '400.00', '2026-08-07');
        $this->payment($owner, $augustInvoice, 'reversal', '50.00', '2026-08-08');
        $this->payment($owner, $julyInvoice, 'receipt', '900.00', '2026-07-07');

        $augustProject = $this->project($owner, $augustCustomer, 'AUG-PROJECT', '1000.00', '2026-08-09', '2026-08-10');
        $julyProject = $this->project($owner, $julyCustomer, 'JUL-PROJECT', '3000.00', '2026-07-09', '2026-07-10');

        $this->expense($owner, $augustProject, 'AUG-EXP', '200.00', 'approved', '2026-08-11');
        $this->expense($owner, $julyProject, 'JUL-EXP', '1000.00', 'approved', '2026-07-11');
        $this->task($owner, $augustProject, 'Aug Task', '2026-08-12', '2026-08-10');
        $this->task($owner, $julyProject, 'July Task', '2026-07-12', '2026-07-10');

        $this->actingAsOrgUser($owner)->get(route('dashboard', ['period' => 'month', 'month' => '2026-08']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('dashboardFilters.period', 'month')
                ->where('dashboardFilters.start_date', '2026-08-01')
                ->where('dashboardFilters.end_date', '2026-08-31')
                ->where('executiveSummary.sales.customers', 1)
                ->where('executiveSummary.sales.open_deals', 1)
                ->where('executiveSummary.sales.pipeline_value', 1000)
                ->where('executiveSummary.sales.won_deals', 1)
                ->where('executiveSummary.sales.won_value', 500)
                ->where('executiveSummary.finance.invoiced_revenue', 1200)
                ->where('executiveSummary.finance.cash_in', 350)
                ->where('executiveSummary.finance.outstanding_ar', 850)
                ->where('executiveSummary.finance.recognized_expense', 200)
                ->where('executiveSummary.finance.gross_profit', 1000)
                ->where('executiveSummary.delivery.active_projects', 1)
                ->where('executiveSummary.delivery.overdue_tasks', 1)
                ->where('executiveSummary.delivery.project_profit', 800)
                ->where('financeSummary.cash_in_receipts', 400)
                ->where('financeSummary.cash_in_reversals', 50)
                ->where('deliverySummary.total_budget', 1000)
                ->where('deliverySummary.actual_cost', 200)
            );
    }

    private function customer(User $owner, string $code, string $createdAt): Customer
    {
        $customer = Customer::create([
            'org_id' => $owner->org_id,
            'customer_code' => $code,
            'company_name' => $code,
            'owner_id' => $owner->id,
        ]);
        $customer->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $customer;
    }

    private function deal(User $owner, Customer $customer, string $title, string $stage, string $amount, string $createdAt): Deal
    {
        $deal = Deal::create([
            'org_id' => $owner->org_id,
            'title' => $title,
            'customer_id' => $customer->id,
            'stage' => $stage,
            'value_amount' => $amount,
            'currency' => 'THB',
            'probability' => $stage === 'won' ? 100 : 60,
            'owner_id' => $owner->id,
        ]);
        $deal->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $deal;
    }

    private function invoice(User $owner, Customer $customer, string $number, string $total, string $balance, string $issueDate, string $dueDate): Invoice
    {
        return Invoice::create([
            'org_id' => $owner->org_id,
            'invoice_no' => $number,
            'customer_id' => $customer->id,
            'status' => 'sent',
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'subtotal' => $total,
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'total' => $total,
            'paid_amount' => bcsub($total, $balance, 2),
            'balance_due' => $balance,
            'currency' => 'THB',
            'created_by' => $owner->id,
        ]);
    }

    private function payment(User $owner, Invoice $invoice, string $type, string $amount, string $date): Payment
    {
        return Payment::create([
            'org_id' => $owner->org_id,
            'invoice_id' => $invoice->id,
            'entry_type' => $type,
            'amount' => $amount,
            'payment_date' => $date,
            'payment_method' => 'bank_transfer',
            'created_by' => $owner->id,
        ]);
    }

    private function project(User $owner, Customer $customer, string $code, string $budget, string $createdAt, string $dueDate): Project
    {
        $project = Project::create([
            'org_id' => $owner->org_id,
            'project_code' => $code,
            'name' => $code,
            'customer_id' => $customer->id,
            'owner_id' => $owner->id,
            'status' => 'active',
            'due_date' => $dueDate,
            'progress_percent' => 0,
            'budget_amount' => $budget,
            'currency' => 'THB',
        ]);
        $project->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $project;
    }

    private function expense(User $owner, Project $project, string $number, string $amount, string $status, string $date): Expense
    {
        return Expense::create([
            'org_id' => $owner->org_id,
            'expense_no' => $number,
            'category' => 'delivery',
            'title' => $number,
            'amount' => $amount,
            'expense_date' => $date,
            'project_id' => $project->id,
            'status' => $status,
            'created_by' => $owner->id,
        ]);
    }

    private function task(User $owner, Project $project, string $title, string $createdAt, string $dueDate): Task
    {
        $task = Task::create([
            'org_id' => $owner->org_id,
            'project_id' => $project->id,
            'title' => $title,
            'status' => 'todo',
            'priority' => 'urgent',
            'assignee_id' => $owner->id,
            'due_date' => $dueDate,
        ]);
        $task->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $task;
    }

    private function attachRole(User $user, string $code, array $permissions): Role
    {
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => $code,
            'name' => str($code)->headline()->toString(),
            'is_system' => true,
        ]);

        foreach ($permissions as $permissionCode) {
            $permission = Permission::firstOrCreate(
                ['code' => $permissionCode],
                ['module' => 'test', 'action' => $permissionCode, 'description' => $permissionCode]
            );
            $role->permissions()->syncWithoutDetaching([$permission->id]);
        }

        $user->roles()->syncWithoutDetaching([$role->id]);

        return $role;
    }
}
