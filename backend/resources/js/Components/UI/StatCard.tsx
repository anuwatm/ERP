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
    iconBgColor = 'bg-indigo-50 text-indigo-600',
}: StatCardProps) {
    return (
        <div className="group relative overflow-hidden rounded-xl border border-slate-200/80 bg-white p-5 shadow-subtle transition-all duration-200 hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-card">
            <div className="flex items-center justify-between">
                <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">
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
                <div className="text-2xl font-bold tracking-tight text-slate-900 md:text-3xl font-sans">
                    {value}
                </div>
                {trend && (
                    <span
                        className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                            trend.type === 'up'
                                ? 'bg-emerald-50 text-emerald-700'
                                : trend.type === 'down'
                                  ? 'bg-rose-50 text-rose-700'
                                  : 'bg-slate-100 text-slate-600'
                        }`}
                    >
                        {trend.label}
                    </span>
                )}
            </div>

            {subtitle && (
                <p className="mt-1 text-xs text-slate-500">{subtitle}</p>
            )}
        </div>
    );
}
