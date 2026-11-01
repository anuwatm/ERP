<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class OperationsHeartbeat implements ShouldQueue
{
    use Queueable;

    public int $timeout = 15;

    public function __construct(public string $monitoredQueue)
    {
        $this->onQueue($monitoredQueue);
    }

    public function handle(): void
    {
        Cache::put('ops:worker:'.$this->monitoredQueue, now()->timestamp, now()->addHour());
    }
}
