import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

type Preference = {
    type: string;
    label: string;
    email_enabled: boolean;
    in_app_enabled: boolean;
};

export default function NotificationPreferences({
    preferences,
}: {
    preferences: Preference[];
}) {
    const form = useForm({ preferences });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        form.patch(route('settings.notifications.update'), {
            preserveScroll: true,
        });
    };

    const toggle = (
        index: number,
        key: 'email_enabled' | 'in_app_enabled',
        value: boolean,
    ) => {
        form.setData(
            'preferences',
            form.data.preferences.map((preference, currentIndex) =>
                currentIndex === index
                    ? { ...preference, [key]: value }
                    : preference,
            ),
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Notification Preferences" />

            <div className="space-y-6">
                <PageHeader
                    title="Notification Preferences"
                    description="Control email and in-app notification channels."
                />

                <Card title="Channels" description="Set preferences per event.">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="overflow-hidden rounded-md border border-slate-200 dark:border-slate-800">
                            <div className="grid grid-cols-[1fr_120px_120px] bg-slate-50 px-4 py-3 text-xs font-bold uppercase text-slate-500 dark:bg-slate-900 dark:text-slate-300">
                                <div>Event</div>
                                <div>Email</div>
                                <div>In App</div>
                            </div>
                            {form.data.preferences.map((preference, index) => (
                                <div
                                    key={preference.type}
                                    className="grid grid-cols-[1fr_120px_120px] items-center border-t border-slate-200 px-4 py-3 text-sm dark:border-slate-800"
                                >
                                    <div>
                                        <div className="font-semibold text-slate-900 dark:text-white">
                                            {preference.label}
                                        </div>
                                        <div className="text-xs text-slate-500 dark:text-slate-300">
                                            {preference.type}
                                        </div>
                                    </div>
                                    <label className="inline-flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={preference.email_enabled}
                                            onChange={(event) =>
                                                toggle(
                                                    index,
                                                    'email_enabled',
                                                    event.target.checked,
                                                )
                                            }
                                            className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        />
                                    </label>
                                    <label className="inline-flex items-center">
                                        <input
                                            type="checkbox"
                                            checked={preference.in_app_enabled}
                                            onChange={(event) =>
                                                toggle(
                                                    index,
                                                    'in_app_enabled',
                                                    event.target.checked,
                                                )
                                            }
                                            className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        />
                                    </label>
                                </div>
                            ))}
                        </div>

                        <div className="flex justify-end">
                            <PrimaryButton disabled={form.processing}>
                                Save Preferences
                            </PrimaryButton>
                        </div>
                    </form>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
