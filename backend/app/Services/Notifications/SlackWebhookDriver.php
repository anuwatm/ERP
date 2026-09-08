<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\NotificationOutbox;
use Illuminate\Support\Facades\Http;

class SlackWebhookDriver implements NotificationChannelDriver
{
    public function send(NotificationOutbox $outbox, NotificationChannel $channel): void
    {
        $config = $channel->config;
        Http::timeout(10)->post($config['webhook_url'], [
            'text' => $this->message($outbox),
        ])->throw();
    }

    private function message(NotificationOutbox $outbox): string
    {
        return trim(($outbox->payload['title'] ?? 'ERP notification')."\n".($outbox->payload['body'] ?? ''));
    }
}
