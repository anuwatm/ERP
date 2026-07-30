<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskChecklist;
use App\Models\TaskComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase4TasksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_existing_roles_receive_task_permissions_from_migration(): void
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);
        $user->roles()->attach($role->id);

        (require database_path('migrations/2026_07_29_000004_backfill_task_permissions.php'))->up();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'tasks.view')->exists());
        $this->assertTrue($role->fresh()->permissions()->where('code', 'tasks.update')->exists());
        $this->assertTrue($role->fresh()->permissions()->where('code', 'tasks.comment')->exists());
        $this->assertFalse($role->fresh()->permissions()->where('code', 'tasks.create')->exists());
    }

    public function test_project_manager_can_create_project_task_and_internal_task(): void
    {
        $manager = User::factory()->create();
        $assignee = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($manager, 'project_manager', ['tasks.create']);
        $project = $this->project($manager, '000001', 'Delivery Project', $manager->id);

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.store'), $this->taskPayload(['project_id' => $project->id, 'assignee_id' => $assignee->id, 'title' => 'Project Task']))
            ->assertRedirect();

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.store'), $this->taskPayload(['project_id' => '', 'title' => 'Internal Task']))
            ->assertRedirect();

        $this->assertDatabaseHas('tasks', ['org_id' => $manager->org_id, 'title' => 'Project Task', 'project_id' => $project->id, 'assignee_id' => $assignee->id]);
        $this->assertDatabaseHas('tasks', ['org_id' => $manager->org_id, 'title' => 'Internal Task', 'project_id' => null, 'assignee_id' => $manager->id]);
        $this->assertSame(2, AuditLog::where('action', 'task.create')->count());
    }

    public function test_member_sees_only_assigned_tasks(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create(['org_id' => $manager->org_id]);
        $other = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($member, 'member', ['tasks.view']);
        $project = $this->project($manager, '000001', 'Delivery Project', $manager->id);
        $this->task($manager, $project, 'Assigned Task', $member->id);
        $this->task($manager, $project, 'Hidden Task', $other->id);

        $this->actingAsOrgUser($member)->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Tasks')
                ->has('tasks', 1)
                ->where('tasks.0.title', 'Assigned Task')
            );
    }

    public function test_member_can_update_only_status_on_assigned_task(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($member, 'member', ['tasks.update']);
        $project = $this->project($manager, '000001', 'Delivery Project', $manager->id);
        $task = $this->task($manager, $project, 'Original Title', $member->id);

        $this->actingAsOrgUser($member)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('tasks.update', $task), $this->taskPayload([
                'project_id' => $project->id,
                'title' => 'Changed Title',
                'status' => 'done',
                'assignee_id' => $member->id,
            ]))->assertRedirect();

        $task->refresh();
        $this->assertSame('Original Title', $task->title);
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
    }

    public function test_checklist_has_org_scope_and_comment_requires_visible_task(): void
    {
        $manager = User::factory()->create();
        $member = User::factory()->create(['org_id' => $manager->org_id]);
        $other = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($member, 'member', ['tasks.update', 'tasks.comment']);
        $project = $this->project($manager, '000001', 'Delivery Project', $manager->id);
        $visible = $this->task($manager, $project, 'Visible Task', $member->id);
        $hidden = $this->task($manager, $project, 'Hidden Task', $other->id);

        $this->actingAsOrgUser($member)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.checklists.store', $visible), ['title' => 'Confirm scope'])
            ->assertRedirect();

        $checklist = TaskChecklist::where('task_id', $visible->id)->firstOrFail();
        $this->assertSame($manager->org_id, $checklist->org_id);

        $this->actingAsOrgUser($member)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('task-checklists.update', $checklist), ['is_done' => true])
            ->assertRedirect();
        $this->assertTrue($checklist->fresh()->is_done);

        $this->actingAsOrgUser($member)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.comments.store', $visible), ['body' => 'Working on it'])
            ->assertRedirect();
        $this->assertSame('Working on it', TaskComment::where('task_id', $visible->id)->firstOrFail()->body);

        $this->actingAsOrgUser($member)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('tasks.comments.store', $hidden), ['body' => 'No access'])
            ->assertForbidden();
    }

    public function test_blocked_task_is_not_auto_overdue(): void
    {
        $manager = User::factory()->create();
        $this->attachRole($manager, 'project_manager', ['tasks.view']);
        $project = $this->project($manager, '000001', 'Delivery Project', $manager->id);
        $this->task($manager, $project, 'Blocked Past Due', $manager->id, ['status' => 'blocked', 'due_date' => now()->subDay()->toDateString()]);

        $this->actingAsOrgUser($manager)->get(route('tasks.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tasks.0.title', 'Blocked Past Due')
                ->where('tasks.0.status', 'blocked')
            );
    }

    private function project(User $user, string $code, string $name, string $ownerId): Project
    {
        $customer = Customer::create(['org_id' => $user->org_id, 'customer_code' => $code, 'company_name' => $name.' Customer', 'owner_id' => $user->id]);

        return Project::create([
            'org_id' => $user->org_id,
            'project_code' => $code,
            'name' => $name,
            'customer_id' => $customer->id,
            'owner_id' => $ownerId,
            'status' => 'planning',
            'progress_percent' => 0,
            'budget_amount' => '0.00',
            'currency' => 'THB',
        ]);
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

    private function taskPayload(array $overrides = []): array
    {
        return array_merge([
            'project_id' => '',
            'title' => 'Task Title',
            'description' => 'Task description',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => '',
            'due_date' => now()->addWeek()->toDateString(),
        ], $overrides);
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
