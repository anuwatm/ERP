<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NotificationChannel;
use App\Models\NotificationPreference;
use App\Models\UserChannelPreference;
use App\Services\NotificationOutboxService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NotificationPreferenceController extends Controller
{
    private const EXTERNAL_CHANNELS = ['line', 'slack', 'telegram'];

    private const TYPES = [
        'purchase_order.pending_approval' => 'PO pending approval',
        'invoice.due_soon' => 'Invoice due soon',
        'invoice.overdue' => 'Invoice overdue',
        'task.assigned' => 'Task assigned',
        'project.member_assigned' => 'Project member assigned',
        'user.invite' => 'User invitation',
    ];

    public function edit(Request $request): Response
    {
        $user = $request->user();
        $stored = NotificationPreference::where('user_id', $user->id)->get()->keyBy('type');
        $external = UserChannelPreference::where('user_id', $user->id)->get()->keyBy('channel');
        $canManageChannels = $user->hasPermissionCode('settings.organization.update');

        return Inertia::render('Settings/NotificationPreferences', [
            'preferences' => collect(self::TYPES)->map(fn (string $label, string $type) => [
                'type' => $type,
                'label' => $label,
                'email_enabled' => $stored[$type]->email_enabled ?? true,
                'in_app_enabled' => $stored[$type]->in_app_enabled ?? true,
            ])->values(),
            'externalPreferences' => collect(self::EXTERNAL_CHANNELS)->map(fn (string $channel) => [
                'channel' => $channel,
                'enabled' => $external->get($channel)?->enabled ?? false,
                'quiet_hours_start' => $external->get($channel)?->quiet_hours_start,
                'quiet_hours_end' => $external->get($channel)?->quiet_hours_end,
                'urgent_only' => $external->get($channel)?->urgent_only ?? false,
            ])->values(),
            'channels' => $canManageChannels
                ? NotificationChannel::where('org_id', $user->org_id)->orderBy('channel')->get()->map(fn (NotificationChannel $channel) => [
                    'id' => $channel->id,
                    'channel' => $channel->channel,
                    'name' => $channel->name,
                    'is_enabled' => $channel->is_enabled,
                    'configured' => ! empty($channel->config),
                ])->values()
                : [],
            'canManageChannels' => $canManageChannels,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $types = array_keys(self::TYPES);
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.type' => ['required', Rule::in($types)],
            'preferences.*.email_enabled' => ['required', 'boolean'],
            'preferences.*.in_app_enabled' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $preference) {
            NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'type' => $preference['type']],
                [
                    'org_id' => $user->org_id,
                    'email_enabled' => $preference['email_enabled'],
                    'in_app_enabled' => $preference['in_app_enabled'],
                ]
            );
        }

        return back()->with('success', 'Notification preferences updated.');
    }

    public function updateExternal(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'preferences' => ['required', 'array'],
            'preferences.*.channel' => ['required', Rule::in(self::EXTERNAL_CHANNELS)],
            'preferences.*.enabled' => ['required', 'boolean'],
            'preferences.*.quiet_hours_start' => ['nullable', 'date_format:H:i'],
            'preferences.*.quiet_hours_end' => ['nullable', 'date_format:H:i', 'required_with:preferences.*.quiet_hours_start'],
            'preferences.*.urgent_only' => ['required', 'boolean'],
        ]);

        foreach ($validated['preferences'] as $preference) {
            UserChannelPreference::updateOrCreate(
                ['user_id' => $user->id, 'channel' => $preference['channel']],
                $preference + ['org_id' => $user->org_id]
            );
        }

        return back()->with('success', 'External channel preferences updated.');
    }

    public function storeChannel(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'channel' => ['required', Rule::in(self::EXTERNAL_CHANNELS)],
            'name' => ['required', 'string', 'max:100'],
            'config' => ['required', 'array'],
            'config.webhook_url' => ['nullable', 'url', 'max:500'],
            'config.access_token' => ['nullable', 'string', 'max:500'],
            'config.target_id' => ['nullable', 'string', 'max:255'],
            'config.bot_token' => ['nullable', 'string', 'max:500'],
            'config.chat_id' => ['nullable', 'string', 'max:255'],
        ]);
        $this->validateChannelConfig($data['channel'], $data['config']);

        $channel = NotificationChannel::updateOrCreate(
            ['org_id' => $user->org_id, 'channel' => $data['channel']],
            ['name' => $data['name'], 'config' => $data['config'], 'is_enabled' => false, 'created_by' => $user->id]
        );
        $this->audit($request, 'notification.channel.configure', $channel->id, ['channel' => $channel->channel, 'is_enabled' => false]);

        return back()->with('success', 'Notification channel saved. Enable it after a successful test.');
    }

    public function updateChannel(Request $request, NotificationChannel $channel): RedirectResponse
    {
        abort_unless($channel->org_id === $request->user()->org_id, 404);
        $data = $request->validate(['is_enabled' => ['required', 'boolean']]);
        $channel->update($data);
        $this->audit($request, 'notification.channel.update', $channel->id, ['channel' => $channel->channel, 'is_enabled' => $channel->is_enabled]);

        return back()->with('success', 'Notification channel updated.');
    }

    public function testChannel(Request $request, NotificationChannel $channel, NotificationOutboxService $outbox): RedirectResponse
    {
        abort_unless($channel->org_id === $request->user()->org_id, 404);
        $outbox->sendTest($channel, $request->user());
        $this->audit($request, 'notification.channel.test', $channel->id, ['channel' => $channel->channel]);

        return back()->with('success', 'Test notification sent.');
    }

    private function validateChannelConfig(string $channel, array $config): void
    {
        $required = match ($channel) {
            'slack' => ['webhook_url'],
            'line' => ['access_token', 'target_id'],
            'telegram' => ['bot_token', 'chat_id'],
        };
        foreach ($required as $key) {
            if (empty($config[$key])) {
                throw ValidationException::withMessages([
                    "config.{$key}" => "{$key} is required for {$channel}.",
                ]);
            }
        }
    }

    private function audit(Request $request, string $action, string $channelId, array $after): void
    {
        AuditLog::create([
            'org_id' => $request->user()->org_id,
            'actor_user_id' => $request->user()->id,
            'action' => $action,
            'entity_type' => 'notification_channel',
            'entity_id' => $channelId,
            'after_json' => $after,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
    }
}
