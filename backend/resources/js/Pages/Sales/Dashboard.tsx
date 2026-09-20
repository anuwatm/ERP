import {
    DashboardChart,
    DashboardFilterBar,
    DashboardKpiCard,
    DashboardLayout,
    DashboardList,
} from '@/Components/Dashboard';
import type { DashboardFilter } from '@/Components/Dashboard';
import Card from '@/Components/UI/Card';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

type Summary = {
    customers: number | null;
    active_customers: number | null;
    open_deals: number | null;
    new_deals: number | null;
    won_deals: number | null;
    lost_deals: number | null;
    win_rate: number | null;
    follow_ups_today: number | null;
    stale_deals: number | null;
};
type MoneyRow = { currency: string; value: number };
type PipelineRow = MoneyRow & { stage: string; count: number };
type OwnerRow = MoneyRow & {
    owner: string;
    owner_id: string | null;
    count: number;
};
type FollowUp = {
    id: string;
    subject: string | null;
    entity_type: string;
    entity_id: string;
    customer_id: string | null;
    follow_up_at: string;
};
type StaleDeal = {
    id: string;
    title: string;
    stage: string;
    created_at: string;
    last_activity_at: string | null;
};
type ExpiringQuotation = {
    id: string;
    quotation_no: string;
    valid_until: string;
};

export default function SalesDashboard({
    filters,
    summary,
    pipelineByStage,
    wonValues,
    topOwners,
    followUps,
    staleDeals,
    expiringQuotations,
    visibility,
}: {
    filters: DashboardFilter;
    summary: Summary;
    pipelineByStage: PipelineRow[];
    wonValues: MoneyRow[];
    topOwners: OwnerRow[];
    followUps: FollowUp[];
    staleDeals: StaleDeal[];
    expiringQuotations: ExpiringQuotation[];
    visibility: { deals: boolean; customers: boolean; quotations: boolean };
}) {
    const periodLabel =
        filters.period === 'all_time'
            ? 'All time'
            : `${filters.from} – ${filters.to}`;
    const stageCounts = Object.entries(
        pipelineByStage.reduce<Record<string, number>>((counts, row) => {
            counts[row.stage] = (counts[row.stage] ?? 0) + row.count;
            return counts;
        }, {}),
    ).map(([label, value]) => ({ label, value }));
    const range: { from?: string; to?: string } =
        filters.period === 'all_time'
            ? {}
            : { from: filters.from, to: filters.to };
    const cards = [
        {
            question: 'Customers',
            value: summary.customers,
            visible: visibility.customers,
            context: 'Current snapshot',
        },
        {
            question: 'Active Customers',
            value: summary.active_customers,
            visible: visibility.customers,
            context: 'Current snapshot',
        },
        {
            question: 'Open Deals',
            value: summary.open_deals,
            visible: visibility.deals,
            context: 'Current snapshot',
        },
        {
            question: 'New Deals',
            value: summary.new_deals,
            visible: visibility.deals,
            context: periodLabel,
        },
        {
            question: 'Won Deals',
            value: summary.won_deals,
            visible: visibility.deals,
            context: periodLabel,
        },
        {
            question: 'Lost Deals',
            value: summary.lost_deals,
            visible: visibility.deals,
            context: periodLabel,
        },
        {
            question: 'Win Rate',
            value: summary.win_rate === null ? 'N/A' : `${summary.win_rate}%`,
            visible: visibility.deals,
            context: `Won / closed deals · ${periodLabel}`,
        },
        {
            question: 'Follow-ups Today',
            value: summary.follow_ups_today,
            visible: visibility.deals,
            context: 'Incomplete activities due today',
        },
        {
            question: 'Stale Deals',
            value: summary.stale_deals,
            visible: visibility.deals,
            context: 'Open, at least 7 days old, no activity in 7 days',
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Sales Dashboard" />
            <DashboardLayout
                title="Sales Dashboard"
                description="Pipeline, closed deals and work requiring follow-up."
            >
                <DashboardFilterBar
                    routeName="sales.dashboard"
                    value={filters}
                />
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards
                        .filter((card) => card.visible)
                        .map((card) => (
                            <DashboardKpiCard
                                key={card.question}
                                question={card.question}
                                value={card.value ?? 'N/A'}
                                context={card.context}
                                tone={
                                    card.question === 'Stale Deals' ||
                                    card.question === 'Lost Deals'
                                        ? 'risk'
                                        : 'neutral'
                                }
                            />
                        ))}
                </div>
                {visibility.deals && (
                    <nav
                        aria-label="Sales KPI details"
                        className="flex flex-wrap gap-4 text-sm"
                    >
                        <Link
                            className="text-indigo-700 underline dark:text-indigo-300"
                            href={route('deals.index', {
                                created_from: range.from,
                                created_to: range.to,
                            })}
                        >
                            View new deals
                        </Link>
                        <Link
                            className="text-indigo-700 underline dark:text-indigo-300"
                            href={route('deals.index', {
                                stage: 'won',
                                won_from: range.from,
                                won_to: range.to,
                            })}
                        >
                            View won deals
                        </Link>
                        <Link
                            className="text-indigo-700 underline dark:text-indigo-300"
                            href={route('deals.index', {
                                stage: 'lost',
                                lost_from: range.from,
                                lost_to: range.to,
                            })}
                        >
                            View lost deals
                        </Link>
                    </nav>
                )}
                {visibility.deals && (
                    <>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card
                                title="Open Pipeline"
                                description="Current open deals; amounts remain separate by currency"
                            >
                                <MoneyList
                                    rows={pipelineByStage.map((row) => ({
                                        id: `${row.stage}-${row.currency}`,
                                        label: `${row.stage} · ${row.count} deals`,
                                        currency: row.currency,
                                        value: row.value,
                                        routeParameters: {
                                            stage: row.stage,
                                            currency:
                                                row.currency === 'Unspecified'
                                                    ? '__unspecified'
                                                    : row.currency,
                                        },
                                    }))}
                                />
                            </Card>
                            <Card
                                title="Won Deal Value"
                                description={`Closed in ${periodLabel}; amounts remain separate by currency`}
                            >
                                <MoneyList
                                    rows={wonValues.map((row) => ({
                                        id: row.currency,
                                        label: 'Won',
                                        ...row,
                                        routeParameters: {
                                            stage: 'won',
                                            won_from: range.from,
                                            won_to: range.to,
                                            currency:
                                                row.currency === 'Unspecified'
                                                    ? '__unspecified'
                                                    : row.currency,
                                        },
                                    }))}
                                />
                            </Card>
                            <Card
                                title="Top Sales Owners"
                                description="Open deals by owner and currency; ranked by deal count"
                            >
                                <MoneyList
                                    rows={topOwners.map((row) => ({
                                        id: `${row.owner_id}-${row.currency}`,
                                        label: `${row.owner} · ${row.count} deals`,
                                        currency: row.currency,
                                        value: row.value,
                                        routeParameters: {
                                            owner_id:
                                                row.owner_id ?? '__unassigned',
                                            currency:
                                                row.currency === 'Unspecified'
                                                    ? '__unspecified'
                                                    : row.currency,
                                        },
                                    }))}
                                />
                            </Card>
                            <DashboardChart
                                title="Deal Stage"
                                description="Current open deal count by stage"
                                data={stageCounts}
                                kind="bar"
                                primaryLabel="Deals"
                            />
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            <DashboardList
                                title="Follow-ups Today"
                                description="Incomplete activities due today"
                                items={followUps.map((item) => ({
                                    id: item.id,
                                    title: item.subject || 'Follow-up',
                                    detail: item.follow_up_at,
                                    ...(item.entity_type === 'deal'
                                        ? {
                                              routeName: 'deals.index',
                                              routeParameters: {
                                                  deal_id: item.entity_id,
                                              },
                                          }
                                        : item.customer_id
                                          ? {
                                                routeName: 'customers.index',
                                                routeParameters: {
                                                    customer_id:
                                                        item.customer_id,
                                                },
                                            }
                                          : {}),
                                }))}
                                emptyMessage="No follow-ups due today."
                            />
                            <DashboardList
                                title="Stale Deals"
                                description="Old open deals with no activity in the last 7 days"
                                items={staleDeals.map((item) => ({
                                    id: item.id,
                                    title: item.title,
                                    detail: `${item.stage} · ${item.last_activity_at ? `Last activity ${item.last_activity_at}` : 'No activity'} · created ${item.created_at}`,
                                    routeName: 'deals.index',
                                    routeParameters: { deal_id: item.id },
                                }))}
                                emptyMessage="No stale deals."
                            />
                        </div>
                    </>
                )}
                <DashboardList
                    visible={visibility.quotations}
                    title="Expiring Quotations"
                    description="Sent quotations expiring within 7 days"
                    items={expiringQuotations.map((item) => ({
                        id: item.id,
                        title: item.quotation_no,
                        detail: `Valid until ${item.valid_until}`,
                        routeName: 'quotations.index',
                        routeParameters: { quotation_id: item.id },
                    }))}
                    emptyMessage="No quotations expiring soon."
                />
            </DashboardLayout>
        </AuthenticatedLayout>
    );
}

function MoneyList({
    rows,
}: {
    rows: {
        id: string;
        label: string;
        currency: string;
        value: number;
        routeParameters?: Record<string, string | null | undefined>;
    }[];
}) {
    if (rows.length === 0)
        return <p className="text-sm text-slate-500">No data for this view.</p>;
    return (
        <ul className="divide-y divide-slate-100 dark:divide-slate-800">
            {rows.map((row) => (
                <li
                    key={row.id}
                    className="flex justify-between gap-3 py-2 text-sm"
                >
                    {row.routeParameters ? (
                        <Link
                            className="text-indigo-700 underline dark:text-indigo-300"
                            href={route('deals.index', row.routeParameters)}
                        >
                            {row.label}
                        </Link>
                    ) : (
                        <span>{row.label}</span>
                    )}
                    <span className="font-semibold">
                        {row.currency} {row.value.toLocaleString()}
                    </span>
                </li>
            ))}
        </ul>
    );
}
