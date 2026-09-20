import Card from '@/Components/UI/Card';
import { Link } from '@inertiajs/react';
import { ReactNode } from 'react';
import type { DashboardWidgetState } from './DashboardKpiCard';

export type DashboardListItem = {
    id: string;
    title: string;
    detail: string;
    routeName?: string;
    routeParameters?: Record<
        string,
        string | number | boolean | null | undefined
    >;
    status?: ReactNode;
};

type DashboardListProps = {
    title: string;
    description: string;
    items: DashboardListItem[];
    emptyMessage: string;
    state?: DashboardWidgetState;
    unavailableMessage?: string;
    errorMessage?: string;
    onRetry?: () => void;
    visible?: boolean;
};

export default function DashboardList({
    title,
    description,
    items,
    emptyMessage,
    state = 'ready',
    unavailableMessage = 'This data is not available.',
    errorMessage = 'Could not load this list.',
    onRetry,
    visible = true,
}: DashboardListProps) {
    if (!visible) return null;

    if (state !== 'ready') {
        const message =
            state === 'loading'
                ? 'Loading…'
                : state === 'unavailable'
                  ? unavailableMessage
                  : errorMessage;

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

    return (
        <Card title={title} description={description}>
            {items.length === 0 ? (
                <p className="text-sm text-slate-600 dark:text-slate-300">
                    {emptyMessage}
                </p>
            ) : (
                <ul className="divide-y divide-slate-100 dark:divide-slate-800">
                    {items.map((item) => (
                        <li key={item.id} className="py-3 first:pt-0 last:pb-0">
                            {item.routeName ? (
                                <Link
                                    href={route(
                                        item.routeName,
                                        item.routeParameters,
                                    )}
                                    className="block rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500"
                                >
                                    <ListContent item={item} />
                                </Link>
                            ) : (
                                <ListContent item={item} />
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </Card>
    );
}

function ListContent({ item }: { item: DashboardListItem }) {
    return (
        <div className="flex items-start justify-between gap-3">
            <div className="min-w-0">
                <p className="truncate text-sm font-semibold text-slate-900 dark:text-white">
                    {item.title}
                </p>
                <p className="mt-0.5 text-xs text-slate-600 dark:text-slate-300">
                    {item.detail}
                </p>
            </div>
            {item.status && <div className="shrink-0">{item.status}</div>}
        </div>
    );
}
