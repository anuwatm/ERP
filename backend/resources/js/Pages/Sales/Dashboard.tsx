import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Summary = {
    customers: number;
    active_customers: number;
    open_deals: number;
    pipeline_value: number;
    won_deals: number;
    lost_deals: number;
    follow_ups_today: number;
    stale_deals: number;
};

type PipelineRow = {
    stage: string;
    count: number;
    value: number;
};

type OwnerRow = {
    owner: string;
    deals_count: number;
    pipeline_value: number;
};

export default function SalesDashboard({
    summary,
    pipelineByStage,
    topOwners,
}: {
    summary: Summary;
    pipelineByStage: PipelineRow[];
    topOwners: OwnerRow[];
}) {
    const cards = [
        {
            title: 'Customers',
            value: summary.customers,
            color: 'bg-indigo-50 text-indigo-600',
        },
        {
            title: 'Active Customers',
            value: summary.active_customers,
            color: 'bg-emerald-50 text-emerald-600',
        },
        {
            title: 'Open Deals',
            value: summary.open_deals,
            color: 'bg-sky-50 text-sky-600',
        },
        {
            title: 'Pipeline Value',
            value: summary.pipeline_value.toLocaleString(),
            color: 'bg-blue-50 text-blue-600',
        },
        {
            title: 'Won Deals',
            value: summary.won_deals,
            color: 'bg-green-50 text-green-600',
        },
        {
            title: 'Lost Deals',
            value: summary.lost_deals,
            color: 'bg-rose-50 text-rose-600',
        },
        {
            title: 'Follow-ups Today',
            value: summary.follow_ups_today,
            color: 'bg-amber-50 text-amber-600',
        },
        {
            title: 'Stale Deals',
            value: summary.stale_deals,
            color: 'bg-orange-50 text-orange-600',
        },
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Sales Dashboard" />

            <div className="space-y-6">
                <PageHeader
                    title="Sales Dashboard"
                    description="CRM summary, pipeline health, follow-ups, and stale deal monitor."
                    actions={<Badge variant="info">Phase 2 Sales Only</Badge>}
                />

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {cards.map((card) => (
                        <StatCard
                            key={card.title}
                            title={card.title}
                            value={card.value}
                            iconBgColor={card.color}
                        />
                    ))}
                </div>

                <SalesActionAlerts summary={summary} />

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card
                        title="Deals Pipeline"
                        description="Deal count and value by stage"
                    >
                        <PipelineFunnel rows={pipelineByStage} />
                        <div className="mt-5">
                            <DataTable
                                data={pipelineByStage}
                                keyExtractor={(row) => row.stage}
                                columns={[
                                    {
                                        header: 'Stage',
                                        accessor: (row) => (
                                            <Badge
                                                variant={
                                                    row.stage === 'won'
                                                        ? 'success'
                                                        : row.stage === 'lost'
                                                          ? 'danger'
                                                          : 'info'
                                                }
                                            >
                                                {row.stage}
                                            </Badge>
                                        ),
                                    },
                                    {
                                        header: 'Deals',
                                        accessor: (row) => row.count,
                                    },
                                    {
                                        header: 'Value',
                                        accessor: (row) =>
                                            row.value.toLocaleString(),
                                    },
                                ]}
                            />
                        </div>
                    </Card>

                    <Card
                        title="Win / Loss Conversion"
                        description="Closed deal outcome mix"
                    >
                        <WonLostDonut summary={summary} />
                    </Card>

                    <Card
                        title="Top Sales Owners"
                        description="Open pipeline ranked by value"
                    >
                        <TopOwnerBars rows={topOwners} />
                        <div className="mt-5">
                            <DataTable
                                data={topOwners}
                                keyExtractor={(row) => row.owner}
                                columns={[
                                    {
                                        header: 'Owner',
                                        accessor: (row) => row.owner,
                                    },
                                    {
                                        header: 'Open Deals',
                                        accessor: (row) => row.deals_count,
                                    },
                                    {
                                        header: 'Pipeline Value',
                                        accessor: (row) =>
                                            row.pipeline_value.toLocaleString(),
                                    },
                                ]}
                            />
                        </div>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function SalesActionAlerts({ summary }: { summary: Summary }) {
    const totalPressure = summary.follow_ups_today + summary.stale_deals;

    if (totalPressure === 0) {
        return (
            <Card
                title="Sales Action Monitor"
                description="Follow-up workload and stale deal pressure"
            >
                <div className="flex min-h-36 items-center justify-center rounded-md border border-emerald-200 bg-emerald-50/70 p-5 text-center dark:border-emerald-900/70 dark:bg-emerald-950/40">
                    <div>
                        <div className="text-3xl font-bold text-emerald-700 dark:text-emerald-100">
                            Clear
                        </div>
                        <div className="mt-1 text-sm font-semibold text-emerald-700/80 dark:text-emerald-200/80">
                            No follow-ups or stale deals need attention
                        </div>
                    </div>
                </div>
            </Card>
        );
    }

    return (
        <Card
            title="Sales Action Monitor"
            description="Follow-up workload and stale deal pressure"
            action={
                <Badge
                    variant={summary.stale_deals > 0 ? 'warning' : 'info'}
                    dot
                >
                    {totalPressure} Actions
                </Badge>
            }
        >
            <div className="grid gap-4 lg:grid-cols-2">
                <SalesActionTile
                    label="Follow-ups Today"
                    value={summary.follow_ups_today}
                    total={totalPressure}
                    tone="amber"
                    description="Activities due today"
                />
                <SalesActionTile
                    label="Stale Deals"
                    value={summary.stale_deals}
                    total={totalPressure}
                    tone="rose"
                    description="Open deals older than 14 days"
                />
            </div>
        </Card>
    );
}

function SalesActionTile({
    label,
    value,
    total,
    tone,
    description,
}: {
    label: string;
    value: number;
    total: number;
    tone: 'amber' | 'rose';
    description: string;
}) {
    const percent = total > 0 ? value / total : 0;
    const toneClass =
        tone === 'amber'
            ? 'border-amber-200 bg-amber-50/70 text-amber-800 dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-100'
            : 'border-rose-200 bg-rose-50/70 text-rose-800 dark:border-rose-900/70 dark:bg-rose-950/40 dark:text-rose-100';
    const barClass = tone === 'amber' ? 'bg-amber-500' : 'bg-rose-500';
    const dotClass = value > 0 ? barClass + ' animate-pulse' : 'bg-slate-300';

    return (
        <div className={'rounded-md border p-4 ' + toneClass}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wide opacity-80">
                        <span
                            className={'h-2.5 w-2.5 rounded-full ' + dotClass}
                        />
                        {label}
                    </div>
                    <div className="mt-1 text-sm font-medium opacity-80">
                        {description}
                    </div>
                </div>
                <div className="font-mono text-3xl font-bold">{value}</div>
            </div>
            <div className="mt-4 h-3 overflow-hidden rounded-full bg-white/70 dark:bg-slate-950/50">
                <div
                    className={
                        'h-full rounded-full transition-all duration-700 ease-out ' +
                        barClass
                    }
                    style={{
                        width:
                            String(Math.max(percent * 100, value > 0 ? 8 : 0)) +
                            '%',
                    }}
                />
            </div>
            <div className="mt-2 text-xs font-semibold opacity-80">
                {(percent * 100).toFixed(1)}% of active sales actions
            </div>
        </div>
    );
}
function PipelineFunnel({ rows }: { rows: PipelineRow[] }) {
    const maxValue = Math.max(...rows.map((row) => row.value), 0);

    if (rows.length === 0 || maxValue === 0) {
        return (
            <div className="flex min-h-48 items-center justify-center rounded-md border border-slate-200/80 bg-slate-50/70 text-sm font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                No pipeline value yet
            </div>
        );
    }

    return (
        <div className="space-y-3 rounded-md border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
            {rows.map((row) => {
                const width = Math.max((row.value / maxValue) * 100, 10);

                return (
                    <div key={row.stage} className="space-y-1">
                        <div className="flex items-center justify-between gap-3 text-xs font-semibold">
                            <span className="capitalize text-slate-700 dark:text-slate-200">
                                {row.stage.replace('_', ' ')}
                            </span>
                            <span className="font-mono text-slate-950 dark:text-white">
                                {row.count} deals / {row.value.toLocaleString()}
                            </span>
                        </div>
                        <div className="h-6 overflow-hidden rounded-md bg-slate-200 dark:bg-slate-800">
                            <div
                                className={`h-full rounded-r-md ${pipelineStageColor(row.stage)} transition-all duration-700 ease-out`}
                                style={{ width: `${width}%` }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function WonLostDonut({ summary }: { summary: Summary }) {
    const totalClosed = summary.won_deals + summary.lost_deals;
    const wonPercent = totalClosed > 0 ? summary.won_deals / totalClosed : 0;
    const lostPercent = totalClosed > 0 ? summary.lost_deals / totalClosed : 0;

    if (totalClosed === 0) {
        return (
            <div className="flex min-h-64 items-center justify-center rounded-md border border-slate-200/80 bg-slate-50/70 text-sm font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                No closed deals yet
            </div>
        );
    }

    return (
        <div className="grid gap-5 md:grid-cols-[180px,1fr] md:items-center">
            <div className="relative mx-auto h-44 w-44">
                <svg viewBox="0 0 42 42" className="h-44 w-44 -rotate-90">
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className="fill-none stroke-slate-100 dark:stroke-slate-800"
                        strokeWidth="6"
                    />
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className="fill-none stroke-emerald-500 transition-all duration-700 ease-out"
                        strokeWidth="6"
                        strokeDasharray={`${wonPercent * 100} ${100 - wonPercent * 100}`}
                    />
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className="fill-none stroke-rose-500 transition-all duration-700 ease-out"
                        strokeWidth="6"
                        strokeDasharray={`${lostPercent * 100} ${100 - lostPercent * 100}`}
                        strokeDashoffset={String(-wonPercent * 100)}
                    />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <div className="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">
                        Win Rate
                    </div>
                    <div className="text-2xl font-bold text-slate-950 dark:text-white">
                        {(wonPercent * 100).toFixed(1)}%
                    </div>
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {totalClosed} closed
                    </div>
                </div>
            </div>
            <div className="grid gap-2">
                <SalesMiniMetric
                    label="Won Deals"
                    value={summary.won_deals}
                    tone="emerald"
                />
                <SalesMiniMetric
                    label="Lost Deals"
                    value={summary.lost_deals}
                    tone="rose"
                />
                <SalesMiniMetric
                    label="Open Deals"
                    value={summary.open_deals}
                    tone="sky"
                />
            </div>
        </div>
    );
}

function TopOwnerBars({ rows }: { rows: OwnerRow[] }) {
    const maxValue = Math.max(...rows.map((row) => row.pipeline_value), 0);

    if (rows.length === 0 || maxValue === 0) {
        return (
            <div className="flex min-h-48 items-center justify-center rounded-md border border-slate-200/80 bg-slate-50/70 text-sm font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                No owner pipeline yet
            </div>
        );
    }

    return (
        <div className="grid gap-3 rounded-md border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
            {rows.map((row) => {
                const width = Math.max(
                    (row.pipeline_value / maxValue) * 100,
                    8,
                );

                return (
                    <div key={row.owner}>
                        <div className="mb-1 flex items-center justify-between gap-3 text-xs font-semibold">
                            <span className="truncate text-slate-700 dark:text-slate-200">
                                {row.owner}
                            </span>
                            <span className="font-mono text-slate-950 dark:text-white">
                                {row.pipeline_value.toLocaleString()}
                            </span>
                        </div>
                        <div className="h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                            <div
                                className="h-full rounded-full bg-indigo-500 transition-all duration-700 ease-out"
                                style={{ width: `${width}%` }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function SalesMiniMetric({
    label,
    value,
    tone,
}: {
    label: string;
    value: string | number;
    tone: 'emerald' | 'rose' | 'sky';
}) {
    const toneClass =
        tone === 'emerald'
            ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-200'
            : tone === 'rose'
              ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/40 dark:text-rose-200'
              : 'bg-sky-50 text-sky-700 dark:bg-sky-950/40 dark:text-sky-200';

    return (
        <div className={`rounded-md px-3 py-2 ${toneClass}`}>
            <div className="text-xs font-bold uppercase tracking-wide opacity-80">
                {label}
            </div>
            <div className="mt-1 text-lg font-bold">{value}</div>
        </div>
    );
}

function pipelineStageColor(stage: string) {
    if (stage === 'won') return 'bg-emerald-500';
    if (stage === 'lost') return 'bg-rose-500';
    if (stage === 'proposal') return 'bg-amber-500';
    if (stage === 'negotiation') return 'bg-violet-500';
    if (stage === 'qualified') return 'bg-sky-500';

    return 'bg-indigo-500';
}
