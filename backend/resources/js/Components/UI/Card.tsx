import { ReactNode } from 'react';

interface CardProps {
    children: ReactNode;
    title?: string;
    description?: string;
    action?: ReactNode;
    className?: string;
    headerClassName?: string;
    bodyClassName?: string;
}

export default function Card({
    children,
    title,
    description,
    action,
    className = '',
    headerClassName = '',
    bodyClassName = 'p-6',
}: CardProps) {
    return (
        <div
            className={`rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/80 shadow-subtle text-slate-900 dark:text-white transition-colors duration-200 ${className}`}
        >
            {(title || action) && (
                <div
                    className={`flex items-center justify-between border-b border-slate-100 dark:border-slate-800 px-6 py-4 ${headerClassName}`}
                >
                    <div>
                        {title && (
                            <h3 className="font-bold text-slate-900 dark:text-white">
                                {title}
                            </h3>
                        )}
                        {description && (
                            <p className="mt-0.5 text-xs text-slate-500 dark:text-slate-100 font-medium">
                                {description}
                            </p>
                        )}
                    </div>
                    {action && <div>{action}</div>}
                </div>
            )}
            <div className={bodyClassName}>{children}</div>
        </div>
    );
}
