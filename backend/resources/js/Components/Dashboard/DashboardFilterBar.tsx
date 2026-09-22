import Card from '@/Components/UI/Card';
import { router } from '@inertiajs/react';
import { FormEvent, useId, useState } from 'react';

export type DashboardPeriod =
    'all_time' | 'today' | 'this_week' | 'month' | 'year' | 'custom';

export type DashboardFilter = {
    period: DashboardPeriod;
    from?: string;
    to?: string;
};

type DashboardFilterBarProps = {
    routeName: string;
    value: DashboardFilter;
};

export default function DashboardFilterBar({
    routeName,
    value,
}: DashboardFilterBarProps) {
    const [filter, setFilter] = useState<DashboardFilter>(value);
    const [error, setError] = useState<string | null>(null);
    const errorId = useId();
    const isCustom = filter.period === 'custom';

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (filter.period === 'custom') {
            if (!filter.from || !filter.to) {
                setError('Choose both dates for a custom range.');
                return;
            }

            if (filter.from > filter.to) {
                setError('The start date must be on or before the end date.');
                return;
            }
        }

        setError(null);
        router.get(route(routeName), filter, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    return (
        <Card bodyClassName="p-4">
            <form
                onSubmit={submit}
                noValidate
                className="grid gap-3 sm:grid-cols-[minmax(12rem,1fr)_minmax(10rem,1fr)_minmax(10rem,1fr)_auto] sm:items-end"
            >
                <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <span>Period</span>
                    <select
                        value={filter.period}
                        onChange={(event) =>
                            setFilter((current) => {
                                setError(null);
                                return {
                                    ...current,
                                    period: event.target
                                        .value as DashboardPeriod,
                                };
                            })
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950"
                    >
                        <option value="all_time">All time</option>
                        <option value="today">Today</option>
                        <option value="this_week">This week</option>
                        <option value="month">Month</option>
                        <option value="year">Year</option>
                        <option value="custom">Custom range</option>
                    </select>
                </label>
                <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <span>From</span>
                    <input
                        type="date"
                        value={filter.from ?? ''}
                        disabled={!isCustom}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(event) =>
                            setFilter((current) => ({
                                ...current,
                                from: event.target.value,
                            }))
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:disabled:bg-slate-800"
                    />
                </label>
                <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                    <span>To</span>
                    <input
                        type="date"
                        value={filter.to ?? ''}
                        disabled={!isCustom}
                        aria-invalid={Boolean(error)}
                        aria-describedby={error ? errorId : undefined}
                        onChange={(event) =>
                            setFilter((current) => ({
                                ...current,
                                to: event.target.value,
                            }))
                        }
                        className="w-full rounded-md border-slate-300 text-sm shadow-sm disabled:bg-slate-100 dark:border-slate-700 dark:bg-slate-950 dark:disabled:bg-slate-800"
                    />
                </label>
                <button
                    type="submit"
                    className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700 dark:bg-white dark:text-slate-950"
                >
                    Apply
                </button>
            </form>
            {error && (
                <p
                    id={errorId}
                    role="alert"
                    className="mt-2 text-sm text-rose-700 dark:text-rose-300"
                >
                    {error}
                </p>
            )}
        </Card>
    );
}
