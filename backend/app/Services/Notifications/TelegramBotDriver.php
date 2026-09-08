<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\NotificationOutbox;
use Illuminate\Support\Facades\Http;

class TelegramBotDriver implements NotificationChannelDriver
{
    public function send(NotificationOutbox $outbox, NotificationChannel $channel): void
    {
        $config = $channel->config;
        Http::timeout(10)->post('https://api.telegram.org/bot'.$config['bot_token'].'/sendMessage', [
            'chat_id' => $config['chat_id'],
            'text' => trim(($outbox->payload['title'] ?? 'ERP notification')."\n".($outbox->payload['body'] ?? '')),
        ])->throw();
    }
}
