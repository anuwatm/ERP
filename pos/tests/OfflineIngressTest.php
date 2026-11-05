<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Models\Staff;
use Pos\Services\OfflineIngressService;
use Pos\Support\CanonicalJson;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class OfflineIngressTest extends TestCase
{
    use RefreshDatabase;

    private Staff $staff;

    private string $terminal;

    private string $device;

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
        $this->terminal = (string) Str::uuid();
        $this->device = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'OFFTEST', 'name' => 'Synthetic offline', 'business_type' => 'retail']);
        DB::table('pos_terminals')->insert(['id' => $this->terminal, 'branch_id' => $branch, 'code' => 'O1']);
        $this->staff = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $branch, 'name' => 'Synthetic staff', 'email' => 'offline@fixture.test', 'password' => 'synthetic-password', 'role' => 'cashier', 'active' => true]);
        config(['pos.instance_id' => (string) Str::uuid(), 'pos.local_browser_offline_enabled' => true]);
    }

    public function test_cash_acceptance_and_quota_release_require_manager_reauthentication(): void
    {
        $this->withSession(['pos_last_seen' => time(), 'pos_login_at' => time()]);
        $command = ['inbox_id' => (string) Str::uuid(), 'terminal_id' => $this->terminal, 'payload_hash' => str_repeat('a', 64), 'cash_received' => '10.70', 'evidence_note' => 'Checked original evidence', 'manager_password' => 'synthetic-password'];
        $this->actingAs($this->staff)->postJson('/offline/accept', $command)->assertForbidden();
        $this->staff->update(['role' => 'manager']);
        $command['manager_password'] = 'wrong-password';
        $this->actingAs($this->staff)->postJson('/offline/accept', $command)->assertForbidden();
        $this->actingAs($this->staff)->postJson('/offline/close-permit', ['permit_id' => (string) Str::uuid(), 'accepted_transactions' => [], 'unused_confirmed' => true, 'evidence_note' => 'All devices and unused quota inspected', 'manager_password' => 'wrong-password'])->assertForbidden();
        $command['manager_password'] = 'synthetic-password';
        $this->actingAs($this->staff)->postJson('/offline/accept', $command)->assertNotFound();
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_payments', 0);
    }

    public function test_review_cursor_exposes_all_pending_evidence_without_cross_branch_leak(): void
    {
        $this->withSession(['pos_last_seen' => time(), 'pos_login_at' => time()]);
        $this->register();
        $this->staff->update(['role' => 'manager']);
        $rows = [];
        for ($i = 0; $i < 102; $i++) {
            $e = $this->evidence();
            $bytes = CanonicalJson::encode($e);
            $rows[] = ['id' => (string) Str::uuid(), 'device_id' => $this->device, 'client_transaction_id' => $e['client_transaction_id'], 'branch_id' => $i === 101 ? (string) Str::uuid() : $this->staff->branch_id, 'staff_id' => $this->staff->id, 'kind' => 'sale.draft', 'client_created_at' => 'untrusted', 'server_received_at' => now()->utc(), 'payload_hash' => hash('sha256', $bytes), 'payload' => Crypt::encryptString($bytes), 'status' => 'review', 'reason_code' => 'operational_request_review', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('pos_offline_inbox')->insert($rows);
        $first = $this->actingAs($this->staff)->getJson('/offline/review')->assertOk()->assertJsonCount(100)->json();
        $last = $first[99];
        $next = $this->getJson('/offline/review?'.http_build_query(['cursor_at' => $last['server_received_at'], 'cursor_id' => $last['id']]))->assertOk()->assertJsonCount(1)->json();
        $this->assertNotContains($next[0]['id'], array_column($first, 'id'));
        $this->assertSame(2, DB::table('pos_audit')->where('action', 'offline.review.read')->count());
        $this->assertDatabaseCount('pos_orders', 0);
    }

    private function register(): array
    {
        return app(OfflineIngressService::class)->register($this->staff, ['device_id' => $this->device, 'terminal_id' => $this->terminal, 'label' => 'Synthetic browser']);
    }

    private function evidence(): array
    {
        return ['device_id' => $this->device, 'client_transaction_id' => (string) Str::uuid(), 'kind' => 'sale.draft', 'client_created_at' => 'untrusted-client-clock', 'data' => ['cash_claimed' => '20.00', 'note' => 'synthetic evidence']];
    }

    public function test_evidence_survives_replay_and_untrusted_clock_without_posting_financials(): void
    {
        $this->assertFalse($this->register()['offline_sale_authorized']);
        $this->assertSame($this->device, $this->register()['device_id']);
        $input = $this->evidence();
        $service = app(OfflineIngressService::class);
        $first = $service->receive($this->staff, $input);
        $this->travel(5)->minutes();
        $this->assertSame($first, $service->receive($this->staff, $input));
        $this->assertSame('review', $first['status']);
        $this->assertFalse($first['financial_posted']);
        $row = DB::table('pos_offline_inbox')->first();
        $this->assertSame('untrusted-client-clock', $row->client_created_at);
        $this->assertStringNotContainsString('synthetic evidence', $row->payload);
        $this->assertSame(CanonicalJson::encode($input), Crypt::decryptString($row->payload));
        $this->assertDatabaseCount('pos_offline_inbox', 1);
        $this->assertDatabaseCount('pos_orders', 0);
        $this->assertDatabaseCount('pos_payments', 0);
        $this->assertDatabaseCount('pos_outbox', 0);
    }

    public function test_conflicting_identity_preserves_both_payloads_and_first_received_time(): void
    {
        $this->register();
        $service = app(OfflineIngressService::class);
        $input = $this->evidence();
        $first = $service->receive($this->staff, $input);
        $original = DB::table('pos_offline_inbox')->first();
        $input['data']['cash_claimed'] = '30.00';
        $this->travel(3)->minutes();
        $conflict = $service->receive($this->staff, $input);
        $this->assertSame('identity_conflict', $conflict['reason_code']);
        $this->assertSame($first['server_received_at'], $conflict['server_received_at']);
        $service->receive($this->staff, $input);
        $this->assertDatabaseCount('pos_offline_conflicts', 1);
        $this->assertSame($original->payload_hash, DB::table('pos_offline_inbox')->value('payload_hash'));
        $changed = json_decode(Crypt::decryptString(DB::table('pos_offline_conflicts')->value('payload')), true);
        $this->assertSame('30.00', $changed['data']['cash_claimed']);
        $this->assertDatabaseCount('pos_orders', 0);
    }

    public function test_disabled_capability_and_inactive_staff_fail_closed(): void
    {
        config(['pos.local_browser_offline_enabled' => false]);
        try {
            $this->register();
            $this->fail('Disabled offline feature accepted device.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(503, $e->getStatusCode());
        }
        config(['pos.local_browser_offline_enabled' => true]);
        $this->staff->update(['active' => false]);
        try {
            $this->register();
            $this->fail('Inactive staff registered device.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseCount('pos_offline_devices', 0);
    }

    public function test_http_scope_auth_and_manager_policy_reauthentication(): void
    {
        $this->register();
        $session = ['pos_last_seen' => time(), 'pos_login_at' => time()];
        $other = Staff::create(['id' => (string) Str::uuid(), 'branch_id' => $this->staff->branch_id, 'name' => 'Other synthetic staff', 'email' => 'other-offline@fixture.test', 'password' => 'synthetic-password', 'role' => 'cashier', 'active' => true]);
        $this->actingAs($other)->withSession($session)->postJson('/offline/inbox', $this->evidence())->assertNotFound();
        $this->assertDatabaseCount('pos_offline_inbox', 0);
        $context = $this->actingAs($this->staff)->withSession($session)->getJson('/offline/context')->assertOk()->assertJsonPath('offline_sale_authorized', false)->assertJsonPath('policy.max_offline_transactions', 20);
        $this->assertStringContainsString('no-store', $context->headers->get('Cache-Control'));
        $policy = ['expected_version' => 0, 'permit_minutes' => 10, 'max_offline_transactions' => 5, 'max_offline_amount' => '1000.00', 'product_ids' => [], 'manager_password' => 'synthetic-password'];
        $this->postJson('/offline/policies', $policy)->assertForbidden();
        $this->staff->update(['role' => 'manager']);
        $this->actingAs($this->staff)->postJson('/offline/policies', array_merge($policy, ['manager_password' => 'incorrect-password']))->assertForbidden();
        $this->postJson('/offline/policies', $policy)->assertOk()->assertJsonPath('version', 1)->assertJsonPath('max_offline_transactions', 5);
        $this->postJson('/offline/policies', $policy)->assertConflict();
        $this->assertDatabaseCount('pos_offline_policies', 1);
    }
}
