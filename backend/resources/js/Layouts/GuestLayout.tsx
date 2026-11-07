import ThemeToggle from '@/Components/UI/ThemeToggle';
import { PropsWithChildren } from 'react';

export default function GuestLayout({ children }: PropsWithChildren) {
    return (
        <main className="erp-canvas min-h-screen text-erp-ink md:grid md:grid-cols-[minmax(15rem,2fr)_minmax(0,3fr)]">
            <header className="erp-rail flex flex-col justify-center px-5 py-5 text-emerald-50 sm:px-8 md:p-12 lg:p-16">
                <div className="max-w-sm">
                    <p className="text-sm font-semibold text-emerald-100">
                        Company OS
                    </p>
                    <p className="mt-1 text-xl font-bold md:mt-4 md:text-4xl">
                        ERP Workspace
                    </p>
                    <p className="mt-3 hidden text-sm leading-relaxed text-emerald-100 md:block">
                        Access your organization, transactions, and reports with
                        your ERP account.
                    </p>
                </div>
            </header>
            <section
                aria-label="Account access"
                className="flex min-w-0 flex-col items-center px-4 py-5 sm:px-8 md:justify-center md:py-12"
            >
                <div className="w-full max-w-md">
                    <div className="mb-4 flex justify-end">
                        <ThemeToggle />
                    </div>
                    <div className="rounded-xl border border-slate-200 bg-white p-5 sm:p-8 dark:border-slate-700 dark:bg-slate-900">
                        {children}
                    </div>
                </div>
            </section>
        </main>
    );
}
