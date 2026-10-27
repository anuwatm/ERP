<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class Phase28TaxSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'tax-config', 'name' => 'Tax configuration']);
        foreach (['pos.manage', 'pos.view'] as $code) {
            $permission = Permission::where('code', $code)->firstOrFail();
            $role->permissions()->attach($permission->id);
        }
        $user->roles()->attach($role->id);
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()]);
        $this->withoutVite();

        return $user;
    }

    public function test_owner_can_be_selected_changed_and_cleared_without_enabling_submission(): void
    {
        $user = $this->manager();
        $owner = User::factory()->create(['org_id' => $user->org_id]);
        foreach ([$owner->id, $user->id, null] as $id) {
            $this->post(route('pos.tax-settings'), ['signing_owner_id' => $id, 'reason' => 'Synthetic owner configuration'])->assertRedirect();
            $this->assertDatabaseHas('pos_tax_settings', ['org_id' => $user->org_id, 'signing_owner_id' => $id, 'updated_by' => $user->id]);
        }
        $this->assertSame(3, DB::table('audit_logs')->where('action', 'pos.tax.signing_owner_changed')->count());
        $this->assertDatabaseCount('pos_sync_inbox', 0);
        $this->get(route('pos.index'))->assertOk()->assertInertia(fn ($page) => $page->component('Admin/Pos')->where('canManage', true)->where('taxSigningOwnerId', null)->has('taxSigningOwners', 2));
    }

    public function test_foreign_inactive_and_missing_owners_are_rejected(): void
    {
        $user = $this->manager();
        $foreign = User::factory()->create();
        $inactive = User::factory()->create(['org_id' => $user->org_id, 'status' => 'inactive']);
        foreach ([$foreign->id, $inactive->id] as $id) {
            $this->postJson(route('pos.tax-settings'), ['signing_owner_id' => $id, 'reason' => 'Synthetic rejection'])->assertNotFound();
        }
        $this->postJson(route('pos.tax-settings'), ['reason' => 'Missing owner'])->assertUnprocessable();
        $this->assertDatabaseCount('pos_tax_settings', 0);
        $this->assertSame(0, DB::table('audit_logs')->where('action', 'pos.tax.signing_owner_changed')->count());
    }

    public function test_configuration_requires_manage_permission_and_recent_password_confirmation(): void
    {
        $user = User::factory()->create();
        $this->actingAsOrgUser($user)->postJson(route('pos.tax-settings'), ['signing_owner_id' => $user->id, 'reason' => 'Denied'])->assertForbidden();
        $manager = $this->manager();
        $this->withSession(['auth.password_confirmed_at' => 0])->postJson(route('pos.tax-settings'), ['signing_owner_id' => $manager->id, 'reason' => 'Expired confirmation'])->assertStatus(423);
        $this->assertDatabaseCount('pos_tax_settings', 0);
    }
}
