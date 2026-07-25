<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_cannot_invite_or_open_team_page(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'member', ['dashboard.view']);
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'viewer', 'name' => 'Viewer', 'is_system' => true]);

        $this->actingAsOrgUser($user)->get('/users')->assertForbidden();
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/users/invite', [
                'name' => 'Blocked Invite',
                'email' => 'blocked@example.com',
                'branch_id' => $user->branch_id,
                'division_id' => $user->division_id,
                'department_id' => $user->department_id,
                'role_id' => $role->id,
            ])->assertForbidden();
    }

    public function test_owner_can_open_phase_one_admin_screens(): void
    {
        $user = User::factory()->create();
        $this->attachRole($user, 'owner', [
            'dashboard.view',
            'users.view',
            'roles.view',
            'settings.organization.view',
            'settings.structure.view',
            'audit.view',
        ]);

        $this->actingAsOrgUser($user)->get('/settings/organization')->assertOk();
        $this->actingAsOrgUser($user)->get('/settings/organization-structure')->assertOk();
        $this->actingAsOrgUser($user)->get('/users')->assertOk();
        $this->actingAsOrgUser($user)->get('/roles')->assertOk();
        $this->actingAsOrgUser($user)->get('/audit-logs')->assertOk();
    }

    public function test_invite_user_creates_one_time_token_with_72_hour_ttl(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['users.create']);
        $memberRole = Role::create([
            'org_id' => $owner->org_id,
            'code' => 'member',
            'name' => 'Member',
            'is_system' => true,
        ]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/users/invite', [
                'name' => 'Invited User',
                'email' => 'invite@example.com',
                'position' => 'Staff',
                'person_id' => '1234567890123',
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'role_id' => $memberRole->id,
            ])->assertRedirect();

        $invited = User::where('email', 'invite@example.com')->firstOrFail();
        $this->assertSame('invited', $invited->status);
        $this->assertNotNull($invited->invite_token_hash);
        $this->assertTrue($invited->invite_expires_at->between(now()->addHours(71), now()->addHours(73)));
        $this->assertTrue(AuditLog::where('action', 'user.invite')->where('entity_id', $invited->id)->exists());
    }

    public function test_accept_invite_activates_user_and_rejects_reuse(): void
    {
        $owner = User::factory()->create();
        $role = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);
        $token = Str::random(48);
        $invited = User::factory()->invited()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
            'email' => 'accept@example.com',
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->addHours(72),
        ]);
        $invited->roles()->attach($role->id);

        $this->patch(route('invites.accept.update', ['user' => $invited->id, 'token' => $token]), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($invited->refresh());
        $this->assertSame('active', $invited->status);
        $this->assertNull($invited->invite_token_hash);
        $this->assertTrue(AuditLog::where('action', 'user.accept_invite')->where('entity_id', $invited->id)->exists());

        auth()->logout();
        $this->get(route('invites.accept', ['user' => $invited->id, 'token' => $token]))->assertNotFound();
    }

    public function test_invited_user_cannot_login_until_accepting_invite(): void
    {
        $user = User::factory()->invited()->create([
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_invite_expires_and_cannot_be_used(): void
    {
        $owner = User::factory()->create();
        $token = Str::random(48);
        $invited = User::factory()->invited()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
            'invite_token_hash' => hash('sha256', $token),
            'invite_expires_at' => now()->subMinute(),
        ]);

        $this->get(route('invites.accept', ['user' => $invited->id, 'token' => $token]))->assertNotFound();
    }

    public function test_invite_rejects_invalid_hierarchy_chain(): void
    {
        $owner = User::factory()->create();
        $role = $this->attachRole($owner, 'owner', ['users.create']);
        $otherBranch = Branch::create(['org_id' => $owner->org_id, 'code' => '000002', 'name' => 'Branch 2']);
        $otherDivision = Division::create(['org_id' => $owner->org_id, 'branch_id' => $otherBranch->id, 'code' => '000002', 'name' => 'Division 2']);
        $otherDepartment = Department::create(['org_id' => $owner->org_id, 'branch_id' => $otherBranch->id, 'division_id' => $otherDivision->id, 'code' => '000002', 'name' => 'Department 2']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post('/users/invite', [
                'name' => 'Bad Chain',
                'email' => 'bad@example.com',
                'branch_id' => $owner->branch_id,
                'division_id' => $otherDivision->id,
                'department_id' => $otherDepartment->id,
                'role_id' => $role->id,
            ])->assertStatus(422);
    }

    public function test_user_routes_enforce_org_isolation(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->attachRole($actor, 'admin', ['users.disable']);

        $this->actingAsOrgUser($actor)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.disable', $target))->assertNotFound();
    }

    public function test_sensitive_user_actions_require_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $role = $this->attachRole($owner, 'owner', ['users.create', 'users.disable', 'roles.update']);
        $target = User::factory()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
        ]);

        $this->actingAsOrgUser($owner)->post('/users/invite', [
            'name' => 'No Confirm',
            'email' => 'no-confirm@example.com',
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
            'role_id' => $role->id,
        ])->assertRedirect(route('password.confirm', absolute: false));

        $this->actingAsOrgUser($owner)->patch(route('users.disable', $target))
            ->assertRedirect(route('password.confirm', absolute: false));

        $this->actingAsOrgUser($owner)->patch(route('users.role.update', $target), ['role_id' => $role->id])
            ->assertRedirect(route('password.confirm', absolute: false));
    }

    public function test_person_id_is_masked_without_specific_permission(): void
    {
        $viewer = User::factory()->create(['name' => 'A Viewer']);
        $this->attachRole($viewer, 'admin', ['users.view']);
        $target = User::factory()->create([
            'org_id' => $viewer->org_id,
            'branch_id' => $viewer->branch_id,
            'division_id' => $viewer->division_id,
            'department_id' => $viewer->department_id,
            'name' => 'B Target',
            'person_id' => '1234567890123',
        ]);

        $this->actingAsOrgUser($viewer)->get('/users')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Users')
                ->where('users.1.id', $target->id)
                ->where('users.1.person_id', '1-2345-xxxxx-xx-3')
            );
    }

    public function test_organization_settings_update_writes_audit_log(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['settings.organization.update']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('settings.organization.update'), [
                'name' => 'Updated Org',
                'legal_name' => 'Updated Org Co., Ltd.',
                'tax_id' => '0105566000000',
                'email' => 'info@example.com',
                'phone' => '021234567',
                'address' => 'Bangkok',
            ])->assertRedirect();

        $this->assertSame('Updated Org', $owner->organization->refresh()->name);
        $this->assertTrue(AuditLog::where('action', 'organization.update')->where('entity_id', $owner->org_id)->exists());
    }

    public function test_user_structure_update_writes_hierarchy_change_audit_log(): void
    {
        $actor = User::factory()->create();
        $this->attachRole($actor, 'admin', ['users.update']);
        $target = User::factory()->create([
            'org_id' => $actor->org_id,
            'branch_id' => $actor->branch_id,
            'division_id' => $actor->division_id,
            'department_id' => $actor->department_id,
        ]);
        $branch = Branch::create(['org_id' => $actor->org_id, 'code' => '000003', 'name' => 'Branch 3']);
        $division = Division::create(['org_id' => $actor->org_id, 'branch_id' => $branch->id, 'code' => '000003', 'name' => 'Division 3']);
        $department = Department::create(['org_id' => $actor->org_id, 'branch_id' => $branch->id, 'division_id' => $division->id, 'code' => '000003', 'name' => 'Department 3']);

        $this->actingAsOrgUser($actor)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.structure.update', $target), [
                'branch_id' => $branch->id,
                'division_id' => $division->id,
                'department_id' => $department->id,
            ])->assertRedirect();

        $this->assertSame($department->id, $target->refresh()->department_id);
        $this->assertTrue(AuditLog::where('action', 'user.hierarchy_change')->where('entity_id', $target->id)->exists());
    }

    public function test_role_change_writes_audit_log(): void
    {
        $actor = User::factory()->create();
        $this->attachRole($actor, 'owner', ['roles.update']);
        $memberRole = Role::create(['org_id' => $actor->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);
        $target = User::factory()->create([
            'org_id' => $actor->org_id,
            'branch_id' => $actor->branch_id,
            'division_id' => $actor->division_id,
            'department_id' => $actor->department_id,
        ]);

        $this->actingAsOrgUser($actor)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.role.update', $target), ['role_id' => $memberRole->id])
            ->assertRedirect();

        $this->assertTrue(AuditLog::where('action', 'user.role_change')->where('entity_id', $target->id)->exists());
    }

    public function test_last_owner_cannot_be_disabled_or_lowered(): void
    {
        $owner = User::factory()->create();
        $ownerRole = $this->attachRole($owner, 'owner', ['users.disable', 'roles.update']);
        $memberRole = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);
        $other = User::factory()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
        ]);
        $this->attachRole($other, 'admin', ['users.disable']);

        $this->actingAsOrgUser($other)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.disable', $owner))->assertStatus(422);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.role.update', $owner), ['role_id' => $memberRole->id])->assertStatus(422);

        $this->assertTrue($ownerRole->exists);
    }

    public function test_audit_log_page_includes_actor_and_diff_details(): void
    {
        $actor = User::factory()->create();
        $this->attachRole($actor, 'auditor', ['audit.view']);

        AuditLog::create([
            'org_id' => $actor->org_id,
            'actor_user_id' => $actor->id,
            'action' => 'user.role_change',
            'entity_type' => 'user',
            'entity_id' => $actor->id,
            'before_json' => ['role_ids' => ['old-role']],
            'after_json' => ['role_ids' => ['new-role']],
        ]);

        $this->actingAsOrgUser($actor)->get('/audit-logs')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/AuditLogs')
                ->where('auditLogs.0.actor.name', $actor->name)
                ->where('auditLogs.0.actor.email', $actor->email)
                ->where('auditLogs.0.before_json.role_ids.0', 'old-role')
                ->where('auditLogs.0.after_json.role_ids.0', 'new-role')
            );
    }

    public function test_dashboard_includes_security_alerts(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['dashboard.view']);
        User::factory()->inactive()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
        ]);
        User::factory()->invited()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
            'invite_expires_at' => now()->subMinute(),
        ]);
        AuditLog::create([
            'org_id' => $owner->org_id,
            'actor_user_id' => $owner->id,
            'action' => 'user.role_change',
            'entity_type' => 'user',
            'entity_id' => $owner->id,
        ]);

        $this->actingAsOrgUser($owner)->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->where('securityAlerts.inactive_users', 1)
                ->where('securityAlerts.pending_invites', 1)
                ->where('securityAlerts.expired_invites', 1)
                ->where('securityAlerts.sensitive_audit_events_24h', 1)
                ->where('securityAlerts.total', 3)
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
