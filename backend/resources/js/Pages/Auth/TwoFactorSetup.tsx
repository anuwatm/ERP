import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
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
        <AuthenticatedLayout header="Two-Factor Authentication">
            <Head title="Set up 2FA" />
            <div className="mx-auto max-w-xl space-y-4 p-6">
                <p>
                    Open Google Authenticator, choose “Enter a setup key”, then
                    use this key:
                </p>
                <code className="block break-all rounded bg-slate-100 p-3">
                    {secret}
                </code>
                <details>
                    <summary>Authenticator URI</summary>
                    <code className="block break-all p-2 text-xs">
                        {otpauthUri}
                    </code>
                </details>
                <form onSubmit={submit} className="space-y-3">
                    <input
                        required
                        autoFocus
                        placeholder="6-digit code"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        className="w-full rounded border-slate-300"
                    />
                    <div className="text-sm text-red-600">
                        {form.errors.code}
                    </div>
                    <button className="rounded bg-indigo-600 px-4 py-2 text-white">
                        Confirm and enable
                    </button>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
