<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;

class ConfigurationTest extends TestCase
{
    use RefreshDatabase;

    private Staff $manager;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        $branch = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'B1', 'name' => 'Local shop', 'business_type' => 'retail']);
        $this->manager = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Manager', 'email' => 'manager@test.example', 'password' => 'long-test-password', 'role' => 'manager', 'active' => true]);
        $this->actingAs($this->manager)->withSession(['pos_login_at' => time(), 'pos_last_seen' => time()]);
    }

    public function test_manager_can_configure_only_own_branch_and_type_locks_after_terminal(): void
    {
        $other = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $other, 'code' => 'B2', 'name' => 'Other secret branch', 'business_type' => 'retail']);
        $this->get('/config')->assertOk()->assertSee('Local shop')->assertDontSee('Other secret branch');
        $this->post('/config/branch', ['branch_id' => $other, 'name' => 'Restaurant', 'business_type' => 'restaurant', 'manager_password' => 'long-test-password'])->assertRedirect('/config');
        $this->assertDatabaseHas('pos_branches', ['id' => $other, 'name' => 'Other secret branch']);
        $this->post('/config/terminal', ['code' => 'T01', 'branch_id' => $other, 'manager_password' => 'long-test-password'])->assertRedirect('/config');
        $this->assertDatabaseHas('pos_terminals', ['branch_id' => $this->manager->branch_id, 'code' => 'T01']);
        $this->postJson('/config/branch', ['name' => 'Changed', 'business_type' => 'pharmacy', 'manager_password' => 'long-test-password'])->assertStatus(409);
        $this->assertDatabaseHas('pos_branches', ['id' => $this->manager->branch_id, 'business_type' => 'restaurant']);
        $this->assertDatabaseCount('pos_audit', 2);
    }

    public function test_configuration_rejects_cashier_wrong_password_and_nonlocal_access(): void
    {
        $this->post('/config/terminal', ['code' => 'T01', 'manager_password' => 'wrong'])->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get('/config')->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        $this->manager->forceFill(['role' => 'cashier'])->save();
        $this->get('/config')->assertForbidden();
        $this->post('/config/terminal', ['code' => 'T01', 'manager_password' => 'long-test-password'])->assertForbidden();
        $this->assertDatabaseCount('pos_terminals', 0);
    }

    public function test_staff_password_is_hashed_and_qualification_cannot_be_self_granted(): void
    {
        $this->post('/config/staff', ['name' => 'New cashier', 'email' => 'new@test.example', 'role' => 'cashier', 'password' => 'new-long-password', 'password_confirmation' => 'new-long-password', 'pharmacist_qualified' => true, 'manager_password' => 'long-test-password'])->assertRedirect('/config');
        $staff = Staff::where('email', 'new@test.example')->firstOrFail();
        $this->assertNotSame('new-long-password', $staff->password);
        $this->assertFalse($staff->pharmacist_qualified);
        $this->assertSame($this->manager->branch_id, $staff->branch_id);
        $this->get('/config')->assertDontSee($staff->password);
        $this->assertStringNotContainsString('new-long-password', DB::table('pos_audit')->value('metadata'));
    }

    public function test_configuration_is_blocked_in_production_and_without_login(): void
    {
        app()->instance('env', 'production');
        $this->get('/config')->assertForbidden();
        app()->instance('env', 'testing');
        auth()->logout();
        $this->get('/config')->assertUnauthorized();
    }

    public function test_pharmacy_register_only_shows_available_lots_from_its_own_branch(): void
    {
        config(['pos.local_pharmacy_enabled' => true]);
        $branch = $this->manager->branch_id;
        $other = (string) Str::uuid();
        $product = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $other, 'code' => 'B2', 'name' => 'Other pharmacy', 'business_type' => 'pharmacy']);
        $today = now('Asia/Bangkok')->toDateString();
        $expired = now('Asia/Bangkok')->subDay()->toDateString();
        $lots = [];

        foreach (['available', 'expired', 'recalled', 'consumed', 'foreign'] as $kind) {
            $lots[$kind] = (string) Str::uuid();
            DB::table('pos_grants')->insert([
                'id' => (string) Str::uuid(),
                'branch_id' => $kind === 'foreign' ? $other : $branch,
                'product_id' => $product,
                'lot_id' => $lots[$kind],
                'expires_at' => $kind === 'expired' ? $expired : $today,
                'epoch' => 1,
                'granted_quantity' => '1',
                'consumed_quantity' => $kind === 'consumed' ? '1' : '0',
                'status' => 'active',
                'payload_hash' => hash('sha256', $kind),
            ]);
        }
        DB::table('pos_lot_recalls')->insert([
            'id' => (string) Str::uuid(), 'branch_id' => $branch, 'product_id' => $product,
            'lot_id' => $lots['recalled'], 'reason' => 'Synthetic recall', 'recalled_at' => now(),
            'payload_hash' => hash('sha256', 'recall'),
        ]);

        $this->getJson('/state')->assertOk()->assertJsonMissingPath('pharmacy_lots');
        DB::table('pos_branches')->where('id', $branch)->update(['business_type' => 'pharmacy']);
        $this->getJson('/state')->assertOk()->assertJsonPath('pharmacy_lots.'.$product, [
            ['lot_id' => $lots['available'], 'expires_at' => $today],
        ]);
    }
}
