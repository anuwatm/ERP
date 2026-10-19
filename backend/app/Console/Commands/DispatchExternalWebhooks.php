<?php

namespace App\Console\Commands;

use App\Services\ExternalWebhookService;
use Illuminate\Console\Command;

class DispatchExternalWebhooks extends Command
{
    protected $signature = 'external-api:dispatch-webhooks {--limit=20}';

    protected $description = 'Dispatch bounded, verified integration webhook deliveries.';

    public function handle(ExternalWebhookService $service): int
    {
        if (! config('external_api.webhooks_enabled')) {
            $this->info('External webhooks are disabled.');

            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
        if ($limit === false) {
            return self::INVALID;
        }
        $this->info('Processed: '.$service->dispatch($limit));

        return self::SUCCESS;
    }
}
