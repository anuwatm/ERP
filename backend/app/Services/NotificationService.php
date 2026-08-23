<?php

namespace App\Services;

use App\Mail\ErpNotificationMail;
use App\Models\InAppNotification;
use App\Models\NotificationEvent;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

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

        $preference = NotificationPreference::where('user_id', $user->id)->where('type', $type)->first();

        if ($preference?->in_app_enabled !== false) {
            InAppNotification::create([
                'org_id' => $user->org_id,
                'user_id' => $user->id,
                'type' => $type,
                'title' => $title,
                'body' => $body,
                'url' => $url,
            ]);
        }

        if ($preference?->email_enabled !== false) {
            Mail::to($user->email)->queue(new ErpNotificationMail($title, $body, $url));
        }

        return true;
    }
}
