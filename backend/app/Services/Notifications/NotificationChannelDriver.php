<?php

namespace App\Services\Notifications;

use App\Models\NotificationChannel;
use App\Models\NotificationOutbox;

interface NotificationChannelDriver
{
    public function send(NotificationOutbox $outbox, NotificationChannel $channel): void;
}
