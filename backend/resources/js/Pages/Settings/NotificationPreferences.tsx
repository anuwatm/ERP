import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

type Preference = {
    type: string;
    label: string;
    email_enabled: boolean;
    in_app_enabled: boolean;
};
type ExternalPreference = {
    channel: 'line' | 'slack' | 'telegram';
    enabled: boolean;
    quiet_hours_start: string | null;
    quiet_hours_end: string | null;
    urgent_only: boolean;
};
type Channel = {
    id: string;
    channel: string;
    name: string;
    is_enabled: boolean;
    configured: boolean;
};
type ChannelForm = {
    channel: 'line' | 'slack' | 'telegram';
    name: string;
    config: {
        webhook_url: string;
        access_token: string;
        target_id: string;
        bot_token: string;
        chat_id: string;
    };
};

export default function NotificationPreferences({
    preferences,
    externalPreferences,
    channels,
    canManageChannels,
}: {
    preferences: Preference[];
    externalPreferences: ExternalPreference[];
    channels: Channel[];
    canManageChannels: boolean;
}) {
    const preferencesForm = useForm({ preferences });
    const externalForm = useForm({ preferences: externalPreferences });
    const channelForm = useForm<ChannelForm>({
        channel: 'slack',
        name: '',
        config: {
            webhook_url: '',
            access_token: '',
            target_id: '',
            bot_token: '',
            chat_id: '',
        },
    });
    const [savingChannel, setSavingChannel] = useState(false);

    const submitPreferences: FormEventHandler = (event) => {
        event.preventDefault();
        preferencesForm.patch(route('settings.notifications.update'), {
            preserveScroll: true,
        });
    };
    const submitExternal: FormEventHandler = (event) => {
        event.preventDefault();
        externalForm.patch(
            route('settings.notifications.external-preferences.update'),
            {
                preserveScroll: true,
            },
        );
    };
    const submitChannel: FormEventHandler = (event) => {
        event.preventDefault();
        channelForm.post(route('settings.notification-channels.store'), {
            preserveScroll: true,
            onSuccess: () => channelForm.reset(),
        });
    };
    const updatePreference = (
        index: number,
        key: 'email_enabled' | 'in_app_enabled',
        value: boolean,
    ) => {
        preferencesForm.setData(
            'preferences',
            preferencesForm.data.preferences.map((preference, currentIndex) =>
                currentIndex === index
                    ? { ...preference, [key]: value }
                    : preference,
            ),
        );
    };
    const updateExternal = (
        index: number,
        key: keyof Omit<ExternalPreference, 'channel'>,
        value: boolean | string,
    ) => {
        externalForm.setData(
            'preferences',
            externalForm.data.preferences.map((preference, currentIndex) =>
                currentIndex === index
                    ? { ...preference, [key]: value }
                    : preference,
            ),
        );
    };
    const configField = (key: keyof ChannelForm['config'], value: string) =>
        channelForm.setData('config', {
            ...channelForm.data.config,
            [key]: value,
        });

    return (
        <AuthenticatedLayout>
            <Head title="Notification Settings" />
            <div className="space-y-6">
                <PageHeader
                    title="Notification Settings"
                    description="Control personal delivery preferences and organization notification channels."
                />
                <Card
                    title="Event Delivery"
                    description="Email and in-app delivery by event type."
                >
                    <form onSubmit={submitPreferences} className="space-y-4">
                        <div className="overflow-x-auto border border-slate-200 dark:border-slate-800">
                            <div className="grid min-w-[560px] grid-cols-[1fr_120px_120px] bg-slate-50 px-4 py-3 text-xs font-bold uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-300">
                                <div>Event</div>
                                <div>Email</div>
                                <div>In App</div>
                            </div>
                            {preferencesForm.data.preferences.map(
                                (preference, index) => (
                                    <div
                                        key={preference.type}
                                        className="grid min-w-[560px] grid-cols-[1fr_120px_120px] items-center border-t border-slate-200 px-4 py-3 text-sm dark:border-slate-800"
                                    >
                                        <div>
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {preference.label}
                                            </div>
                                            <div className="text-xs text-slate-500 dark:text-slate-300">
                                                {preference.type}
                                            </div>
                                        </div>
                                        <input
                                            type="checkbox"
                                            checked={preference.email_enabled}
                                            onChange={(event) =>
                                                updatePreference(
                                                    index,
                                                    'email_enabled',
                                                    event.target.checked,
                                                )
                                            }
                                            className="rounded border-slate-300 text-indigo-600"
                                        />
                                        <input
                                            type="checkbox"
                                            checked={preference.in_app_enabled}
                                            onChange={(event) =>
                                                updatePreference(
                                                    index,
                                                    'in_app_enabled',
                                                    event.target.checked,
                                                )
                                            }
                                            className="rounded border-slate-300 text-indigo-600"
                                        />
                                    </div>
                                ),
                            )}
                        </div>
                        <div className="flex justify-end">
                            <PrimaryButton
                                disabled={preferencesForm.processing}
                            >
                                Save Event Preferences
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>

                <Card
                    title="External Delivery"
                    description="External channels are opt-in. Quiet hours suppress normal-priority delivery."
                >
                    <form onSubmit={submitExternal} className="space-y-4">
                        <div className="space-y-3">
                            {externalForm.data.preferences.map(
                                (preference, index) => (
                                    <div
                                        key={preference.channel}
                                        className="grid gap-3 border-l-2 border-slate-200 pl-3 md:grid-cols-[130px_90px_1fr_1fr_120px] md:items-center"
                                    >
                                        <span className="font-medium capitalize text-slate-800 dark:text-slate-100">
                                            {preference.channel}
                                        </span>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={preference.enabled}
                                                onChange={(event) =>
                                                    updateExternal(
                                                        index,
                                                        'enabled',
                                                        event.target.checked,
                                                    )
                                                }
                                                className="rounded border-slate-300 text-indigo-600"
                                            />
                                            Enabled
                                        </label>
                                        <input
                                            type="time"
                                            value={
                                                preference.quiet_hours_start ??
                                                ''
                                            }
                                            onChange={(event) =>
                                                updateExternal(
                                                    index,
                                                    'quiet_hours_start',
                                                    event.target.value,
                                                )
                                            }
                                            aria-label={`${preference.channel} quiet start`}
                                            className="rounded-md border-slate-300"
                                        />
                                        <input
                                            type="time"
                                            value={
                                                preference.quiet_hours_end ?? ''
                                            }
                                            onChange={(event) =>
                                                updateExternal(
                                                    index,
                                                    'quiet_hours_end',
                                                    event.target.value,
                                                )
                                            }
                                            aria-label={`${preference.channel} quiet end`}
                                            className="rounded-md border-slate-300"
                                        />
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                checked={preference.urgent_only}
                                                onChange={(event) =>
                                                    updateExternal(
                                                        index,
                                                        'urgent_only',
                                                        event.target.checked,
                                                    )
                                                }
                                                className="rounded border-slate-300 text-indigo-600"
                                            />
                                            Urgent only
                                        </label>
                                    </div>
                                ),
                            )}
                        </div>
                        <div className="flex justify-end">
                            <PrimaryButton disabled={externalForm.processing}>
                                Save External Preferences
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>

                {canManageChannels && (
                    <Card
                        title="Organization Channels"
                        description="Credentials are encrypted and never returned to this page after saving."
                    >
                        <div className="space-y-5">
                            <form
                                onSubmit={submitChannel}
                                className="grid gap-3 md:grid-cols-2"
                            >
                                <select
                                    value={channelForm.data.channel}
                                    onChange={(event) =>
                                        channelForm.setData(
                                            'channel',
                                            event.target
                                                .value as ChannelForm['channel'],
                                        )
                                    }
                                    className="rounded-md border-slate-300"
                                >
                                    <option value="slack">Slack webhook</option>
                                    <option value="line">
                                        LINE Messaging API
                                    </option>
                                    <option value="telegram">
                                        Telegram bot
                                    </option>
                                </select>
                                <input
                                    required
                                    value={channelForm.data.name}
                                    onChange={(event) =>
                                        channelForm.setData(
                                            'name',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="Channel name"
                                    className="rounded-md border-slate-300"
                                />
                                {channelForm.data.channel === 'slack' && (
                                    <input
                                        required
                                        type="url"
                                        value={
                                            channelForm.data.config.webhook_url
                                        }
                                        onChange={(event) =>
                                            configField(
                                                'webhook_url',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Webhook URL"
                                        className="rounded-md border-slate-300 md:col-span-2"
                                    />
                                )}
                                {channelForm.data.channel === 'line' && (
                                    <>
                                        <input
                                            required
                                            type="password"
                                            value={
                                                channelForm.data.config
                                                    .access_token
                                            }
                                            onChange={(event) =>
                                                configField(
                                                    'access_token',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Channel access token"
                                            className="rounded-md border-slate-300"
                                        />
                                        <input
                                            required
                                            value={
                                                channelForm.data.config
                                                    .target_id
                                            }
                                            onChange={(event) =>
                                                configField(
                                                    'target_id',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="User or group ID"
                                            className="rounded-md border-slate-300"
                                        />
                                    </>
                                )}
                                {channelForm.data.channel === 'telegram' && (
                                    <>
                                        <input
                                            required
                                            type="password"
                                            value={
                                                channelForm.data.config
                                                    .bot_token
                                            }
                                            onChange={(event) =>
                                                configField(
                                                    'bot_token',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Bot token"
                                            className="rounded-md border-slate-300"
                                        />
                                        <input
                                            required
                                            value={
                                                channelForm.data.config.chat_id
                                            }
                                            onChange={(event) =>
                                                configField(
                                                    'chat_id',
                                                    event.target.value,
                                                )
                                            }
                                            placeholder="Chat ID"
                                            className="rounded-md border-slate-300"
                                        />
                                    </>
                                )}
                                <div className="md:col-span-2">
                                    <PrimaryButton
                                        disabled={channelForm.processing}
                                    >
                                        Save Channel
                                    </PrimaryButton>
                                </div>
                            </form>
                            <div className="space-y-2">
                                {channels.map((channel) => (
                                    <div
                                        key={channel.id}
                                        className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 py-3 text-sm dark:border-slate-800"
                                    >
                                        <div>
                                            <span className="font-semibold text-slate-900 dark:text-white">
                                                {channel.name}
                                            </span>
                                            <span className="ml-2 text-slate-500">
                                                {channel.channel} ·{' '}
                                                {channel.configured
                                                    ? 'configured'
                                                    : 'not configured'}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={channel.is_enabled}
                                                    onChange={(event) => {
                                                        setSavingChannel(true);
                                                        router.patch(
                                                            route(
                                                                'settings.notification-channels.update',
                                                                channel.id,
                                                            ),
                                                            {
                                                                is_enabled:
                                                                    event.target
                                                                        .checked,
                                                            },
                                                            {
                                                                preserveScroll: true,
                                                                onFinish: () =>
                                                                    setSavingChannel(
                                                                        false,
                                                                    ),
                                                            },
                                                        );
                                                    }}
                                                    disabled={savingChannel}
                                                    className="rounded border-slate-300 text-indigo-600"
                                                />
                                                Enabled
                                            </label>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'settings.notification-channels.test',
                                                            channel.id,
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                className="text-indigo-700"
                                            >
                                                Test
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
