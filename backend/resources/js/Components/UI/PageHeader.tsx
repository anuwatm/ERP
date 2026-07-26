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
        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                {breadcrumbs && breadcrumbs.length > 0 && (
                    <nav className="mb-1.5 flex items-center gap-2 text-xs text-slate-500 dark:text-slate-200">
                        {breadcrumbs.map((crumb, idx) => (
                            <span key={idx} className="flex items-center gap-2">
                                {idx > 0 && (
                                    <span className="text-slate-400 dark:text-slate-400">
                                        /
                                    </span>
                                )}
                                {crumb.href ? (
                                    <a
                                        href={crumb.href}
                                        className="hover:text-indigo-600 dark:hover:text-indigo-300 transition-colors"
                                    >
                                        {crumb.label}
                                    </a>
                                ) : (
                                    <span className="text-slate-700 dark:text-white font-bold">
                                        {crumb.label}
                                    </span>
                                )}
                            </span>
                        ))}
                    </nav>
                )}
                <h1 className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white font-sans">
                    {title}
                </h1>
                {description && (
                    <p className="mt-1 text-sm text-slate-500 dark:text-slate-100 font-medium">
                        {description}
                    </p>
                )}
            </div>

            {actions && (
                <div className="flex items-center gap-3">{actions}</div>
            )}
        </div>
    );
}
