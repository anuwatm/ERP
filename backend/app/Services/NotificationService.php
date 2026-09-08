<?php

namespace App\Services;

use App\Models\NotificationEvent;
use App\Models\User;

class NotificationService
{
    public function notify(User $user, string $type, string $dedupeKey, string $title, string $body, ?string $url = null): bool
    {
        $created = NotificationEvent::firstOrCreate([
            'org_id' => $user->org_id,
            'user_id' => $user->id,
            'dedupe_key' => $dedupeKey,
        ]);

        if (! $created->wasRecentlyCreated) {
            return false;
        }

        $outbox = app(NotificationOutboxService::class);
        $outbox->enqueue($user, $type, $dedupeKey, [
            'title' => $title,
            'body' => $body,
            'url' => $url,
        ]);
        if (config('queue.default') === 'sync') {
            $outbox->dispatchPending();
        }

        return true;
    }
}
