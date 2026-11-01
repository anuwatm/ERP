<?php

use App\Jobs\OperationsHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    Cache::put('ops:scheduler', now()->timestamp, now()->addHour());
    foreach (config('operations.queues') as $queue) {
        OperationsHeartbeat::dispatch($queue);
    }
})->name('operations.heartbeat')->everyMinute()->withoutOverlapping()->when(fn () => config('operations.heartbeats_enabled') === true);

Artisan::command('ops:health', function () {
    $checks = [];
    try {
        DB::select('SELECT 1');
        $checks['database'] = true;
        $ageOk = fn ($timestamp, $limit) => is_numeric($timestamp) && now()->timestamp >= (int) $timestamp && now()->timestamp - (int) $timestamp <= $limit;
        $checks['heartbeats_enabled'] = config('operations.heartbeats_enabled') === true;
        $checks['scheduler'] = $ageOk(Cache::get('ops:scheduler'), config('operations.scheduler_max_age'));
        $checks['async_queue'] = ! in_array(config('queue.default'), ['sync', 'null'], true);
        $checks['queues_configured'] = count(config('operations.queues')) > 0;
        foreach (config('operations.queues') as $queue) {
            $checks['worker:'.$queue] = $ageOk(Cache::get('ops:worker:'.$queue), config('operations.worker_max_age'));
        }
        if (app()->environment('production')) {
            $checks['debug_disabled'] = config('app.debug') === false;
            $checks['https_cookie'] = config('session.secure') === true && config('session.http_only') === true;
            $checks['shared_cache'] = ! in_array(config('cache.default'), ['array', 'null'], true);
        }
    } catch (Throwable $exception) {
        $checks['dependency_access'] = false;
        // Deliberately omit messages/bindings/credentials from health output.
    }
    $ok = ! in_array(false, $checks, true);
    $this->line(json_encode(['status' => $ok ? 'ok' : 'attention', 'checks' => $checks], JSON_THROW_ON_ERROR));

    return $ok ? 0 : 1;
})->purpose('Read-only DB/cache and scheduler/queue heartbeat checks; exit 1 on missing or stale evidence.');
