<?php

namespace Tests\Feature;

use App\Jobs\OperationsHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class OperationsHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_requires_real_fresh_scheduler_and_async_worker_evidence(): void
    {
        config(['operations.heartbeats_enabled' => true, 'operations.queues' => ['default', 'etax'], 'queue.default' => 'database']);
        $this->artisan('ops:health')->assertExitCode(1);
        Cache::put('ops:scheduler', now()->timestamp);
        (new OperationsHeartbeat('default'))->handle();
        $this->artisan('ops:health')->assertExitCode(1);
        (new OperationsHeartbeat('etax'))->handle();
        $this->artisan('ops:health')->assertExitCode(0);
        $this->travel(6)->minutes();
        $this->artisan('ops:health')->assertExitCode(1);
    }

    public function test_sync_queue_cannot_masquerade_as_running_worker(): void
    {
        config(['operations.heartbeats_enabled' => true, 'operations.queues' => ['default'], 'queue.default' => 'sync']);
        Cache::put('ops:scheduler', now()->timestamp);
        (new OperationsHeartbeat('default'))->handle();
        $this->artisan('ops:health')->assertExitCode(1);
    }
}
