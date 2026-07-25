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
            className={`rounded-xl border border-slate-200/80 bg-white shadow-subtle ${className}`}
        >
            {(title || action) && (
                <div
                    className={`flex items-center justify-between border-b border-slate-100 px-6 py-4 ${headerClassName}`}
                >
                    <div>
                        {title && (
                            <h3 className="font-semibold text-slate-800">
                                {title}
                            </h3>
                        )}
                        {description && (
                            <p className="mt-0.5 text-xs text-slate-500">
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
