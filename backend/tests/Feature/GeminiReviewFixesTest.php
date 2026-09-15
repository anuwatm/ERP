<?php

namespace Tests\Feature;

use App\Models\NotificationChannel;
use App\Models\NotificationOutbox;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationOutboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GeminiReviewFixesTest extends TestCase
{
    use RefreshDatabase;

    private function manager(): User
    {
        $this->withoutVite();
        $user = User::factory()->create();
        $role = Role::create(['org_id' => $user->org_id, 'code' => 'review-manager', 'name' => 'Review manager', 'is_system' => true]);
        $role->permissions()->attach(Permission::firstOrCreate(['code' => 'settings.organization.update'], ['module' => 'settings', 'action' => 'update']));
        $user->roles()->attach($role);

        return $user;
    }

    public function test_attendance_privacy_route_requires_permission_confirmation_and_preserves_org_scope(): void
    {
        $user = $this->manager();
        $other = User::factory()->create();
        $payload = ['capture_ip' => false, 'capture_gps' => false, 'require_employee_consent' => true, 'retention_days' => 90];
        $url = route('settings.organization.attendance-privacy.update');
        $this->actingAsOrgUser($other)->patch($url, $payload)->assertForbidden();
        $this->actingAsOrgUser($user)->patch($url, $payload)->assertRedirect(route('password.confirm'));
        $this->withSession(['auth.password_confirmed_at' => time()])->patch($url, array_merge($payload, ['retention_days' => 1]))->assertSessionHasErrors('retention_days');
        $this->patch($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($payload, Setting::where('org_id', $user->org_id)->where('key', 'hr.attendance_privacy')->firstOrFail()->value_json);
        $this->assertDatabaseMissing('settings', ['org_id' => $other->org_id, 'key' => 'hr.attendance_privacy']);
    }

    public function test_channel_errors_are_actionable_without_leaking_secrets_to_session_or_logs(): void
    {
        $user = $this->manager();
        $channel = NotificationChannel::create(['org_id' => $user->org_id, 'channel' => 'slack', 'name' => 'Fixture', 'config' => ['webhook_url' => 'https://hooks.slack.test/SECRET-WEBHOOK'], 'is_enabled' => true, 'created_by' => $user->id]);
        Http::fake(['hooks.slack.test/*' => Http::response('SECRET-RESPONSE', 500)]);
        Log::spy();
        $this->actingAsOrgUser($user)->withSession(['auth.password_confirmed_at' => time()])->post(route('settings.notification-channels.test', $channel))->assertRedirect()->assertSessionHasErrors('channel')->assertSessionMissing('success');
        $this->assertStringNotContainsString('SECRET', json_encode(session()->all()));
        Log::shouldHaveReceived('warning')->with('Notification channel test failed.', ['channel_id' => $channel->id, 'channel' => 'slack', 'exception_type' => RequestException::class])->once();
        $outbox = NotificationOutbox::create(['org_id' => $user->org_id, 'user_id' => $user->id, 'event_type' => 'fixture', 'channel' => 'slack', 'payload' => ['title' => 'Fixture'], 'idempotency_key' => 'review-safe-log', 'status' => 'pending', 'available_at' => now()]);
        $this->assertFalse(app(NotificationOutboxService::class)->dispatch($outbox->id));
        Log::shouldHaveReceived('warning')->with('Notification delivery failed.', ['outbox_id' => $outbox->id, 'channel' => 'slack', 'attempt' => 1, 'exception_type' => RequestException::class])->once();
        $this->assertSame('retrying', $outbox->fresh()->status);
        $this->assertStringNotContainsString('SECRET', $outbox->fresh()->last_error);
        Http::swap(new Factory);
        Http::fake(['hooks.slack.test/*' => Http::response('', 200)]);
        $this->post(route('settings.notification-channels.test', $channel))->assertRedirect()->assertSessionHas('success');
    }
}
