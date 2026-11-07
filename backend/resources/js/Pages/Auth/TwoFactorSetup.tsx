import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import InputLabel from '@/Components/InputLabel';
import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';
import PrimaryButton from '@/Components/PrimaryButton';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function TwoFactorSetup({
    secret,
    otpauthUri,
}: {
    secret: string;
    otpauthUri: string;
}) {
    const form = useForm({ code: '' });
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('two-factor.setup.confirm'));
    };
    return (
        <AuthenticatedLayout>
            <Head title="Set up 2FA" />
            <PageHeader
                title="Two-Factor Authentication"
                description="Set up an authenticator for your account."
            />
            <Card
                className="mx-auto max-w-xl"
                bodyClassName="space-y-4 p-4 sm:p-6"
            >
                <p>
                    Open Google Authenticator, choose “Enter a setup key”, then
                    use this key:
                </p>
                <code className="block break-all rounded-lg bg-slate-100 p-3 text-slate-900 dark:bg-slate-800 dark:text-slate-100">
                    {secret}
                </code>
                <details>
                    <summary className="min-h-11 cursor-pointer py-2">
                        Authenticator URI
                    </summary>
                    <code className="block break-all p-2 text-xs">
                        {otpauthUri}
                    </code>
                </details>
                <form onSubmit={submit} className="space-y-3">
                    <InputLabel
                        htmlFor="two-factor-setup-code"
                        value="Authenticator code"
                    />
                    <TextInput
                        id="two-factor-setup-code"
                        name="code"
                        required
                        isFocused
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        pattern="[0-9]{6}"
                        maxLength={6}
                        placeholder="6-digit code"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        className="w-full"
                        aria-invalid={Boolean(form.errors.code)}
                        aria-describedby={
                            form.errors.code
                                ? 'two-factor-setup-error'
                                : undefined
                        }
                    />
                    <InputError
                        id="two-factor-setup-error"
                        message={form.errors.code}
                    />
                    <PrimaryButton disabled={form.processing}>
                        Confirm and enable
                    </PrimaryButton>
                </form>
            </Card>
        </AuthenticatedLayout>
    );
}
