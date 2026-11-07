import GuestLayout from '@/Layouts/GuestLayout';
import TextInput from '@/Components/TextInput';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function TwoFactorChallenge({
    allowTrustedDevices,
    trustedDeviceDays,
}: {
    allowTrustedDevices: boolean;
    trustedDeviceDays: number;
}) {
    const form = useForm({ code: '', trust_device: false });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('two-factor.challenge.verify'));
    };
    return (
        <GuestLayout>
            <Head title="Two-Factor Authentication" />
            <form onSubmit={submit} className="space-y-4">
                <h1 className="text-xl font-semibold">
                    Two-Factor Authentication
                </h1>
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    Enter the 6-digit code from Google Authenticator, or a
                    recovery code.
                </p>
                <div>
                    <InputLabel
                        htmlFor="two-factor-code"
                        value="Authentication or recovery code"
                    />
                    <TextInput
                        id="two-factor-code"
                        autoFocus
                        autoComplete="one-time-code"
                        required
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        className="mt-1.5 w-full"
                        aria-invalid={Boolean(form.errors.code)}
                        aria-describedby={
                            form.errors.code ? 'two-factor-error' : undefined
                        }
                    />
                    <InputError
                        id="two-factor-error"
                        message={form.errors.code}
                        className="mt-1.5"
                    />
                </div>
                {allowTrustedDevices && (
                    <label className="block text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.trust_device}
                            onChange={(e) =>
                                form.setData('trust_device', e.target.checked)
                            }
                        />{' '}
                        Trust this device for {trustedDeviceDays} days
                    </label>
                )}
                <button
                    className="erp-primary min-h-11 rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-50"
                    disabled={form.processing}
                >
                    Verify
                </button>
            </form>
        </GuestLayout>
    );
}
