<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase4DeliveryDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_delivery_dashboard_calculates_project_task_cost_and_risk_metrics(): void
    {
        $manager = User::factory()->create();
        $otherOwner = User::factory()->create(['org_id' => $manager->org_id]);
        $assignee = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($manager, 'project_manager', ['dashboard.view', 'projects.view', 'tasks.view', 'expenses.view']);

        $active = $this->project($manager, '000001', 'Active Project', $manager->id, [
            'status' => 'active',
            'budget_amount' => '5000.00',
            'due_date' => now()->addDays(10)->toDateString(),
        ]);
        $atRisk = $this->project($manager, '000002', 'At Risk Project', $manager->id, [
            'status' => 'active',
            'budget_amount' => '1000.00',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $this->project($manager, '000003', 'Hidden Project', $otherOwner->id, ['status' => 'active', 'budget_amount' => '9000.00']);

        $this->expense($manager, $active, 'approved', '1200.00', '000001');
        $this->expense($manager, $active, 'paid', '300.00', '000002');
        $this->expense($manager, $active, 'draft', '999.00', '000003');
        $this->expense($manager, $atRisk, 'approved', '1500.00', '000004');

        $this->task($manager, $active, 'Overdue Task', $assignee->id, [
            'status' => 'todo',
            'priority' => 'urgent',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $this->task($manager, $active, 'Blocked Task', $assignee->id, [
            'status' => 'blocked',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $this->task($manager, $active, 'Done Task', $assignee->id, [
            'status' => 'done',
            'due_date' => now()->subDay()->toDateString(),
            'completed_at' => now(),
        ]);
        $this->task($manager, $atRisk, 'At Risk Task', $manager->id, [
            'status' => 'in_progress',
            'priority' => 'high',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        $this->actingAsOrgUser($manager)->get(route('delivery.dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('deliverySummary.active_projects', 2)
                ->where('deliverySummary.overdue_tasks', 1)
                ->where('deliverySummary.total_budget', 6000)
                ->where('deliverySummary.actual_cost', 3000)
                ->where('deliverySummary.budget_remaining', 3000)
                ->where('deliverySummary.delivery_risk.count', 2)
                ->where('deliverySummary.delivery_risk.over_budget', 1)
                ->where('deliverySummary.delivery_risk.past_due_projects', 1)
                ->where('deliverySummary.delivery_risk.urgent_or_high_open_tasks', 2)
                ->where('deliverySummary.project_status.0.status', 'active')
                ->where('deliverySummary.project_status.0.count', 2)
                ->where('deliverySummary.task_load.0.assignee_id', $assignee->id)
                ->where('deliverySummary.task_load.0.open_tasks', 2)
            );
    }

    public function test_member_delivery_dashboard_uses_assigned_task_scope_only(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create(['org_id' => $manager->org_id]);
        $other = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($member, 'member', ['dashboard.view', 'tasks.view']);
        $project = $this->project($manager, '000001', 'Member Project', $manager->id);

        $this->task($manager, $project, 'Assigned Overdue', $member->id, [
            'status' => 'todo',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $this->task($manager, $project, 'Hidden Overdue', $other->id, [
            'status' => 'todo',
            'due_date' => now()->subDay()->toDateString(),
        ]);

        $this->actingAsOrgUser($member)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('deliverySummary', null)
                ->missing('dashboardWidgets.delivery.projects')
                ->where('dashboardWidgets.delivery.tasks.overdue_tasks', 1)
            );
    }

    public function test_user_without_delivery_permissions_does_not_receive_delivery_summary(): void
    {
        $finance = User::factory()->create();
        $this->attachRole($finance, 'finance', ['dashboard.view', 'expenses.view']);

        $this->actingAsOrgUser($finance)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('deliverySummary', null)
            );
    }

    public function test_delivery_widgets_are_current_scoped_and_drill_down_without_expense_leakage(): void
    {
        $manager = User::factory()->create();
        $this->attachRole($manager, 'delivery_viewer', ['dashboard.view', 'projects.view', 'tasks.view']);
        $project = $this->project($manager, 'DLV-1', 'Visible Delivery', $manager->id, [
            'status' => 'active',
            'progress_percent' => 40,
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $project->forceFill(['created_at' => now()->subMonths(2)])->save();
        $overdue = $this->task($manager, $project, 'Overdue scoped task', $manager->id, [
            'status' => 'todo',
            'due_date' => now()->subDay()->toDateString(),
        ]);
        $this->task($manager, $project, 'Blocked today', $manager->id, [
            'status' => 'blocked',
            'due_date' => now()->toDateString(),
        ]);
        $this->expense($manager, $project, 'approved', '100.00', 'DLV-EXP');

        $this->actingAsOrgUser($manager)->get(route('delivery.dashboard', ['period' => 'month', 'month' => now()->format('Y-m')]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('deliverySummary', null)
                ->where('dashboardWidgets.delivery.projects.active_projects', 1)
                ->where('dashboardWidgets.delivery.projects.average_progress', 40)
                ->where('dashboardWidgets.delivery.tasks.overdue_tasks', 1)
                ->where('dashboardWidgets.delivery.tasks.due_this_week', now()->isMonday() ? 1 : 2)
                ->where('dashboardWidgets.delivery.overdue_tasks.0.id', $overdue->id)
                ->where('dashboardWidgets.delivery.past_due_projects.0.id', $project->id)
                ->where('dashboardWidgets.delivery.assignee_load.0.open_tasks', 2));

        $this->actingAsOrgUser($manager)->get(route('projects.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('projects', 1)
                ->where('projects.0.id', $project->id)
                ->missing('projects.0.actual_cost')
                ->missing('projects.0.gross_margin'));
        $this->actingAsOrgUser($manager)->get(route('tasks.index', ['task_id' => $overdue->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1)->where('tasks.0.id', $overdue->id));
        $this->actingAsOrgUser($manager)->get(route('tasks.index', ['assignee_id' => $manager->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 2));
        $this->actingAsOrgUser($manager)->get(route('projects.index', ['status' => 'active']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('projects', 1));

        $outsider = User::factory()->create();
        $outsideProject = $this->project($outsider, 'OUT-1', 'Outside', $outsider->id);
        $outsideTask = $this->task($outsider, $outsideProject, 'Outside task', $outsider->id);
        $this->actingAsOrgUser($manager)->get(route('projects.index', ['project_id' => $outsideProject->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('projects', 0));
        $this->actingAsOrgUser($manager)->get(route('tasks.index', ['task_id' => $outsideTask->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 0));

        $this->attachRole($manager, 'expense_viewer', ['expenses.view']);
        $this->actingAsOrgUser($manager)->get(route('projects.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.actual_cost', '100.00')
                ->where('projects.0.budget_remaining', '-100.00'));
        Expense::create([
            'org_id' => $manager->org_id,
            'expense_no' => 'DLV-FX',
            'category' => 'delivery',
            'title' => 'Foreign currency cost',
            'amount' => 20,
            'currency' => 'USD',
            'expense_date' => now()->toDateString(),
            'project_id' => $project->id,
            'status' => 'approved',
            'created_by' => $manager->id,
        ]);
        $this->actingAsOrgUser($manager)->get(route('projects.index', ['project_id' => $project->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->missing('projects.0.actual_cost')
                ->missing('projects.0.budget_remaining'));
    }

    public function test_my_work_counts_only_assigned_tasks_and_links_to_exact_task(): void
    {
        // Monday exposes accidental mutation of today's fixture into last week.
        $this->travelTo(now()->setDate(2026, 10, 19)->setTime(5, 0));
        $user = User::factory()->create();
        $other = User::factory()->create(['org_id' => $user->org_id]);
        $this->attachRole($user, 'worker', ['tasks.view']);
        $businessNow = now($user->organization?->timezone ?: config('app.timezone'));
        $today = Task::create(['org_id' => $user->org_id, 'title' => 'Mine today', 'status' => 'todo', 'assignee_id' => $user->id, 'due_date' => $businessNow->toDateString()]);
        Task::create(['org_id' => $user->org_id, 'title' => 'Mine overdue', 'status' => 'in_progress', 'assignee_id' => $user->id, 'due_date' => $businessNow->copy()->subDay()->toDateString()]);
        Task::create(['org_id' => $user->org_id, 'title' => 'Mine done', 'status' => 'done', 'assignee_id' => $user->id, 'completed_at' => $businessNow]);
        Task::create(['org_id' => $user->org_id, 'title' => 'Other task', 'status' => 'todo', 'assignee_id' => $other->id, 'due_date' => $businessNow->toDateString()]);

        $this->actingAsOrgUser($user)->get(route('my-work.dashboard'))
            ->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('summary.due_today', 1)
            ->where('summary.overdue', 1)
            ->where('summary.completed_this_week', 1)
            ->has('groups', 1)
            ->has('groups.0.tasks', 2)
            ->where('hasMore', false));
        $this->get(route('tasks.index', ['task_id' => $today->id]))
            ->assertInertia(fn (Assert $page) => $page->has('tasks', 1)->where('tasks.0.id', $today->id));
    }

    private function project(User $user, string $code, string $name, string $ownerId, array $overrides = []): Project
    {
        $customer = Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => $code,
            'company_name' => $name.' Customer',
            'owner_id' => $user->id,
        ]);

        return Project::create(array_merge([
            'org_id' => $user->org_id,
            'project_code' => $code,
            'name' => $name,
            'customer_id' => $customer->id,
            'owner_id' => $ownerId,
            'status' => 'active',
            'progress_percent' => 0,
            'budget_amount' => '0.00',
            'currency' => 'THB',
        ], $overrides));
    }

    private function task(User $user, Project $project, string $title, string $assigneeId, array $overrides = []): Task
    {
        return Task::create(array_merge([
            'org_id' => $user->org_id,
            'project_id' => $project->id,
            'title' => $title,
            'description' => 'Task description',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $assigneeId,
            'due_date' => now()->addWeek()->toDateString(),
        ], $overrides));
    }

    private function expense(User $user, Project $project, string $status, string $amount, string $no): Expense
    {
        return Expense::create([
            'org_id' => $user->org_id,
            'expense_no' => $no,
            'category' => 'delivery',
            'title' => 'Project expense '.$no,
            'amount' => $amount,
            'expense_date' => now()->toDateString(),
            'project_id' => $project->id,
            'status' => $status,
            'created_by' => $user->id,
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
