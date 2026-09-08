<?php

namespace App\Services;

use App\Mail\ErpNotificationMail;
use App\Models\InAppNotification;
use App\Models\NotificationChannel;
use App\Models\NotificationDispatch;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Models\UserChannelPreference;
use App\Services\Notifications\LineMessagingDriver;
use App\Services\Notifications\NotificationChannelDriver;
use App\Services\Notifications\SlackWebhookDriver;
use App\Services\Notifications\TelegramBotDriver;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class NotificationOutboxService
{
    private const MAX_ATTEMPTS = 5;

    public function enqueue(User $user, string $eventType, string $dedupeKey, array $payload, string $priority = 'normal'): void
    {
        foreach ($this->channelsFor($user, $eventType, $priority) as $channel) {
            try {
                NotificationOutbox::create([
                    'org_id' => $user->org_id,
                    'user_id' => $user->id,
                    'event_type' => $eventType,
                    'channel' => $channel,
                    'priority' => $priority,
                    'payload' => $payload,
                    'idempotency_key' => $dedupeKey.':'.$channel,
                    'status' => 'pending',
                    'available_at' => now(),
                ]);
            } catch (QueryException $exception) {
                if (! str_contains(strtolower($exception->getMessage()), 'unique')) {
                    throw $exception;
                }
            }
        }
    }

    public function dispatchPending(int $limit = 100): int
    {
        $sent = 0;
        $ids = NotificationOutbox::query()
            ->whereIn('status', ['pending', 'retrying'])
            ->where(fn ($query) => $query->whereNull('available_at')->orWhere('available_at', '<=', now()))
            ->orderBy('available_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            if ($this->dispatch($id)) {
                $sent++;
            }
        }

        return $sent;
    }

    public function dispatch(string $outboxId): bool
    {
        $outbox = DB::transaction(function () use ($outboxId) {
            $outbox = NotificationOutbox::whereKey($outboxId)->lockForUpdate()->first();
            if (! $outbox || ! in_array($outbox->status, ['pending', 'retrying'], true) || ($outbox->available_at && $outbox->available_at->isFuture())) {
                return null;
            }

            $outbox->increment('attempt_count');
            $outbox->update(['status' => 'processing', 'last_error' => null]);

            return $outbox->fresh(['user']);
        });

        if (! $outbox) {
            return false;
        }

        try {
            $this->deliver($outbox);
            NotificationDispatch::create([
                'notification_outbox_id' => $outbox->id,
                'channel' => $outbox->channel,
                'attempt_no' => $outbox->attempt_count,
                'status' => 'sent',
                'dispatched_at' => now(),
            ]);
            $outbox->update(['status' => 'sent', 'sent_at' => now()]);

            return true;
        } catch (\Throwable) {
            $terminal = $outbox->attempt_count >= self::MAX_ATTEMPTS;
            NotificationDispatch::create([
                'notification_outbox_id' => $outbox->id,
                'channel' => $outbox->channel,
                'attempt_no' => $outbox->attempt_count,
                'status' => $terminal ? 'failed' : 'retrying',
                'error_message' => 'Delivery failed. See channel configuration and application logs.',
                'dispatched_at' => now(),
            ]);
            $outbox->update([
                'status' => $terminal ? 'failed' : 'retrying',
                'failed_at' => $terminal ? now() : null,
                'available_at' => $terminal ? null : now()->addSeconds(2 ** $outbox->attempt_count * 60),
                'last_error' => 'Delivery failed. See channel configuration and application logs.',
            ]);

            return false;
        }
    }

    public function sendTest(NotificationChannel $channel, User $user): void
    {
        $outbox = new NotificationOutbox([
            'org_id' => $channel->org_id,
            'user_id' => $user->id,
            'event_type' => 'notification.channel_test',
            'channel' => $channel->channel,
            'payload' => ['title' => 'ERP channel test', 'body' => 'Channel configuration is valid.'],
        ]);
        $this->driver($channel->channel)->send($outbox, $channel);
    }

    private function channelsFor(User $user, string $eventType, string $priority): array
    {
        $channels = [];
        $legacy = $user->notificationPreferences()->where('type', $eventType)->first();
        if ($legacy?->in_app_enabled !== false) {
            $channels[] = 'in_app';
        }
        if ($legacy?->email_enabled !== false) {
            $channels[] = 'email';
        }

        $preferences = UserChannelPreference::where('user_id', $user->id)->where('enabled', true)->get()->keyBy('channel');
        foreach (NotificationChannel::where('org_id', $user->org_id)->where('is_enabled', true)->pluck('channel') as $channel) {
            $preference = $preferences->get($channel);
            if ($preference && ! $preference->urgent_only && ! $this->isQuiet($preference, $user)) {
                $channels[] = $channel;
            }
            if ($preference && $preference->urgent_only && $priority === 'urgent') {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    private function isQuiet(UserChannelPreference $preference, User $user): bool
    {
        if (! $preference->quiet_hours_start || ! $preference->quiet_hours_end) {
            return false;
        }
        $now = Carbon::now($user->organization->timezone ?: config('app.timezone'))->format('H:i:s');
        $start = $preference->quiet_hours_start;
        $end = $preference->quiet_hours_end;

        return $start < $end ? $now >= $start && $now < $end : $now >= $start || $now < $end;
    }

    private function deliver(NotificationOutbox $outbox): void
    {
        if ($outbox->channel === 'in_app') {
            InAppNotification::create([
                'org_id' => $outbox->org_id,
                'user_id' => $outbox->user_id,
                'type' => $outbox->event_type,
                'title' => $outbox->payload['title'],
                'body' => $outbox->payload['body'] ?? null,
                'url' => $outbox->payload['url'] ?? null,
            ]);

            return;
        }
        if ($outbox->channel === 'email') {
            Mail::to($outbox->user->email)->queue(new ErpNotificationMail($outbox->payload['title'], $outbox->payload['body'] ?? '', $outbox->payload['url'] ?? null));

            return;
        }

        $channel = NotificationChannel::where('org_id', $outbox->org_id)->where('channel', $outbox->channel)->where('is_enabled', true)->firstOrFail();
        $this->driver($outbox->channel)->send($outbox, $channel);
    }

    private function driver(string $channel): NotificationChannelDriver
    {
        return match ($channel) {
            'line' => app(LineMessagingDriver::class),
            'slack' => app(SlackWebhookDriver::class),
            'telegram' => app(TelegramBotDriver::class),
            default => throw new \InvalidArgumentException('Unsupported notification channel.'),
        };
    }
}
