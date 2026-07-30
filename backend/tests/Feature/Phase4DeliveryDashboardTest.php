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
        $this->attachRole($manager, 'project_manager', ['dashboard.view', 'projects.view', 'tasks.view']);

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

        $this->actingAsOrgUser($manager)->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('deliverySummary.active_projects', 2)
                ->where('deliverySummary.overdue_tasks', 1)
                ->where('deliverySummary.total_budget', 6000)
                ->where('deliverySummary.actual_cost', 3000)
                ->where('deliverySummary.project_profit', 3000)
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
                ->where('deliverySummary.active_projects', 0)
                ->where('deliverySummary.overdue_tasks', 1)
                ->where('deliverySummary.task_load.0.assignee_id', $member->id)
                ->where('deliverySummary.task_load.0.open_tasks', 1)
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
