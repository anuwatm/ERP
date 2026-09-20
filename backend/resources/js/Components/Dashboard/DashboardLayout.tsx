import { ReactNode } from 'react';

type DashboardLayoutProps = {
    title: string;
    description: string;
    children: ReactNode;
    updatedAt?: string;
};

export default function DashboardLayout({
    title,
    description,
    children,
    updatedAt,
}: DashboardLayoutProps) {
    return (
        <section className="space-y-6" aria-labelledby="dashboard-title">
            <header className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1
                        id="dashboard-title"
                        className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white"
                    >
                        {title}
                    </h1>
                    <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">
                        {description}
                    </p>
                </div>
                {updatedAt && (
                    <p className="text-xs text-slate-500 dark:text-slate-400">
                        Updated {updatedAt}
                    </p>
                )}
            </header>
            {children}
        </section>
    );
}
