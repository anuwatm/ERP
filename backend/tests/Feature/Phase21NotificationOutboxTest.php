<?php

namespace Tests\Feature;

use App\Models\NotificationChannel;
use App\Models\NotificationOutbox;
use App\Models\User;
use App\Models\UserChannelPreference;
use App\Services\NotificationOutboxService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class Phase21NotificationOutboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_dedupes_delivery_keys_and_encrypts_channel_config(): void
    {
        $user = User::factory()->create();
        $channel = NotificationChannel::create([
            'org_id' => $user->org_id,
            'channel' => 'slack',
            'name' => 'Finance Slack',
            'config' => ['webhook_url' => 'https://hooks.slack.test/services/secret-value'],
            'is_enabled' => true,
            'created_by' => $user->id,
        ]);
        UserChannelPreference::create([
            'org_id' => $user->org_id,
            'user_id' => $user->id,
            'channel' => 'slack',
            'enabled' => true,
            'urgent_only' => false,
        ]);
        Http::fake(['hooks.slack.test/*' => Http::response('', 200)]);

        $service = app(NotificationService::class);
        $this->assertTrue($service->notify($user, 'test.event', 'outbox-key', 'Alert', 'Body'));
        $this->assertFalse($service->notify($user, 'test.event', 'outbox-key', 'Alert', 'Body'));

        $this->assertDatabaseCount('notification_outbox', 3);
        $this->assertSame('sent', NotificationOutbox::where('channel', 'slack')->value('status'));
        $rawConfig = \DB::table('notification_channels')->where('id', $channel->id)->value('config');
        $this->assertStringNotContainsString('secret-value', $rawConfig);
    }

    public function test_failed_external_delivery_retries_then_moves_to_dead_letter_state(): void
    {
        $user = User::factory()->create();
        NotificationChannel::create([
            'org_id' => $user->org_id,
            'channel' => 'slack',
            'name' => 'Failing Slack',
            'config' => ['webhook_url' => 'https://hooks.slack.test/fail'],
            'is_enabled' => true,
            'created_by' => $user->id,
        ]);
        $outbox = NotificationOutbox::create([
            'org_id' => $user->org_id,
            'user_id' => $user->id,
            'event_type' => 'test.event',
            'channel' => 'slack',
            'payload' => ['title' => 'Alert', 'body' => 'Body'],
            'idempotency_key' => 'failure-key',
            'status' => 'pending',
            'available_at' => now(),
        ]);
        Http::fake(['hooks.slack.test/*' => Http::response('', 500)]);
        $service = app(NotificationOutboxService::class);

        $this->assertFalse($service->dispatch($outbox->id));
        $outbox->refresh();
        $this->assertSame('retrying', $outbox->status);
        $this->assertSame(1, $outbox->attempt_count);
        $this->assertDatabaseHas('notification_dispatches', ['notification_outbox_id' => $outbox->id, 'status' => 'retrying']);

        $outbox->update(['status' => 'pending', 'available_at' => now()->subMinute(), 'attempt_count' => 4]);
        $this->assertFalse($service->dispatch($outbox->id));
        $this->assertSame('failed', $outbox->fresh()->status);
        $this->assertNotNull($outbox->fresh()->failed_at);
    }

    public function test_quiet_hours_suppress_normal_external_delivery(): void
    {
        $user = User::factory()->create();
        NotificationChannel::create([
            'org_id' => $user->org_id,
            'channel' => 'telegram',
            'name' => 'Telegram',
            'config' => ['bot_token' => 'token', 'chat_id' => '123'],
            'is_enabled' => true,
            'created_by' => $user->id,
        ]);
        UserChannelPreference::create([
            'org_id' => $user->org_id,
            'user_id' => $user->id,
            'channel' => 'telegram',
            'enabled' => true,
            'quiet_hours_start' => now($user->organization->timezone)->subMinute()->format('H:i:s'),
            'quiet_hours_end' => now($user->organization->timezone)->addMinute()->format('H:i:s'),
        ]);

        app(NotificationService::class)->notify($user, 'test.event', 'quiet-key', 'Alert', 'Body');

        $this->assertDatabaseMissing('notification_outbox', ['channel' => 'telegram']);
        $this->assertDatabaseCount('notification_outbox', 2);
    }

    public function test_daily_digest_has_no_cash_balance_payload(): void
    {
        $source = file_get_contents(base_path('routes/console.php'));

        $this->assertStringContainsString("Artisan::command('notifications:daily-digest'", $source);
        $start = strpos($source, "Artisan::command('notifications:daily-digest'");
        $end = strpos($source, ')->purpose', $start);
        $digest = substr($source, $start, $end - $start);
        $this->assertStringNotContainsStringIgnoringCase('cash balance', $digest);
        $this->assertStringNotContainsStringIgnoringCase('cash_balance', $digest);
    }
}
