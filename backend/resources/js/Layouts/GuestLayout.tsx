import { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <div className="relative min-h-screen bg-slate-950 flex flex-col md:flex-row antialiased selection:bg-indigo-500 selection:text-white overflow-hidden">
            {/* Ambient Background Animated Orbs */}
            <div className="pointer-events-none fixed inset-0 z-0 overflow-hidden">
                <div className="absolute -top-32 -left-32 h-[500px] w-[500px] rounded-full bg-gradient-to-br from-indigo-600/30 to-purple-600/20 blur-[120px] animate-float-slow" />
                <div className="absolute -bottom-40 -right-40 h-[600px] w-[600px] rounded-full bg-gradient-to-tl from-violet-600/30 to-indigo-600/20 blur-[140px] animate-float-reverse" />
                <div className="absolute top-1/2 left-1/3 -translate-y-1/2 h-[450px] w-[450px] rounded-full bg-sky-500/10 blur-[130px] animate-pulse-glow" />
                {/* Subtle Grid Background Overlay */}
                <div className="absolute inset-0 bg-[linear-gradient(to_right,#1e293b15_1px,transparent_1px),linear-gradient(to_bottom,#1e293b15_1px,transparent_1px)] bg-[size:4rem_4rem]" />
            </div>

            {/* Left Hero Showcase Section (Desktop) */}
            <div className="relative z-10 hidden md:flex md:w-1/2 lg:w-7/12 flex-col justify-between p-12 lg:p-16 text-white border-r border-white/5">
                {/* Brand Header */}
                <div className="flex items-center gap-3.5">
                    <div className="relative flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-indigo-500 to-indigo-700 font-bold text-white shadow-xl shadow-indigo-500/30 text-lg border border-white/20">
                        <span className="relative z-10">ERP</span>
                        <div className="absolute inset-0 rounded-2xl bg-indigo-400/30 blur-md animate-pulse" />
                    </div>
                    <div>
                        <div className="font-bold tracking-wider text-white text-base font-sans">
                            LOCAL DEVINE
                        </div>
                        <div className="text-[11px] text-indigo-300 font-semibold tracking-wider uppercase">
                            Enterprise Management System
                        </div>
                    </div>
                </div>

                {/* Main Hero Content */}
                <div className="my-auto max-w-xl space-y-8 py-10">
                    <div className="inline-flex items-center gap-2.5 rounded-full border border-indigo-400/30 bg-indigo-500/10 px-4 py-1.5 text-xs font-semibold text-indigo-300 backdrop-blur-md">
                        <span className="relative flex h-2 w-2">
                            <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                            <span className="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
                        </span>
                        Phase 1 Ready & High-Performance Architecture
                    </div>

                    <h1 className="text-4xl font-black tracking-tight text-white lg:text-5xl font-sans leading-[1.15]">
                        Streamlined Enterprise Operations &{' '}
                        <span className="bg-gradient-to-r from-indigo-400 via-violet-300 to-sky-300 bg-clip-text text-transparent">
                            Smart Workspace
                        </span>
                    </h1>

                    <p className="text-slate-300 text-base leading-relaxed font-normal">
                        Experience precision in organizational structure management, multi-tier RBAC security, audit logging, and modern web interfaces designed for speed and clarity.
                    </p>

                    {/* Glassmorphic Feature Showcase Cards */}
                    <div className="grid grid-cols-3 gap-4 pt-2">
                        <div className="glass-card glass-card-hover rounded-2xl p-4">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-500/20 text-indigo-300 mb-2 border border-indigo-400/20">
                                🏢
                            </div>
                            <div className="text-sm font-bold text-white">Multi-Org</div>
                            <div className="mt-0.5 text-xs text-slate-400">Branch & Dept Chain</div>
                        </div>

                        <div className="glass-card glass-card-hover rounded-2xl p-4">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/20 text-emerald-300 mb-2 border border-emerald-400/20">
                                🛡️
                            </div>
                            <div className="text-sm font-bold text-white">RBAC 7 Roles</div>
                            <div className="mt-0.5 text-xs text-slate-400">Permission Matrix</div>
                        </div>

                        <div className="glass-card glass-card-hover rounded-2xl p-4">
                            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500/20 text-violet-300 mb-2 border border-violet-400/20">
                                📜
                            </div>
                            <div className="text-sm font-bold text-white">Audit Trail</div>
                            <div className="mt-0.5 text-xs text-slate-400">Immutable Logs</div>
                        </div>
                    </div>
                </div>

                {/* Footer Credits */}
                <div className="flex items-center justify-between text-xs text-slate-400 border-t border-white/5 pt-6">
                    <div>&copy; 2026 Local Devine ERP Co., Ltd.</div>
                    <div className="flex items-center gap-1.5">
                        <span className="h-1.5 w-1.5 rounded-full bg-emerald-500" />
                        System Operational
                    </div>
                </div>
            </div>

            {/* Right Interactive Form Section */}
            <div className="relative z-10 flex-1 flex flex-col justify-center items-center p-6 sm:p-10 md:p-12">
                {/* Mobile Header Branding */}
                <div className="md:hidden mb-8 flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-indigo-700 font-bold text-white shadow-lg text-base border border-white/20">
                        ERP
                    </div>
                    <div>
                        <div className="font-bold text-white text-sm">LOCAL DEVINE ERP</div>
                        <div className="text-[10px] text-indigo-400 uppercase font-semibold">Enterprise Suite</div>
                    </div>
                </div>

                <div className="w-full max-w-md">
                    {children}
                </div>
            </div>
        </div>
    );
}
