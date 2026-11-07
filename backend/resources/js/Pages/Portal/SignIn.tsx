import ThemeToggle from '@/Components/UI/ThemeToggle';
import { Head } from '@inertiajs/react';

export default function PortalSignIn() {
    return (
        <>
            <Head title="ERP Portal" />
            <main className="erp-canvas min-h-screen px-4 py-8 text-erp-ink">
                <div className="mx-auto max-w-md rounded-xl border border-slate-200 bg-white dark:border-slate-700 dark:bg-slate-900 p-6">
                    <div className="flex items-center justify-between gap-3">
                        <p className="text-sm font-medium text-teal-800 dark:text-teal-200">
                            ERP Portal
                        </p>
                        <ThemeToggle />
                    </div>
                    <h1 className="mt-2 text-2xl font-bold">
                        Access by email link
                    </h1>
                    <p className="mt-3 text-sm text-slate-600 dark:text-slate-300">
                        Use the secure link sent by your ERP contact. The link
                        expires after 30 minutes and can be used once.
                    </p>
                </div>
            </main>
        </>
    );
}
