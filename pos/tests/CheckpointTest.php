<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pos\Services\CheckpointService;
use Pos\Services\OutboxService;
use Pos\Services\RecoveryFenceService;
use Pos\Support\CanonicalJson;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class CheckpointTest extends TestCase
{
    use RefreshDatabase;

    private string $key;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (isset($this->key)) {
            foreach ([$this->key, $this->key.'.anchor'] as $path) {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
    }

    public function test_signed_checkpoint_requires_accepted_evidence_before_archive_and_preserves_replay(): void
    {
        Storage::fake('pos_archives');
        $branch = (string) Str::uuid();
        $instance = (string) Str::uuid();
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'C', 'name' => 'Checkpoint', 'business_type' => 'retail']);
        $this->key = tempnam(sys_get_temp_dir(), 'pos-checkpoint-');
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $cnf = dirname(PHP_BINARY).'/extras/ssl/openssl.cnf';
        if (is_file($cnf)) {
            $options['config'] = $cnf;
        }
        $key = openssl_pkey_new($options);
        openssl_pkey_export($key, $pem, null, $options);
        file_put_contents($this->key, $pem);
        config(['pos.local_checkpoints_enabled' => true, 'pos.recovery_generation' => 'synthetic-generation-one', 'pos.instance_id' => $instance, 'pos.signing_key_id' => 'checkpoint-key', 'pos.signing_key_path' => $this->key, 'pos.connector_branch_ids' => [$branch]]);
        config(['pos.local_recovery_fencing_enabled' => true, 'pos.recovery_anchor_path' => $this->key.'.anchor']);
        app(RecoveryFenceService::class)->initialize();
        $event = DB::transaction(fn () => app(OutboxService::class)->append('sale.completed', (string) Str::uuid(), ['branch_id' => $branch, 'occurred_at' => now()->toIso8601String()]));
        $service = app(CheckpointService::class);
        $cp = $service->create($branch, 0);
        $unsigned = $cp;
        $signature = base64_decode($unsigned['signature']);
        unset($unsigned['signature']);
        $this->assertSame(1, openssl_verify(CanonicalJson::encode($unsigned), $signature, openssl_pkey_get_details($key)['key'], OPENSSL_ALGO_SHA256));
        try {
            $service->acknowledge($cp['checkpoint_id'], $cp['digest']);
            $this->fail('Pending event acknowledged.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        DB::table('pos_outbox')->where('event_id', $event)->update(['result_status' => 'accepted', 'result' => json_encode(['status' => 'accepted', 'revision' => 1]), 'created_at' => now()->subDays(91)]);
        $service->acknowledge($cp['checkpoint_id'], $cp['digest']);
        $this->assertSame(1, $service->archive($cp['checkpoint_id']));
        $this->assertSame(0, $service->archive($cp['checkpoint_id']));
        $this->assertDatabaseCount('pos_outbox', 0);
        $this->assertDatabaseCount('pos_outbox_archive', 1);
        $this->assertSame('', DB::table('pos_outbox_archive')->value('envelope'));
        Storage::disk('pos_archives')->assertExists($event.'.gz.enc');
        $replay = $service->create($branch, 0);
        $this->assertSame($cp['digest'], $replay['digest']);
        $service->restore($event);
        $this->assertDatabaseCount('pos_outbox_archive', 0);
        $this->assertDatabaseCount('pos_outbox', 1);
        $this->assertSame(1, $service->archive($cp['checkpoint_id']));
        $next = DB::transaction(fn () => app(OutboxService::class)->append('sale.completed', (string) Str::uuid(), ['branch_id' => $branch, 'occurred_at' => now()->toIso8601String()]));
        $service->hold($replay['checkpoint_id'], 'Synthetic investigation hold');
        $service->restore($event);
        $overlap = $service->create($branch, 0);
        // A fresh checkpoint must not bypass a hold placed through an older checkpoint.
        DB::table('pos_outbox')->where('event_id', $next)->update(['result_status' => 'accepted', 'result' => json_encode(['status' => 'accepted', 'revision' => 1])]);
        $service->acknowledge($overlap['checkpoint_id'], $overlap['digest']);
        $this->assertSame(0, $service->archive($overlap['checkpoint_id']));
        $this->assertNotNull(DB::table('pos_outbox')->where('event_id', $event)->value('hold_at'));
        $this->assertGreaterThan($cp['through_sequence'], DB::table('pos_outbox')->where('event_id', $next)->value('sequence'));
        $preserved = (array) DB::table('pos_outbox')->where('event_id', $next)->firstOrFail();
        DB::table('pos_outbox')->where('event_id', $next)->delete();
        try {
            DB::transaction(fn () => app(OutboxService::class)->append('sale.completed', (string) Str::uuid(), ['branch_id' => $branch, 'occurred_at' => now()->toIso8601String()]));
            $this->fail('Restored POS sold with a reused source sequence.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(423, $e->getStatusCode());
        }
        DB::table('pos_outbox')->insert($preserved);
        try {
            DB::transaction(function () use ($branch) {
                app(OutboxService::class)->append('sale.completed', (string) Str::uuid(), ['branch_id' => $branch, 'occurred_at' => now()->toIso8601String()]);
                throw new \RuntimeException('Synthetic rollback after signing');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic rollback after signing', $e->getMessage());
        }
        DB::transaction(fn () => app(RecoveryFenceService::class)->assertCurrent());
        try {
            $service->create($branch, 999);
            $this->fail('Restored watermark accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }
}
