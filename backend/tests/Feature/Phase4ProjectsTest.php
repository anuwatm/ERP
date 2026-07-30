<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Deal;
use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase4ProjectsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_existing_roles_receive_project_permissions_from_migration(): void
    {
        $owner = User::factory()->create();
        $role = Role::create([
            'org_id' => $owner->org_id,
            'code' => 'project_manager',
            'name' => 'Project Manager',
            'is_system' => true,
        ]);
        $owner->roles()->attach($role->id);

        (require database_path('migrations/2026_07_29_000002_backfill_project_permissions.php'))->up();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'projects.view')->exists());
        $this->assertTrue($role->fresh()->permissions()->where('code', 'projects.create')->exists());
        $this->assertFalse($role->fresh()->permissions()->where('code', 'projects.reassign')->exists());
    }

    public function test_project_manager_can_create_manual_project(): void
    {
        $manager = User::factory()->create();
        $this->attachRole($manager, 'project_manager', ['projects.create']);
        $customer = $this->customer($manager, '000001', 'Delivery Customer');

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('projects.store'), [
                'name' => 'ERP Delivery',
                'customer_id' => $customer->id,
                'deal_id' => '',
                'owner_id' => '',
                'status' => 'planning',
                'start_date' => '2026-08-01',
                'due_date' => '2026-08-31',
                'progress_percent' => 10,
                'budget_amount' => '150000.00',
                'currency' => 'THB',
                'note' => 'Manual project',
            ])->assertRedirect();

        $project = Project::where('org_id', $manager->org_id)->firstOrFail();
        $this->assertSame('000001', $project->project_code);
        $this->assertSame($manager->id, $project->owner_id);
        $this->assertSame('10', (string) $project->progress_percent);
        $this->assertTrue(AuditLog::where('action', 'project.create')->where('entity_id', $project->id)->exists());
    }

    public function test_can_create_project_from_won_deal_once_only(): void
    {
        $manager = User::factory()->create();
        $this->attachRole($manager, 'project_manager', ['projects.create', 'projects.view']);
        $customer = $this->customer($manager, '000001', 'Won Customer');
        $deal = $this->deal($manager, $customer, 'Won Deal', 'won');

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.projects.store', $deal))
            ->assertRedirect(route('projects.index'));

        $project = Project::where('deal_id', $deal->id)->firstOrFail();
        $this->assertSame('Won Deal', $project->name);
        $this->assertSame('project.create_from_deal', AuditLog::where('entity_id', $project->id)->firstOrFail()->action);

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.projects.store', $deal))
            ->assertStatus(422);
    }

    public function test_non_won_deal_cannot_create_project(): void
    {
        $manager = User::factory()->create();
        $this->attachRole($manager, 'project_manager', ['projects.create']);
        $customer = $this->customer($manager, '000001', 'Proposal Customer');
        $deal = $this->deal($manager, $customer, 'Proposal Deal', 'proposal');

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('deals.projects.store', $deal))
            ->assertStatus(422);

        $this->assertSame(0, Project::count());
    }

    public function test_project_manager_sees_only_owned_projects_but_admin_sees_all(): void
    {
        $manager = User::factory()->create();
        $other = User::factory()->create(['org_id' => $manager->org_id]);
        $admin = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($manager, 'project_manager', ['projects.view']);
        $this->attachRole($admin, 'admin', ['projects.view']);

        $customer = $this->customer($manager, '000001', 'Visible Customer');
        $this->project($manager, $customer, '000001', 'Owned Project', $manager->id);
        $this->project($manager, $customer, '000002', 'Hidden Project', $other->id);

        $this->actingAsOrgUser($manager)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Projects')
                ->has('projects', 1)
                ->where('projects.0.name', 'Owned Project')
            );

        $this->actingAsOrgUser($admin)->get(route('projects.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Delivery/Projects')
                ->has('projects', 2)
            );
    }

    public function test_only_reassign_permission_can_change_project_owner(): void
    {
        $manager = User::factory()->create();
        $newOwner = User::factory()->create(['org_id' => $manager->org_id]);
        $admin = User::factory()->create(['org_id' => $manager->org_id]);
        $this->attachRole($manager, 'project_manager', ['projects.update']);
        $this->attachRole($admin, 'admin', ['projects.update', 'projects.reassign']);
        $customer = $this->customer($manager, '000001', 'Reassign Customer');
        $project = $this->project($manager, $customer, '000001', 'Reassign Project', $manager->id);

        $payload = [
            'name' => 'Reassign Project',
            'customer_id' => $customer->id,
            'deal_id' => '',
            'owner_id' => $newOwner->id,
            'status' => 'active',
            'start_date' => '',
            'due_date' => '',
            'progress_percent' => 25,
            'budget_amount' => '2000.00',
            'currency' => 'THB',
            'note' => '',
        ];

        $this->actingAsOrgUser($manager)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('projects.update', $project), $payload)
            ->assertRedirect();
        $this->assertSame($manager->id, $project->fresh()->owner_id);

        $this->actingAsOrgUser($admin)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('projects.update', $project), $payload)
            ->assertRedirect();
        $this->assertSame($newOwner->id, $project->fresh()->owner_id);
    }

    private function customer(User $user, string $code, string $name): Customer
    {
        return Customer::create([
            'org_id' => $user->org_id,
            'customer_code' => $code,
            'company_name' => $name,
            'owner_id' => $user->id,
        ]);
    }

    private function deal(User $user, Customer $customer, string $title, string $stage): Deal
    {
        return Deal::create([
            'org_id' => $user->org_id,
            'title' => $title,
            'customer_id' => $customer->id,
            'stage' => $stage,
            'value_amount' => '100000.00',
            'currency' => 'THB',
            'probability' => $stage === 'won' ? 100 : 60,
            'owner_id' => $user->id,
        ]);
    }

    private function project(User $user, Customer $customer, string $code, string $name, string $ownerId): Project
    {
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
