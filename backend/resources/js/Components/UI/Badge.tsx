import { ReactNode } from 'react';

export type BadgeVariant =
    'success' | 'warning' | 'danger' | 'info' | 'purple' | 'neutral';

interface BadgeProps {
    children: ReactNode;
    variant?: BadgeVariant;
    size?: 'sm' | 'md';
    dot?: boolean;
}

const variantStyles: Record<BadgeVariant, string> = {
    success: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
    warning: 'bg-amber-50 text-amber-700 border-amber-200/80',
    danger: 'bg-rose-50 text-rose-700 border-rose-200/80',
    info: 'bg-indigo-50 text-indigo-700 border-indigo-200/80',
    purple: 'bg-violet-50 text-violet-700 border-violet-200/80',
    neutral: 'bg-slate-100 text-slate-700 border-slate-200',
};

const dotStyles: Record<BadgeVariant, string> = {
    success: 'bg-emerald-500',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    info: 'bg-indigo-500',
    purple: 'bg-violet-500',
    neutral: 'bg-slate-400',
};

export default function Badge({
    children,
    variant = 'neutral',
    size = 'md',
    dot = false,
}: BadgeProps) {
    const sizeStyle =
        size === 'sm' ? 'px-2 py-0.5 text-xs' : 'px-2.5 py-1 text-xs';

    return (
        <span
            className={`inline-flex items-center gap-1.5 font-medium border rounded-full ${variantStyles[variant]} ${sizeStyle}`}
        >
            {dot && (
                <span
                    className={`h-1.5 w-1.5 rounded-full ${dotStyles[variant]}`}
                />
            )}
            {children}
        </span>
    );
}
