import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import GuestLayout from '@/Layouts/GuestLayout';
import { PageProps } from '@/Types/auth';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const org = usePage<PageProps>().props.org;
    const companyName = org?.name || 'Demo ERP Co., Ltd.';

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    const fillDemoUser = (email: string) => {
        setData((prev) => ({
            ...prev,
            email,
            password: 'password',
        }));
    };

    return (
        <GuestLayout>
            <Head title={`Log in - ${companyName}`} />

            <div className="glass-card rounded-3xl p-8 sm:p-10 shadow-2xl relative overflow-hidden group">
                {/* Top Specular Glow Reflection Line */}
                <div className="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent" />

                <div className="mb-6">
                    {org?.logo_url && (
                        <div className="mb-5 flex items-center gap-3 border-b border-white/10 pb-4">
                            <img
                                src={org.logo_url}
                                alt={companyName}
                                className="h-12 w-12 rounded-xl object-contain bg-white/10 p-1 shadow-md border border-white/15"
                            />
                            <div>
                                <span className="block font-bold text-white text-sm leading-snug">
                                    {companyName}
                                </span>
                                <span className="text-[11px] font-semibold text-indigo-300 uppercase tracking-wider">
                                    {org?.legal_name || 'Enterprise ERP Portal'}
                                </span>
                            </div>
                        </div>
                    )}
                    <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/20 text-xs font-semibold text-indigo-300 mb-3">
                        🔐 Secure Sign In
                    </div>
                    <h2 className="text-3xl font-black text-white tracking-tight font-sans">
                        Welcome Back
                    </h2>
                    <p className="mt-1.5 text-xs text-slate-300">
                        Enter your credentials to access {companyName}{' '}
                        workspace.
                    </p>
                </div>

                {status && (
                    <div className="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3.5 text-xs font-medium text-emerald-300 backdrop-blur-md">
                        {status}
                    </div>
                )}

                {/* Quick Demo Fill Helper Container */}
                <div className="mb-6 rounded-2xl border border-indigo-500/20 bg-slate-950/40 p-4 backdrop-blur-md">
                    <div className="flex items-center justify-between mb-2.5">
                        <span className="text-[11px] font-bold uppercase tracking-wider text-indigo-300">
                            Quick Demo Login
                        </span>
                        <Badge variant="purple" size="sm">
                            Phase 1
                        </Badge>
                    </div>
                    <div className="flex gap-2.5">
                        <button
                            type="button"
                            onClick={() => fillDemoUser('owner@example.com')}
                            className="flex-1 group/btn relative overflow-hidden rounded-xl border border-indigo-500/30 bg-gradient-to-r from-indigo-900/40 to-indigo-800/40 px-3 py-2 text-xs font-semibold text-indigo-200 transition-all duration-200 hover:border-indigo-400 hover:text-white hover:shadow-lg hover:shadow-indigo-500/20 active:scale-[0.98]"
                        >
                            <span className="relative z-10 flex items-center justify-center gap-1.5">
                                🔑 Demo Owner
                            </span>
                        </button>
                        <button
                            type="button"
                            onClick={() => fillDemoUser('admin@example.com')}
                            className="flex-1 group/btn relative overflow-hidden rounded-xl border border-slate-700 bg-slate-900/60 px-3 py-2 text-xs font-semibold text-slate-300 transition-all duration-200 hover:border-slate-500 hover:text-white hover:shadow-lg hover:shadow-slate-700/20 active:scale-[0.98]"
                        >
                            <span className="relative z-10 flex items-center justify-center gap-1.5">
                                🛡️ Demo Admin
                            </span>
                        </button>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <InputLabel
                            htmlFor="email"
                            value="Email Address"
                            className="text-[11px] font-bold uppercase tracking-wider text-slate-300"
                        />

                        <div className="relative mt-1.5">
                            <TextInput
                                id="email"
                                type="email"
                                name="email"
                                value={data.email}
                                className="glass-input block w-full rounded-xl text-white placeholder-slate-500 text-sm py-2.5 px-3.5"
                                autoComplete="username"
                                placeholder="owner@example.com"
                                isFocused={true}
                                onChange={(e) =>
                                    setData('email', e.target.value)
                                }
                            />
                        </div>

                        <InputError message={errors.email} className="mt-1.5" />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="password"
                            value="Password"
                            className="text-[11px] font-bold uppercase tracking-wider text-slate-300"
                        />

                        <div className="relative mt-1.5">
                            <TextInput
                                id="password"
                                type="password"
                                name="password"
                                value={data.password}
                                className="glass-input block w-full rounded-xl text-white placeholder-slate-500 text-sm py-2.5 px-3.5"
                                autoComplete="current-password"
                                placeholder="••••••••"
                                onChange={(e) =>
                                    setData('password', e.target.value)
                                }
                            />
                        </div>

                        <InputError
                            message={errors.password}
                            className="mt-1.5"
                        />
                    </div>

                    <div className="flex items-center justify-between pt-1">
                        <label className="flex items-center cursor-pointer select-none">
                            <Checkbox
                                name="remember"
                                checked={data.remember}
                                onChange={(e) =>
                                    setData(
                                        'remember',
                                        (e.target.checked || false) as false,
                                    )
                                }
                                className="rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500"
                            />
                            <span className="ms-2 text-xs font-semibold text-slate-200 hover:text-white transition-colors">
                                Remember me
                            </span>
                        </label>

                        {canResetPassword && (
                            <Link
                                href={route('password.request')}
                                className="text-xs text-indigo-400 hover:text-indigo-300 transition-colors font-medium"
                            >
                                Forgot password?
                            </Link>
                        )}
                    </div>

                    <div className="pt-2">
                        <button
                            type="submit"
                            disabled={processing}
                            className="relative w-full overflow-hidden flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-violet-600 px-4 py-3 text-sm font-bold text-white shadow-xl shadow-indigo-600/30 transition-all duration-200 hover:shadow-indigo-500/50 hover:scale-[1.01] active:scale-[0.98] disabled:opacity-50"
                        >
                            {/* Shimmer Effect */}
                            <div className="absolute inset-0 bg-[linear-gradient(115deg,transparent_20%,rgba(255,255,255,0.25)_45%,transparent_70%)] bg-[length:200%_100%] animate-pulse" />
                            {processing && (
                                <svg
                                    className="h-4 w-4 animate-spin text-white"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                >
                                    <circle
                                        className="opacity-25"
                                        cx="12"
                                        cy="12"
                                        r="10"
                                        stroke="currentColor"
                                        strokeWidth="4"
                                    />
                                    <path
                                        className="opacity-75"
                                        fill="currentColor"
                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                                    />
                                </svg>
                            )}
                            <span className="relative z-10">
                                Sign in to ERP Dashboard
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </GuestLayout>
    );
}
