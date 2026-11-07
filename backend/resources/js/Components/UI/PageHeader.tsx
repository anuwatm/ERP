import { ReactNode } from 'react';

interface PageHeaderProps {
    title: string;
    description?: string;
    actions?: ReactNode;
    breadcrumbs?: { label: string; href?: string }[];
}

export default function PageHeader({
    title,
    description,
    actions,
    breadcrumbs,
}: PageHeaderProps) {
    return (
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div className="min-w-0">
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <nav
                        aria-label="Breadcrumb"
                        className="mb-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-600 dark:text-slate-300"
                    >
                        {breadcrumbs.map((crumb, idx) => (
                            <span key={idx} className="flex items-center gap-2">
                                {idx > 0 && (
                                    <span
                                        aria-hidden="true"
                                        className="text-slate-400"
                                    >
                                        /
                                    </span>
                                )}
                                {crumb.href ? (
                                    <a
                                        href={crumb.href}
                                        className="underline-offset-2 hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-teal-700 dark:focus-visible:outline-teal-300"
                                    >
                                        {crumb.label}
                                    </a>
                                ) : (
                                    <span
                                        aria-current={
                                            idx === breadcrumbs.length - 1
                                                ? 'page'
                                                : undefined
                                        }
                                        className="font-semibold text-erp-ink"
                                    >
                                        {crumb.label}
                                    </span>
                                )}
                            </span>
                        ))}
                    </nav>
                )}
                <h1 className="break-words text-2xl font-bold tracking-tight text-erp-ink sm:text-3xl">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1.5 max-w-prose text-sm text-slate-600 dark:text-slate-300">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
