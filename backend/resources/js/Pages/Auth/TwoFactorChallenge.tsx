import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

export default function TwoFactorChallenge() {
    const form = useForm({ code: '', trust_device: false });
    const submit = (event: FormEvent) => { event.preventDefault(); form.post(route('two-factor.challenge.verify')); };
    return <GuestLayout><Head title="Two-Factor Authentication" /><form onSubmit={submit} className="mx-auto max-w-md space-y-4 rounded-lg bg-white p-6 shadow"><h1 className="text-xl font-semibold">Two-Factor Authentication</h1><p className="text-sm text-slate-600">Enter the 6-digit code from Google Authenticator, or a recovery code.</p><input autoFocus required value={form.data.code} onChange={e => form.setData('code', e.target.value)} className="w-full rounded border-slate-300" /><label className="block text-sm"><input type="checkbox" checked={form.data.trust_device} onChange={e => form.setData('trust_device', e.target.checked)} /> Trust this device for 30 days</label><div className="text-sm text-red-600">{form.errors.code}</div><button className="rounded bg-indigo-600 px-4 py-2 text-white" disabled={form.processing}>Verify</button></form></GuestLayout>;
}
