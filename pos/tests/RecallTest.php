<?php

namespace Pos\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pos\Services\EntitlementAllocator;
use Pos\Services\LotRecallService;
use Pos\Services\ProvisioningService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class RecallTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication(): Application
    {
        $app = require dirname(__DIR__).'/bootstrap/app.php';
        $this->traitsUsedByTest = class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_recall_stops_existing_quota_preserves_counters_and_cannot_be_overwritten(): void
    {
        $branch = (string) Str::uuid();
        $lot = (string) Str::uuid();
        $product = (string) Str::uuid();
        $recall = (string) Str::uuid();
        config(['pos.local_recalls_enabled' => true, 'pos.connector_branch_ids' => [$branch]]);
        DB::table('pos_branches')->insert(['id' => $branch, 'code' => 'R', 'name' => 'Synthetic recall', 'business_type' => 'pharmacy']);
        app(ProvisioningService::class)->grant((string) Str::uuid(), ['branch_id' => $branch, 'product_id' => $product, 'lot_id' => $lot, 'epoch' => 1, 'quantity' => '2.0000']);
        $input = ['branch_id' => $branch, 'product_id' => $product, 'lot_id' => $lot, 'reason' => 'Synthetic recall', 'recalled_at' => now()->toIso8601String()];
        $service = app(LotRecallService::class);
        $service->receive($recall, $input);
        $service->receive($recall, $input);
        $this->assertDatabaseCount('pos_lot_recalls', 1);
        try {
            DB::transaction(fn () => app(EntitlementAllocator::class)->take($branch, $product, '1.0000', 'Asia/Bangkok'));
            $this->fail('Recalled lot sold.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $this->assertSame(0.0, (float) DB::table('pos_grants')->value('consumed_quantity'));
        $this->assertSame(2.0, (float) DB::table('pos_grants')->value('granted_quantity'));
        $input['reason'] = 'Changed identity';
        try {
            $service->receive($recall, $input);
            $this->fail('Recall overwritten.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame('Synthetic recall', DB::table('pos_lot_recalls')->value('reason'));
    }

    public function test_foreign_scope_and_production_recalls_are_denied(): void
    {
        config(['pos.local_recalls_enabled' => true, 'pos.connector_branch_ids' => []]);
        try {
            app(LotRecallService::class)->receive((string) Str::uuid(), ['branch_id' => (string) Str::uuid(), 'product_id' => (string) Str::uuid(), 'lot_id' => (string) Str::uuid(), 'reason' => 'Synthetic', 'recalled_at' => now()->toIso8601String()]);
            $this->fail('Foreign recall accepted.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->app->detectEnvironment(fn () => 'production');
        $this->expectException(HttpException::class);
        app(LotRecallService::class)->receive((string) Str::uuid(), []);
    }
}
