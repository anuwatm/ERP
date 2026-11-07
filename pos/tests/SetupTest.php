<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Hash;
use Pos\Models\Staff;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'local-test-token-with-at-least-32-bytes';

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_setup_is_gated_and_can_only_be_completed_once(): void
    {
        $url = 'http://localhost/setup';
        config(['pos.setup_token' => null]);
        $this->get($url)->assertNotFound();
        config(['pos.setup_token' => self::TOKEN]);
        $this->get('http://example.test/setup')->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10'])->get($url)->assertNotFound();
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1']);
        app()->instance('env', 'production');
        $this->get($url)->assertNotFound();
        app()->instance('env', 'testing');
        $this->get($url)->assertOk()->assertSee('POS Setup Wizard')->assertDontSee(self::TOKEN);

        $input = [
            'setup_token' => self::TOKEN, 'branch_code' => 'B01', 'branch_name' => 'Test POS',
            'business_type' => 'pharmacy', 'terminal_code' => 'T01', 'name' => 'Test Manager',
            'email' => 'manager@example.test', 'password' => 'test-password-123',
            'password_confirmation' => 'test-password-123', 'confirm_install' => 'INSTALL',
            'pharmacist_qualified' => true,
        ];
        $this->postJson($url, ['setup_token' => 'wrong'] + $input)->assertForbidden();
        $this->postJson($url, ['confirm_install' => 'wrong'] + $input)->assertUnprocessable();
        $this->assertDatabaseCount('pos_branches', 0);
        $this->post($url, $input)->assertOk()->assertDontSee(self::TOKEN);
        $manager = Staff::firstOrFail();
        $this->assertTrue(Hash::check($input['password'], $manager->password));
        $this->assertFalse($manager->pharmacist_qualified);
        $this->assertDatabaseHas('pos_branches', ['id' => $manager->branch_id, 'code' => 'B01', 'business_type' => 'pharmacy']);
        $this->assertDatabaseHas('pos_terminals', ['branch_id' => $manager->branch_id, 'code' => 'T01']);
        $this->assertDatabaseHas('pos_audit', ['action' => 'setup.install', 'actor_id' => $manager->id]);
        $this->get($url)->assertConflict();
        $this->post($url, $input)->assertConflict();
        $this->assertDatabaseCount('pos_staff', 1);
        $this->postJson('http://localhost/login', ['email' => $input['email'], 'password' => $input['password']])->assertOk();
    }
}
