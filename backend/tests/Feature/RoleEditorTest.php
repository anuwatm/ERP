<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleEditorTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'role_manager', 'name' => 'Role Manager']);
        foreach (['roles.view', 'roles.manage'] as $code) {
            $role->permissions()->attach(Permission::firstOrCreate(['code' => $code], ['module' => 'roles', 'action' => 'manage']));
        }
        $user->roles()->attach($role);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);

        return $user;
    }

    public function test_create_is_scoped_audited_and_retry_safe(): void
    {
        $user = $this->manager();
        $permission = Permission::firstOrCreate(['code' => 'products.view'], ['module' => 'products', 'action' => 'view']);
        $payload = ['code' => 'warehouse_lead', 'name' => 'หัวหน้าคลัง', 'description' => 'คลังสินค้า', 'permission_ids' => [$permission->id], 'is_system' => true, 'org_id' => (string) Str::uuid()];
        $headers = ['X-Form-Submission' => (string) Str::uuid()];
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('roles.store'), $payload, $headers)->assertRedirect()->assertSessionHasNoErrors();
        }
        $role = Role::where('code', 'warehouse_lead')->sole();
        $this->assertSame($user->org_id, $role->org_id);
        $this->assertFalse($role->is_system);
        $this->assertSame([$permission->id], $role->permissions()->pluck('permissions.id')->all());
        $this->assertDatabaseCount('form_submissions', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.create', 'entity_id' => $role->id]);
    }

    public function test_edit_clears_fields_and_revokes_sessions_when_permissions_change(): void
    {
        $user = $this->manager();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'custom', 'name' => 'Before', 'description' => 'Clear me']);
        $role->permissions()->attach(Permission::first());
        $member = User::factory()->create(['org_id' => $user->org_id, 'remember_token' => 'remember-me']);
        $member->roles()->attach($role);
        DB::table('sessions')->insert(['id' => 'role-editor-session', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        $this->patch(route('roles.update', $role), ['code' => 'custom', 'name' => 'After', 'description' => '', 'permission_ids' => []])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('After', $role->fresh()->name);
        $this->assertNull($role->fresh()->description);
        $this->assertSame(0, $role->permissions()->count());
        $this->assertNull($member->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'role-editor-session']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'role.update', 'entity_id' => $role->id]);
    }

    public function test_owner_foreign_org_and_code_changes_are_blocked(): void
    {
        $user = $this->manager();
        $owner = Role::create(['org_id' => $user->org_id, 'code' => 'owner', 'name' => 'Owner', 'is_system' => true]);
        $other = User::factory()->create();
        $foreign = Role::create(['org_id' => $other->org_id, 'code' => 'foreign', 'name' => 'Foreign']);
        $payload = ['code' => 'owner', 'name' => 'Changed', 'permission_ids' => []];
        $this->patchJson(route('roles.update', $owner), $payload)->assertStatus(422);
        $this->patchJson(route('roles.update', $foreign), $payload)->assertNotFound();
        $custom = Role::create(['org_id' => $user->org_id, 'code' => 'custom', 'name' => 'Custom']);
        $this->patchJson(route('roles.update', $custom), $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->postJson(route('roles.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->assertSame('Owner', $owner->fresh()->name);
        $this->assertSame('custom', $custom->fresh()->code);
    }

    public function test_validation_rejects_duplicates_and_invalid_permissions_without_writes(): void
    {
        $this->manager();
        $payload = ['code' => 'role_manager', 'name' => 'Duplicate', 'permission_ids' => [(string) Str::uuid()]];
        $this->postJson(route('roles.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors(['code', 'permission_ids.0']);
        $this->assertDatabaseCount('roles', 1);
    }

    public function test_read_only_actor_cannot_create_or_edit(): void
    {
        $manager = $this->manager();
        $target = $manager->roles()->first();
        $viewer = User::factory()->create(['org_id' => $manager->org_id]);
        $this->actingAsOrgUser($viewer)->withSession(['auth.password_confirmed_at' => time()]);
        $this->postJson(route('roles.store'), ['code' => 'custom', 'name' => 'Custom', 'permission_ids' => []])->assertForbidden();
        $this->patchJson(route('roles.update', $target), ['code' => $target->code, 'name' => 'Changed', 'permission_ids' => []])->assertForbidden();
    }
}
