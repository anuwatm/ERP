import Card from '@/Components/UI/Card';
import type { DashboardWidgetState } from './DashboardKpiCard';

export type DashboardBreakdownItem = {
    label: string;
    value: number;
    tone?: 'positive' | 'risk' | 'pending' | 'neutral';
    href?: string;
};

const toneClass = {
    positive: 'bg-emerald-500',
    risk: 'bg-rose-500',
    pending: 'bg-amber-500',
    neutral: 'bg-slate-500',
};

type DashboardBreakdownProps = {
    title: string;
    description: string;
    items: DashboardBreakdownItem[];
    state?: DashboardWidgetState;
    visible?: boolean;
    onRetry?: () => void;
};

export default function DashboardBreakdown({
    title,
    description,
    items,
    state = 'ready',
    visible = true,
    onRetry,
}: DashboardBreakdownProps) {
    if (!visible) return null;

    if (state !== 'ready') {
        const message =
            state === 'loading'
                ? 'Loading…'
                : state === 'unavailable'
                  ? 'Unavailable'
                  : 'Could not load';
        return (
            <Card title={title} description={description}>
                <p
                    role={state === 'error' ? 'alert' : 'status'}
                    className="text-sm text-slate-600 dark:text-slate-300"
                >
                    {message}
                </p>
                {state === 'error' && onRetry && (
                    <button
                        type="button"
                        onClick={onRetry}
                        className="mt-3 text-sm font-semibold text-indigo-700 underline dark:text-indigo-300"
                    >
                        Retry
                    </button>
                )}
            </Card>
        );
    }

    const total = items.reduce((sum, item) => sum + Math.max(item.value, 0), 0);

    return (
        <Card title={title} description={description}>
            {total === 0 ? (
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    No data in this period.
                </p>
            ) : (
                <>
                    <div
                        className="flex h-3 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                        aria-hidden="true"
                    >
                        {items.map((item) => (
                            <div
                                key={item.label}
                                className={toneClass[item.tone ?? 'neutral']}
                                style={{
                                    width: `${(Math.max(item.value, 0) / total) * 100}%`,
                                }}
                            />
                        ))}
                    </div>
                    <ul className="mt-4 space-y-2 text-sm">
                        {items.map((item) => (
                            <li
                                key={item.label}
                                className="flex items-center justify-between gap-3"
                            >
                                <span className="flex min-w-0 items-center gap-2">
                                    <span
                                        className={`h-2.5 w-2.5 rounded-full ${toneClass[item.tone ?? 'neutral']}`}
                                        aria-hidden="true"
                                    />
                                    {item.href ? (
                                        <a
                                            href={item.href}
                                            className="truncate font-semibold text-indigo-700 underline focus:outline-none focus:ring-2 focus:ring-indigo-500 dark:text-indigo-300"
                                            aria-label={`View ${item.label}`}
                                        >
                                            {item.label}
                                        </a>
                                    ) : (
                                        <span className="truncate">
                                            {item.label}
                                        </span>
                                    )}
                                </span>
                                <span className="font-semibold">
                                    {item.value}
                                </span>
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </Card>
    );
}
