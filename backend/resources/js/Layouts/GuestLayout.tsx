import ThemeToggle from '@/Components/UI/ThemeToggle';
import { PageProps } from '@/Types/auth';
import { usePage } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    const org = usePage<PageProps>().props.org;

    const companyName = org?.name || 'Demo ERP Co., Ltd.';
    const legalName = org?.legal_name || 'Enterprise Management Portal';

    return (
        <div className="relative min-h-screen bg-slate-950 flex flex-col md:flex-row antialiased selection:bg-indigo-500 selection:text-white overflow-hidden">
            {/* Top Right Floating Theme Toggle */}
            <div className="absolute top-5 right-5 z-30">
                <ThemeToggle />
            </div>
            {/* Soft Ambient Glow Lighting */}
            <div className="pointer-events-none fixed inset-0 z-0 overflow-hidden">
                <div className="absolute -top-40 -left-40 h-[650px] w-[650px] rounded-full bg-indigo-600/15 blur-[150px] animate-pulse-glow" />
                <div className="absolute -bottom-40 -right-40 h-[650px] w-[650px] rounded-full bg-violet-600/10 blur-[160px]" />
            </div>

            {/* Left Hero Section - Featuring Dynamic Company Name from Database */}
            <div className="relative z-10 hidden md:flex md:w-1/2 lg:w-7/12 flex-col justify-between p-12 lg:p-20 text-white border-r border-white/5">
                {/* Top Badge Tag */}
                <div className="inline-flex items-center gap-2 self-start rounded-full border border-indigo-500/20 bg-indigo-500/10 px-3.5 py-1 text-xs font-semibold text-indigo-300 backdrop-blur-md">
                    <span className="h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
                    Enterprise Management System
                </div>

                {/* Main Hero Showcase - Prominent Company Logo & Big Name from Database */}
                <div className="my-auto max-w-xl space-y-6 py-8">
                    {/* Big Company Logo Icon */}
                    <div className="flex items-center gap-5">
                        {org?.logo_url ? (
                            <div className="relative group/herologo">
                                <div className="absolute -inset-1 rounded-3xl bg-gradient-to-r from-indigo-500 to-violet-600 opacity-50 blur-xl group-hover/herologo:opacity-80 transition duration-300" />
                                <img
                                    src={org.logo_url}
                                    alt={companyName}
                                    className="relative h-24 w-24 lg:h-32 lg:w-32 rounded-3xl object-contain bg-slate-900/95 p-3.5 shadow-2xl shadow-indigo-600/40 border border-white/20"
                                />
                            </div>
                        ) : (
                            <div className="relative flex h-24 w-24 lg:h-32 lg:w-32 items-center justify-center rounded-3xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-violet-600 font-black text-white shadow-2xl shadow-indigo-600/40 text-4xl font-sans border border-white/30 ring-4 ring-indigo-500/20">
                                <span>ERP</span>
                                <div className="absolute inset-0 rounded-3xl bg-indigo-400/20 blur-xl animate-pulse" />
                            </div>
                        )}
                        <div>
                            <div className="text-xs font-bold tracking-widest text-indigo-400 uppercase">
                                Verified Tenant Profile
                            </div>
                            <div className="text-sm font-semibold text-slate-300 mt-1">
                                Organization ID:{' '}
                                {org?.id
                                    ? `#${org.id.substring(0, 8)}`
                                    : 'System Core'}
                            </div>
                        </div>
                    </div>

                    {/* Big Bold Company Name Header (From Database) */}
                    <div>
                        <h1 className="text-4xl lg:text-5xl font-black tracking-tight text-white font-sans leading-tight">
                            {companyName}
                        </h1>
                        <p className="mt-2 text-xs font-bold text-slate-200 tracking-wider uppercase">
                            {legalName}
                        </p>
                    </div>

                    {/* Clean Key Stats Summary */}
                    <div className="pt-4 grid grid-cols-3 gap-6 border-t border-slate-800/80">
                        <div>
                            <div className="text-xl font-bold text-white font-sans">
                                Multi-Org
                            </div>
                            <div className="text-xs text-slate-200 mt-0.5 font-medium">
                                Structure & Chain
                            </div>
                        </div>
                        <div>
                            <div className="text-xl font-bold text-white font-sans">
                                7 Roles
                            </div>
                            <div className="text-xs text-slate-200 mt-0.5 font-medium">
                                RBAC Matrix
                            </div>
                        </div>
                        <div>
                            <div className="text-xl font-bold text-white font-sans">
                                100% Logged
                            </div>
                            <div className="text-xs text-slate-200 mt-0.5 font-medium">
                                Audit Trail
                            </div>
                        </div>
                    </div>
                </div>

                {/* Footer Copyright */}
                <div className="text-xs text-slate-300 font-medium">
                    &copy; 2026 {companyName}. All rights reserved.
                </div>
            </div>

            {/* Right Interactive Form Section */}
            <div className="relative z-10 flex-1 flex flex-col justify-center items-center p-6 sm:p-10 md:p-12">
                {/* Mobile Header Branding */}
                <div className="md:hidden mb-8 flex items-center gap-3">
                    {org?.logo_url ? (
                        <img
                            src={org.logo_url}
                            alt={companyName}
                            className="h-12 w-12 rounded-xl object-contain bg-white/10 p-1 shadow-lg border border-white/20"
                        />
                    ) : (
                        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 font-bold text-white shadow-lg text-lg border border-white/20">
                            ERP
                        </div>
                    )}
                    <div>
                        <div className="font-black text-white text-base max-w-[200px] truncate">
                            {companyName}
                        </div>
                        <div className="text-[10px] text-indigo-400 uppercase font-semibold">
                            Enterprise ERP Suite
                        </div>
                    </div>
                </div>

                <div className="w-full max-w-md">{children}</div>
            </div>
        </div>
    );
}
