import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('login'), { onFinish: () => reset('password') });
    };

    const fillDemoUser = (email: string) => {
        setData((previous) => ({ ...previous, email, password: 'password' }));
    };

    return (
        <GuestLayout>
            <Head title="Sign in - Company OS ERP" />
            <h1 className="text-3xl font-bold tracking-tight">Welcome Back</h1>
            <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                Enter your credentials to access your ERP workspace.
            </p>
            {status && (
                <p
                    role="status"
                    className="mt-4 rounded-lg bg-emerald-50 p-3 text-sm text-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
                >
                    {status}
                </p>
            )}
            <form onSubmit={submit} className="mt-6 space-y-4">
                <div>
                    <InputLabel htmlFor="email" value="Email Address" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1.5 block min-h-11 w-full"
                        autoComplete="username"
                        placeholder="owner@example.com"
                        isFocused
                        aria-invalid={Boolean(errors.email)}
                        aria-describedby={
                            errors.email ? 'login-email-error' : undefined
                        }
                        onChange={(event) =>
                            setData('email', event.target.value)
                        }
                    />
                    <InputError
                        id="login-email-error"
                        message={errors.email}
                        className="mt-1.5"
                    />
                </div>
                <div>
                    <InputLabel htmlFor="password" value="Password" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1.5 block min-h-11 w-full"
                        autoComplete="current-password"
                        aria-invalid={Boolean(errors.password)}
                        aria-describedby={
                            errors.password ? 'login-password-error' : undefined
                        }
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                    />
                    <InputError
                        id="login-password-error"
                        message={errors.password}
                        className="mt-1.5"
                    />
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                    <label className="flex min-h-10 items-center gap-2">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(event) =>
                                setData('remember', event.target.checked)
                            }
                        />
                        Remember me
                    </label>
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="font-medium text-teal-800 underline underline-offset-2 dark:text-teal-200"
                        >
                            Forgot password?
                        </Link>
                    )}
                </div>
                <button
                    type="submit"
                    disabled={processing}
                    className="erp-primary min-h-11 w-full rounded-lg px-4 py-2 text-sm font-semibold disabled:opacity-50"
                >
                    Sign in to ERP Dashboard
                </button>
            </form>
            <details className="mt-5 border-t border-slate-200 pt-4 text-sm dark:border-slate-700">
                <summary className="cursor-pointer font-semibold">
                    Quick Demo Login
                </summary>
                <div className="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={() => fillDemoUser('owner@example.com')}
                        className="min-h-10 rounded-lg border border-slate-300 px-3 dark:border-slate-600"
                    >
                        Demo Owner
                    </button>
                    <button
                        type="button"
                        onClick={() => fillDemoUser('admin@example.com')}
                        className="min-h-10 rounded-lg border border-slate-300 px-3 dark:border-slate-600"
                    >
                        Demo Admin
                    </button>
                </div>
            </details>
        </GuestLayout>
    );
}
