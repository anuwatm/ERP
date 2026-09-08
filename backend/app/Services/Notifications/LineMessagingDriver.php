<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\NotificationOutbox;
use Illuminate\Support\Facades\Http;

class LineMessagingDriver implements NotificationChannelDriver
{
    public function send(NotificationOutbox $outbox, NotificationChannel $channel): void
    {
        $config = $channel->config;
        Http::timeout(10)
            ->withToken($config['access_token'])
            ->post('https://api.line.me/v2/bot/message/push', [
                'to' => $config['target_id'],
                'messages' => [[
                    'type' => 'text',
                    'text' => trim(($outbox->payload['title'] ?? 'ERP notification')."\n".($outbox->payload['body'] ?? '')),
                ]],
            ])->throw();
    }
}
