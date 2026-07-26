<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\NumberSequence;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase11AdminMasterDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_branch_with_auto_generated_code(): void
    {
        $owner = User::factory()->create();
        $this->seedSequence($owner->org_id, 'branch', null, 1);
        $this->attachRole($owner, 'owner', ['settings.structure.update']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.branches.store'), [
                'name' => 'Chiang Mai',
                'phone' => '053000000',
            ])->assertRedirect();

        $branch = Branch::where('org_id', $owner->org_id)->where('name', 'Chiang Mai')->firstOrFail();
        $this->assertSame('000002', $branch->code);
        $this->assertTrue(AuditLog::where('action', 'branch.create')->where('entity_id', $branch->id)->exists());
    }

    public function test_head_office_switch_is_transactional_and_audited(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['settings.structure.update']);
        $branch = Branch::create(['org_id' => $owner->org_id, 'code' => '000002', 'name' => 'New HQ', 'status' => 'active']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('settings.branches.head-office', $branch))->assertRedirect();

        $this->assertTrue($branch->refresh()->is_head_office);
        $this->assertFalse(Branch::where('id', $owner->branch_id)->firstOrFail()->is_head_office);
        $this->assertTrue(AuditLog::where('action', 'branch.set_head_office')->where('entity_id', $branch->id)->exists());
    }

    public function test_delete_or_disable_branch_with_active_children_is_blocked(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['settings.structure.update']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('settings.branches.disable', $owner->branch_id))->assertStatus(422);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('settings.branches.destroy', $owner->branch_id))->assertStatus(422);
    }

    public function test_owner_can_create_division_and_department_with_auto_codes(): void
    {
        $owner = User::factory()->create();
        $this->seedSequence($owner->org_id, 'division', $owner->branch_id, 1);
        $this->seedSequence($owner->org_id, 'department', $owner->branch_id, 1);
        $this->attachRole($owner, 'owner', ['settings.structure.update']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.divisions.store'), [
                'branch_id' => $owner->branch_id,
                'name' => 'Operations',
            ])->assertRedirect();

        $division = Division::where('org_id', $owner->org_id)->where('name', 'Operations')->firstOrFail();
        $this->assertSame('000002', $division->code);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.departments.store'), [
                'branch_id' => $owner->branch_id,
                'division_id' => $division->id,
                'name' => 'Support',
            ])->assertRedirect();

        $department = Department::where('org_id', $owner->org_id)->where('name', 'Support')->firstOrFail();
        $this->assertSame('000002', $department->code);
    }

    public function test_invalid_department_hierarchy_is_rejected(): void
    {
        $owner = User::factory()->create();
        $this->seedSequence($owner->org_id, 'department', $owner->branch_id, 1);
        $this->attachRole($owner, 'owner', ['settings.structure.update']);
        $branch = Branch::create(['org_id' => $owner->org_id, 'code' => '000002', 'name' => 'Other', 'status' => 'active']);
        $division = Division::create(['org_id' => $owner->org_id, 'branch_id' => $branch->id, 'code' => '000002', 'name' => 'Other Division', 'status' => 'active']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.departments.store'), [
                'branch_id' => $owner->branch_id,
                'division_id' => $division->id,
                'name' => 'Bad Chain',
            ])->assertStatus(422);
    }

    public function test_owner_can_update_and_enable_user(): void
    {
        $owner = User::factory()->create();
        $ownerRole = $this->attachRole($owner, 'owner', ['users.update', 'users.disable']);
        $target = User::factory()->inactive()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $owner->branch_id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
        ]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.update', $target), [
                'name' => 'Updated User',
                'email' => $target->email,
                'position' => 'Manager',
                'phone' => '020000000',
                'person_id' => '1234567890123',
                'branch_id' => $owner->branch_id,
                'division_id' => $owner->division_id,
                'department_id' => $owner->department_id,
                'role_id' => $ownerRole->id,
            ])->assertRedirect();

        $this->assertSame('Updated User', $target->refresh()->name);
        $this->assertSame('Manager', $target->position);
        $this->assertTrue(AuditLog::where('action', 'user.update')->where('entity_id', $target->id)->exists());

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('users.enable', $target))->assertRedirect();

        $this->assertSame('active', $target->refresh()->status);
        $this->assertTrue(AuditLog::where('action', 'user.enable')->where('entity_id', $target->id)->exists());
    }

    public function test_hard_delete_user_route_does_not_exist(): void
    {
        $owner = User::factory()->create();
        $target = User::factory()->create(['org_id' => $owner->org_id]);
        $this->attachRole($owner, 'owner', ['users.disable']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->delete('/users/'.$target->id)->assertStatus(405);
    }

    public function test_owner_can_update_role_permissions_but_not_owner_role(): void
    {
        $owner = User::factory()->create();
        $this->attachRole($owner, 'owner', ['roles.view', 'roles.manage']);
        $role = Role::create(['org_id' => $owner->org_id, 'code' => 'member', 'name' => 'Member', 'is_system' => true]);
        $permission = Permission::create(['code' => 'customers.view', 'module' => 'customers', 'action' => 'view']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('roles.permissions.update', $role), ['permission_ids' => [$permission->id]])
            ->assertRedirect();

        $this->assertTrue($role->fresh()->permissions()->where('code', 'customers.view')->exists());
        $this->assertTrue(AuditLog::where('action', 'role.permission_update')->where('entity_id', $role->id)->exists());

        $ownerRole = $owner->roles()->where('code', 'owner')->firstOrFail();
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('roles.permissions.update', $ownerRole), ['permission_ids' => []])
            ->assertStatus(422);
    }

    public function test_member_cannot_update_role_permissions(): void
    {
        $member = User::factory()->create();
        $role = $this->attachRole($member, 'member', ['dashboard.view']);

        $this->actingAsOrgUser($member)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('roles.permissions.update', $role), ['permission_ids' => []])
            ->assertForbidden();
    }

    public function test_delete_branch_with_inactive_user_reference_is_blocked_but_disable_allows_it(): void
    {
        $owner = User::factory()->create();
        $this->seedSequence($owner->org_id, 'branch', null, 1);
        $this->attachRole($owner, 'owner', ['settings.structure.update']);
        $branch = Branch::create(['org_id' => $owner->org_id, 'code' => '000002', 'name' => 'Archive Branch', 'status' => 'active']);
        User::factory()->inactive()->create([
            'org_id' => $owner->org_id,
            'branch_id' => $branch->id,
            'division_id' => $owner->division_id,
            'department_id' => $owner->department_id,
        ]);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->patch(route('settings.branches.disable', $branch))->assertRedirect();

        $this->assertSame('inactive', $branch->refresh()->status);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->delete(route('settings.branches.destroy', $branch))->assertStatus(422);
    }

    public function test_creating_head_office_branch_writes_create_and_head_office_audit_logs(): void
    {
        $owner = User::factory()->create();
        $this->seedSequence($owner->org_id, 'branch', null, 1);
        $this->attachRole($owner, 'owner', ['settings.structure.update']);

        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])
            ->post(route('settings.branches.store'), [
                'name' => 'New Head Office',
                'is_head_office' => true,
            ])->assertRedirect();

        $branch = Branch::where('org_id', $owner->org_id)->where('name', 'New Head Office')->firstOrFail();

        $this->assertTrue($branch->is_head_office);
        $this->assertTrue(AuditLog::where('action', 'branch.create')->where('entity_id', $branch->id)->where('after_json->is_head_office', true)->exists());
        $this->assertTrue(AuditLog::where('action', 'branch.set_head_office')->where('entity_id', $branch->id)->exists());
    }

    private function seedSequence(string $orgId, string $docType, ?string $branchId, int $lastNumber): void
    {
        NumberSequence::create([
            'org_id' => $orgId,
            'branch_id' => $branchId,
            'branch_key' => $branchId ?? '00000000-0000-0000-0000-000000000000',
            'doc_type' => $docType,
            'year' => null,
            'year_key' => 0,
            'last_number' => $lastNumber,
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
