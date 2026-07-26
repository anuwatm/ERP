import { ReactNode } from 'react';

interface StatCardProps {
    title: string;
    value: string | number;
    icon?: ReactNode;
    subtitle?: string;
    trend?: {
        label: string;
        type: 'up' | 'down' | 'neutral';
    };
    iconBgColor?: string;
}

export default function StatCard({
    title,
    value,
    icon,
    subtitle,
    trend,
    iconBgColor = 'bg-indigo-50 dark:bg-indigo-950/70 text-indigo-600 dark:text-indigo-300',
}: StatCardProps) {
    return (
        <div className="group relative overflow-hidden rounded-xl border border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900/80 p-5 shadow-subtle transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 dark:hover:border-slate-700 hover:shadow-card">
            <div className="flex items-center justify-between">
                <span className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-white">
                    {title}
                </span>
                {icon && (
                    <div
                        className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-lg ${iconBgColor} transition-transform group-hover:scale-105`}
                    >
                        {icon}
                    </div>
                )}
            </div>

            <div className="mt-3 flex items-baseline justify-between gap-2">
                <div className="text-2xl font-bold tracking-tight text-slate-900 dark:text-white md:text-3xl font-sans">
                    {value}
                </div>
                {trend && (
                    <span
                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-semibold ${
                            trend.type === 'up'
                                ? 'bg-emerald-50 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300'
                                : trend.type === 'down'
                                  ? 'bg-rose-50 dark:bg-rose-950/80 text-rose-700 dark:text-rose-300'
                                  : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-white'
                        }`}
                    >
                        {trend.label}
                    </span>
                )}
            </div>

            {subtitle && (
                <p className="mt-1 text-xs text-slate-500 dark:text-slate-100 font-medium">
                    {subtitle}
                </p>
            )}
        </div>
    );
}
