import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { money } from '@/Utils/format';

type Summary = Record<string, number>;

type SecurityAlerts = {
    inactive_users: number;
    pending_invites: number;
    expired_invites: number;
    sensitive_audit_events_24h: number;
    total: number;
};

type DashboardScope = 'admin' | 'executive' | 'finance' | 'delivery';

type DashboardFilters = {
    period: 'all_time' | 'month' | 'year' | 'custom';
    month: string;
    year: string;
    from: string;
    to: string;
    start_date: string | null;
    end_date: string | null;
};
type AuditItem = {
    action: string;
    entity_type: string;
    created_at: string;
};

type InvoiceStatusItem = {
    status: string;
    count: number;
    total: string | number | null;
};

type DeliverySummary = {
    active_projects: number;
    overdue_tasks: number;
    total_budget: number;
    actual_cost: number;
    project_profit: number;
    project_status: { status: string; count: number }[];
    task_load: {
        assignee_id: string | null;
        assignee_name: string | null;
        open_tasks: number;
    }[];
    delivery_risk: {
        count: number;
        over_budget: number;
        past_due_projects: number;
        urgent_or_high_open_tasks: number;
    };
};

type FinanceTrendMetrics = {
    invoiced_revenue: number;
    cash_in: number;
    outstanding_ar: number;
    overdue_ar: number;
    recognized_expense: number;
    cash_out: number;
    net_cash_flow: number;
    gross_profit: number;
};

type FinanceSummary = FinanceTrendMetrics & {
    cash_in_receipts: number;
    cash_in_reversals: number;
    invoice_status: InvoiceStatusItem[];
    payment_reversals: { count: number; amount: number };
    previous: FinanceTrendMetrics | null;
};

type ExecutiveSummary = {
    sales: {
        customers: number;
        open_deals: number;
        pipeline_value: number;
        won_deals: number;
        won_value: number;
    };
    finance: {
        invoiced_revenue: number;
        cash_in: number;
        outstanding_ar: number;
        overdue_ar: number;
        recognized_expense: number;
        gross_profit: number;
    };
    delivery: {
        active_projects: number;
        overdue_tasks: number;
        project_profit: number;
        delivery_risk_count: number;
    };
};

export default function Dashboard({
    summary,
    securityAlerts,
    recentAudits,
    executiveSummary,
    financeSummary,
    deliverySummary,
    dashboardFilters,
    dashboardScope,
}: {
    summary: Summary;
    securityAlerts: SecurityAlerts;
    recentAudits: AuditItem[];
    executiveSummary?: ExecutiveSummary | null;
    financeSummary?: FinanceSummary | null;
    deliverySummary?: DeliverySummary | null;
    dashboardFilters: DashboardFilters;
    dashboardScope: DashboardScope;
}) {
    const isAdminDashboard = dashboardScope === 'admin';
    const dashboardRouteName =
        dashboardScope === 'executive'
            ? 'executive.dashboard'
            : dashboardScope === 'finance'
              ? 'finance.dashboard'
              : dashboardScope === 'delivery'
                ? 'delivery.dashboard'
                : 'dashboard';
    const dashboardTitle =
        dashboardScope === 'executive'
            ? 'Executive Dashboard'
            : dashboardScope === 'finance'
              ? 'Finance Dashboard'
              : dashboardScope === 'delivery'
                ? 'Delivery Dashboard'
                : 'Admin Dashboard';

    const kpiCards = [
        {
            title: 'Branches',
            value: summary.branches,
            color: 'bg-indigo-50 text-indigo-600',
        },
        {
            title: 'Divisions',
            value: summary.divisions,
            color: 'bg-blue-50 text-blue-600',
        },
        {
            title: 'Departments',
            value: summary.departments,
            color: 'bg-sky-50 text-sky-600',
        },
        {
            title: 'Total Users',
            value: summary.users,
            color: 'bg-slate-100 text-slate-700',
        },
        {
            title: 'Active Users',
            value: summary.active_users,
            color: 'bg-emerald-50 text-emerald-600',
        },
        {
            title: 'Invited Users',
            value: summary.invited_users,
            color: 'bg-amber-50 text-amber-600',
        },
        {
            title: 'System Roles',
            value: summary.roles,
            color: 'bg-violet-50 text-violet-600',
        },
        {
            title: 'Total Audit Logs',
            value: summary.recent_audits,
            color: 'bg-purple-50 text-purple-600',
        },
    ];

    const alertCards = [
        {
            label: 'Inactive Users',
            value: securityAlerts.inactive_users,
            variant: 'danger' as const,
        },
        {
            label: 'Pending Invites',
            value: securityAlerts.pending_invites,
            variant: 'warning' as const,
        },
        {
            label: 'Expired Invites',
            value: securityAlerts.expired_invites,
            variant: 'danger' as const,
        },
        {
            label: 'Sensitive Events (24h)',
            value: securityAlerts.sensitive_audit_events_24h,
            variant: 'info' as const,
        },
    ];
    const [filters, setFilters] = useState({
        period: dashboardFilters.period,
        month: dashboardFilters.month,
        year: dashboardFilters.year,
        from: dashboardFilters.from,
        to: dashboardFilters.to,
    });

    const filterLabel = dashboardFilters.start_date
        ? `${dashboardFilters.start_date} - ${dashboardFilters.end_date ?? dashboardFilters.start_date}`
        : 'All time';

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.get(route(dashboardRouteName), filters, {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const resetFilters = () => {
        router.get(
            route(dashboardRouteName),
            { period: 'all_time' },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title={dashboardTitle} />

            <div className="space-y-6">
                {isAdminDashboard && (
                    <PageHeader
                        title="System Administration Overview"
                        description="Real-time metrics, system structure counts, and security monitor."
                    />
                )}

                {!isAdminDashboard && (
                    <Card>
                        <form
                            onSubmit={submitFilters}
                            className="grid gap-3 md:grid-cols-[minmax(160px,1fr)_minmax(160px,1fr)_minmax(120px,0.8fr)_minmax(140px,1fr)_minmax(140px,1fr)_auto_auto] md:items-end"
                        >
                            <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span>Period</span>
                                <select
                                    value={filters.period}
                                    onChange={(event) =>
                                        setFilters((current) => ({
                                            ...current,
                                            period: event.target
                                                .value as DashboardFilters['period'],
                                        }))
                                    }
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-900 dark:text-white"
                                >
                                    <option value="all_time">All time</option>
                                    <option value="month">Month</option>
                                    <option value="year">Year</option>
                                    <option value="custom">Custom</option>
                                </select>
                            </label>

                            <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span>Month</span>
                                <input
                                    type="month"
                                    value={filters.month}
                                    disabled={filters.period !== 'month'}
                                    onChange={(event) =>
                                        setFilters((current) => ({
                                            ...current,
                                            month: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800"
                                />
                            </label>

                            <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span>Year</span>
                                <input
                                    type="number"
                                    min="2000"
                                    max="2100"
                                    value={filters.year}
                                    disabled={filters.period !== 'year'}
                                    onChange={(event) =>
                                        setFilters((current) => ({
                                            ...current,
                                            year: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800"
                                />
                            </label>

                            <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span>From</span>
                                <input
                                    type="date"
                                    value={filters.from}
                                    disabled={filters.period !== 'custom'}
                                    onChange={(event) =>
                                        setFilters((current) => ({
                                            ...current,
                                            from: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800"
                                />
                            </label>

                            <label className="space-y-1 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <span>To</span>
                                <input
                                    type="date"
                                    value={filters.to}
                                    disabled={filters.period !== 'custom'}
                                    onChange={(event) =>
                                        setFilters((current) => ({
                                            ...current,
                                            to: event.target.value,
                                        }))
                                    }
                                    className="w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-slate-100 disabled:text-slate-400 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:disabled:bg-slate-800"
                                />
                            </label>

                            <button
                                type="submit"
                                className="rounded-md bg-slate-900 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-700 dark:bg-white dark:text-slate-950 dark:hover:bg-slate-200"
                            >
                                Apply
                            </button>
                            <button
                                type="button"
                                onClick={resetFilters}
                                className="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-900"
                            >
                                Reset
                            </button>
                        </form>
                        <div className="mt-3 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">
                            Dashboard range: {filterLabel}
                        </div>
                    </Card>
                )}
                {/* KPI Metrics Grid */}
                {isAdminDashboard && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {kpiCards.map((card, idx) => (
                            <StatCard
                                key={idx}
                                title={card.title}
                                value={card.value}
                                iconBgColor={card.color}
                            />
                        ))}
                    </div>
                )}

                {executiveSummary && (
                    <div className="space-y-4">
                        <PageHeader
                            title="Executive Dashboard"
                            description="Sales, finance, and delivery metrics for management view."
                        />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                title="Pipeline Value"
                                value={money(
                                    executiveSummary.sales.pipeline_value,
                                )}
                                subtitle={`${executiveSummary.sales.open_deals} open deals`}
                                iconBgColor="bg-indigo-50 text-indigo-600"
                            />
                            <StatCard
                                title="Won Value"
                                value={money(executiveSummary.sales.won_value)}
                                subtitle={`${executiveSummary.sales.won_deals} won deals`}
                                iconBgColor="bg-emerald-50 text-emerald-600"
                            />
                            <StatCard
                                title="Cash In"
                                value={money(executiveSummary.finance.cash_in)}
                                subtitle="Receipt minus reversal"
                                iconBgColor="bg-cyan-50 text-cyan-600"
                            />
                            <StatCard
                                title="Outstanding AR"
                                value={money(
                                    executiveSummary.finance.outstanding_ar,
                                )}
                                subtitle={`${money(executiveSummary.finance.overdue_ar)} overdue`}
                                iconBgColor="bg-amber-50 text-amber-600"
                            />
                            <StatCard
                                title="Gross Profit"
                                value={money(
                                    executiveSummary.finance.gross_profit,
                                )}
                                subtitle="Revenue - recognized expense"
                                iconBgColor="bg-violet-50 text-violet-600"
                            />
                            <StatCard
                                title="Active Projects"
                                value={
                                    executiveSummary.delivery.active_projects
                                }
                                subtitle="Planning, active, on hold"
                                iconBgColor="bg-teal-50 text-teal-600"
                            />
                            <StatCard
                                title="Delivery Risk"
                                value={
                                    executiveSummary.delivery
                                        .delivery_risk_count
                                }
                                subtitle={`${executiveSummary.delivery.overdue_tasks} overdue tasks`}
                                iconBgColor="bg-rose-50 text-rose-600"
                            />
                            <StatCard
                                title="Project Profit"
                                value={money(
                                    executiveSummary.delivery.project_profit,
                                )}
                                subtitle="Budget - actual cost"
                                iconBgColor="bg-slate-100 text-slate-700"
                            />
                        </div>
                        <ExecutiveVisualIndicators summary={executiveSummary} />
                    </div>
                )}

                {financeSummary && (
                    <div className="space-y-4">
                        <PageHeader
                            title="Finance Dashboard"
                            description="Revenue, cash movement, AR, expenses, and reversal health."
                        />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                title="Invoiced Revenue"
                                value={money(financeSummary.invoiced_revenue)}
                                subtitle="Non-void invoices"
                                iconBgColor="bg-emerald-50 text-emerald-600"
                            />
                            <StatCard
                                title="Cash In"
                                value={money(financeSummary.cash_in)}
                                subtitle={`Receipt ${money(financeSummary.cash_in_receipts)} / Reversal ${money(financeSummary.cash_in_reversals)}`}
                                iconBgColor="bg-cyan-50 text-cyan-600"
                            />
                            <StatCard
                                title="Outstanding AR"
                                value={money(financeSummary.outstanding_ar)}
                                subtitle="Sent, partial, overdue"
                                iconBgColor="bg-amber-50 text-amber-600"
                            />
                            <StatCard
                                title="Overdue AR"
                                value={money(financeSummary.overdue_ar)}
                                subtitle="Due date before today"
                                iconBgColor="bg-rose-50 text-rose-600"
                            />
                            <StatCard
                                title="Recognized Expense"
                                value={money(financeSummary.recognized_expense)}
                                subtitle="Approved and paid"
                                iconBgColor="bg-orange-50 text-orange-600"
                            />
                            <StatCard
                                title="Cash Out"
                                value={money(financeSummary.cash_out)}
                                subtitle="Paid expenses"
                                iconBgColor="bg-slate-100 text-slate-700"
                            />
                            <StatCard
                                title="Net Cash Flow"
                                value={money(financeSummary.net_cash_flow)}
                                subtitle="Cash In - Cash Out"
                                iconBgColor="bg-blue-50 text-blue-600"
                            />
                            <StatCard
                                title="Gross Profit"
                                value={money(financeSummary.gross_profit)}
                                subtitle="Revenue - recognized expense"
                                iconBgColor="bg-violet-50 text-violet-600"
                            />
                        </div>
                        {financeSummary.previous && (
                            <FinanceTrendPanel
                                financeSummary={financeSummary}
                            />
                        )}
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card
                                title="Invoice Status"
                                description="Count and value by status"
                            >
                                <InvoiceStatusDonut
                                    items={financeSummary.invoice_status}
                                />
                                <div className="mt-5">
                                    <DataTable
                                        data={financeSummary.invoice_status}
                                        keyExtractor={(row) => row.status}
                                        columns={[
                                            {
                                                header: 'Status',
                                                accessor: (row) => (
                                                    <Badge
                                                        variant={invoiceStatusVariant(
                                                            row.status,
                                                        )}
                                                        size="sm"
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                ),
                                            },
                                            {
                                                header: 'Count',
                                                accessor: (row) => row.count,
                                            },
                                            {
                                                header: 'Total',
                                                accessor: (row) =>
                                                    money(row.total ?? 0),
                                            },
                                        ]}
                                        emptyMessage="No invoices yet"
                                    />
                                </div>
                            </Card>
                            <Card
                                title="Payment Reversal"
                                description="Reversal impact on Cash In"
                            >
                                <PaymentReversalBar
                                    financeSummary={financeSummary}
                                />
                                <div className="mt-5 grid gap-4 sm:grid-cols-2">
                                    <div className="rounded-lg border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
                                        <div className="text-xs font-bold uppercase text-slate-700 dark:text-white">
                                            Reversal Count
                                        </div>
                                        <div className="mt-2 text-3xl font-bold text-slate-900 dark:text-white">
                                            {
                                                financeSummary.payment_reversals
                                                    .count
                                            }
                                        </div>
                                    </div>
                                    <div className="rounded-lg border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
                                        <div className="text-xs font-bold uppercase text-slate-700 dark:text-white">
                                            Reversal Amount
                                        </div>
                                        <div className="mt-2 text-3xl font-bold text-slate-900 dark:text-white">
                                            {money(
                                                financeSummary.payment_reversals
                                                    .amount,
                                            )}
                                        </div>
                                    </div>
                                </div>
                            </Card>
                        </div>
                    </div>
                )}

                {deliverySummary && (
                    <div className="space-y-4">
                        <PageHeader
                            title="Delivery Dashboard"
                            description="Project delivery, task load, budget usage, and risk signals."
                        />
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <StatCard
                                title="Active Projects"
                                value={deliverySummary.active_projects}
                                subtitle="Planning, active, on hold"
                                iconBgColor="bg-teal-50 text-teal-600"
                            />
                            <StatCard
                                title="Overdue Tasks"
                                value={deliverySummary.overdue_tasks}
                                subtitle="Todo/in progress past due"
                                iconBgColor="bg-rose-50 text-rose-600"
                            />
                            <StatCard
                                title="Budget vs Expense"
                                value={`${money(deliverySummary.actual_cost)} / ${money(deliverySummary.total_budget)}`}
                                subtitle="Approved and paid expense"
                                iconBgColor="bg-amber-50 text-amber-600"
                            />
                            <StatCard
                                title="Project Profit"
                                value={money(deliverySummary.project_profit)}
                                subtitle="Budget - actual cost"
                                iconBgColor="bg-emerald-50 text-emerald-600"
                            />
                        </div>
                        <div className="grid gap-4 lg:grid-cols-3">
                            <Card
                                title="Budget Control"
                                description="Budget utilization and visible project status"
                            >
                                <BudgetBurnBar
                                    totalBudget={deliverySummary.total_budget}
                                    actualCost={deliverySummary.actual_cost}
                                />
                                <div className="mt-5">
                                    <ProjectStatusDonut
                                        items={deliverySummary.project_status}
                                    />
                                </div>
                                <div className="mt-5">
                                    <DataTable
                                        data={deliverySummary.project_status}
                                        keyExtractor={(row) => row.status}
                                        columns={[
                                            {
                                                header: 'Status',
                                                accessor: (row) => (
                                                    <Badge
                                                        variant={projectStatusVariant(
                                                            row.status,
                                                        )}
                                                        size="sm"
                                                    >
                                                        {row.status}
                                                    </Badge>
                                                ),
                                            },
                                            {
                                                header: 'Count',
                                                accessor: (row) => row.count,
                                            },
                                        ]}
                                        emptyMessage="No visible projects"
                                    />
                                </div>
                            </Card>
                            <Card
                                title="Task Load"
                                description="Open tasks by assignee"
                            >
                                <TaskLoadBars
                                    rows={deliverySummary.task_load}
                                />
                                <div className="mt-5">
                                    <DataTable
                                        data={deliverySummary.task_load}
                                        keyExtractor={(row, index) =>
                                            row.assignee_id ??
                                            `unassigned-${index}`
                                        }
                                        columns={[
                                            {
                                                header: 'Assignee',
                                                accessor: (row) =>
                                                    row.assignee_name ??
                                                    'Unassigned',
                                            },
                                            {
                                                header: 'Open',
                                                accessor: (row) =>
                                                    row.open_tasks,
                                            },
                                        ]}
                                        emptyMessage="No open tasks"
                                    />
                                </div>
                            </Card>
                            <Card
                                title="Delivery Risk"
                                description="Projects or tasks needing attention"
                                action={
                                    <Badge
                                        variant={
                                            deliverySummary.delivery_risk
                                                .count > 0
                                                ? 'warning'
                                                : 'success'
                                        }
                                        dot
                                    >
                                        {deliverySummary.delivery_risk.count}{' '}
                                        Risk Projects
                                    </Badge>
                                }
                            >
                                <DeliveryRiskBadges
                                    risk={deliverySummary.delivery_risk}
                                />
                            </Card>
                        </div>
                    </div>
                )}
                {/* Security Alerts Hero Banner */}
                {isAdminDashboard && (
                    <Card
                        title="Security & System Alerts"
                        description="Active alerts requiring administrative review"
                        action={
                            <Badge
                                variant={
                                    securityAlerts.total > 0
                                        ? 'warning'
                                        : 'success'
                                }
                                dot
                            >
                                {securityAlerts.total} Open Alerts
                            </Badge>
                        }
                    >
                        <div className="grid gap-5 xl:grid-cols-[260px,1fr] xl:items-center">
                            <SecurityAlertDonut
                                alerts={securityAlerts}
                                alertCards={alertCards}
                            />
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-2">
                                {alertCards.map((alert, idx) => (
                                    <div
                                        key={idx}
                                        className="rounded-lg border border-slate-200/80 bg-slate-50/50 p-4 transition-all hover:bg-slate-50 dark:border-slate-800 dark:bg-slate-900/80 dark:hover:bg-slate-900"
                                    >
                                        <div className="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-white">
                                            {alert.label}
                                        </div>
                                        <div className="mt-2 flex items-baseline justify-between">
                                            <span className="font-sans text-2xl font-bold text-slate-900 dark:text-white">
                                                {alert.value}
                                            </span>
                                            <Badge
                                                variant={alert.variant}
                                                size="sm"
                                            >
                                                {alert.value > 0
                                                    ? 'Action Req'
                                                    : 'Normal'}
                                            </Badge>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </Card>
                )}

                {/* Recent Audit Activity Table */}
                {isAdminDashboard && (
                    <Card
                        title="Recent System Audits"
                        description="Latest 8 system events logged"
                    >
                        <DataTable
                            data={recentAudits}
                            keyExtractor={(_, index) => index}
                            columns={[
                                {
                                    header: 'Action',
                                    accessor: (row) => (
                                        <span className="font-mono text-xs font-bold text-slate-800 dark:text-white bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-200 dark:border-slate-700">
                                            {row.action}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'Entity Type',
                                    accessor: (row) => (
                                        <span className="capitalize text-slate-700 dark:text-white font-medium">
                                            {row.entity_type}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'Timestamp',
                                    accessor: (row) => (
                                        <span className="text-xs text-slate-600 dark:text-slate-200 font-mono font-medium">
                                            {row.created_at}
                                        </span>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function ExecutiveVisualIndicators({ summary }: { summary: ExecutiveSummary }) {
    const totalDeals = summary.sales.open_deals + summary.sales.won_deals;
    const winRate = totalDeals > 0 ? summary.sales.won_deals / totalDeals : 0;
    const receivableTotal =
        summary.finance.cash_in + summary.finance.outstanding_ar;
    const cashRatio =
        receivableTotal > 0 ? summary.finance.cash_in / receivableTotal : 0;
    const overdueRatio =
        summary.finance.outstanding_ar > 0
            ? summary.finance.overdue_ar / summary.finance.outstanding_ar
            : 0;
    const riskTotal =
        summary.delivery.active_projects + summary.delivery.delivery_risk_count;
    const riskRatio =
        riskTotal > 0 ? summary.delivery.delivery_risk_count / riskTotal : 0;

    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <Card
                title="Sales Conversion"
                description="Won deals compared with open pipeline"
                bodyClassName="p-5"
            >
                <CircleGauge
                    value={winRate}
                    label="Win rate"
                    center={summary.sales.won_deals}
                    suffix="won"
                    color="stroke-emerald-500"
                />
                <div className="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <MiniMetric
                        label="Pipeline"
                        value={money(summary.sales.pipeline_value)}
                    />
                    <MiniMetric
                        label="Won Value"
                        value={money(summary.sales.won_value)}
                    />
                </div>
            </Card>

            <Card
                title="Finance Mix"
                description="Cash collected vs receivables"
                bodyClassName="p-5"
            >
                <div className="space-y-4">
                    <StackedRatioBar
                        leftLabel="Cash In"
                        leftValue={summary.finance.cash_in}
                        rightLabel="Outstanding AR"
                        rightValue={summary.finance.outstanding_ar}
                        leftClass="bg-cyan-500"
                        rightClass="bg-amber-500"
                    />
                    <div className="grid grid-cols-2 gap-2 text-sm">
                        <MiniMetric
                            label="Cash Ratio"
                            value={`${(cashRatio * 100).toFixed(1)}%`}
                        />
                        <MiniMetric
                            label="Overdue AR"
                            value={`${(overdueRatio * 100).toFixed(1)}%`}
                        />
                    </div>
                </div>
            </Card>

            <Card
                title="Delivery Signal"
                description="Risk pressure across active work"
                bodyClassName="p-5"
            >
                <CircleGauge
                    value={riskRatio}
                    label="Risk ratio"
                    center={summary.delivery.delivery_risk_count}
                    suffix="risk"
                    color={
                        summary.delivery.delivery_risk_count > 0
                            ? 'stroke-amber-500'
                            : 'stroke-emerald-500'
                    }
                />
                <div className="mt-4 grid grid-cols-2 gap-2 text-sm">
                    <MiniMetric
                        label="Active"
                        value={summary.delivery.active_projects}
                    />
                    <MiniMetric
                        label="Profit"
                        value={money(summary.delivery.project_profit)}
                    />
                </div>
            </Card>
        </div>
    );
}

function CircleGauge({
    value,
    label,
    center,
    suffix,
    color,
}: {
    value: number;
    label: string;
    center: string | number;
    suffix: string;
    color: string;
}) {
    const clampedValue = Math.min(Math.max(value, 0), 1);
    const circumference = 100;
    const dash = clampedValue * circumference;

    return (
        <div className="flex items-center justify-center">
            <div className="relative h-36 w-36">
                <svg viewBox="0 0 42 42" className="h-36 w-36 -rotate-90">
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className="fill-none stroke-slate-100 dark:stroke-slate-800"
                        strokeWidth="5"
                    />
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className={`${color} fill-none transition-all duration-700 ease-out`}
                        strokeWidth="5"
                        strokeDasharray={`${dash} ${circumference - dash}`}
                        strokeLinecap="round"
                    />
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <div className="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">
                        {label}
                    </div>
                    <div className="text-2xl font-bold text-slate-950 dark:text-white">
                        {center}
                    </div>
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {suffix}
                    </div>
                </div>
            </div>
        </div>
    );
}

function StackedRatioBar({
    leftLabel,
    leftValue,
    rightLabel,
    rightValue,
    leftClass,
    rightClass,
}: {
    leftLabel: string;
    leftValue: number;
    rightLabel: string;
    rightValue: number;
    leftClass: string;
    rightClass: string;
}) {
    const total = Math.max(leftValue + rightValue, 0);
    const leftPercent = total > 0 ? (leftValue / total) * 100 : 0;
    const rightPercent = total > 0 ? (rightValue / total) * 100 : 0;

    return (
        <div>
            <div className="h-4 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div className="flex h-full w-full">
                    <div
                        className={`${leftClass} h-full transition-all duration-700 ease-out`}
                        style={{ width: `${leftPercent}%` }}
                    />
                    <div
                        className={`${rightClass} h-full transition-all duration-700 ease-out`}
                        style={{ width: `${rightPercent}%` }}
                    />
                </div>
            </div>
            <div className="mt-3 grid gap-2 text-sm">
                <MiniMetric label={leftLabel} value={money(leftValue)} />
                <MiniMetric label={rightLabel} value={money(rightValue)} />
            </div>
        </div>
    );
}

function MiniMetric({
    label,
    value,
}: {
    label: string;
    value: string | number;
}) {
    return (
        <div className="rounded-md bg-slate-50 px-3 py-2 dark:bg-slate-950/60">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                {label}
            </div>
            <div className="mt-1 font-bold text-slate-950 dark:text-white">
                {value}
            </div>
        </div>
    );
}
function BudgetBurnBar({
    totalBudget,
    actualCost,
}: {
    totalBudget: number;
    actualCost: number;
}) {
    const percent = totalBudget > 0 ? actualCost / totalBudget : 0;
    const clampedPercent = Math.min(percent, 1);
    const percentLabel = (percent * 100).toFixed(1);
    const color =
        percent > 1
            ? 'bg-rose-500'
            : percent >= 0.9
              ? 'bg-amber-500'
              : 'bg-emerald-500';

    return (
        <div className="rounded-md border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Budget Utilized
                    </div>
                    <div className="mt-1 text-2xl font-bold text-slate-950 dark:text-white">
                        {percentLabel}%
                    </div>
                </div>
                <Badge
                    variant={
                        percent > 1
                            ? 'danger'
                            : percent >= 0.9
                              ? 'warning'
                              : 'success'
                    }
                >
                    {money(actualCost)} / {money(totalBudget)}
                </Badge>
            </div>
            <div className="mt-4 h-4 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div
                    className={`h-full rounded-full ${color} transition-all duration-700 ease-out`}
                    style={{ width: `${clampedPercent * 100}%` }}
                />
            </div>
            <div className="mt-3 flex items-center justify-between text-xs font-medium text-slate-500 dark:text-slate-400">
                <span>Actual Cost</span>
                <span>Total Budget</span>
            </div>
        </div>
    );
}

function TaskLoadBars({ rows }: { rows: DeliverySummary['task_load'] }) {
    const maxOpenTasks = Math.max(...rows.map((row) => row.open_tasks), 0);

    if (rows.length === 0 || maxOpenTasks === 0) {
        return (
            <div className="flex min-h-40 items-center justify-center rounded-md border border-slate-200/80 bg-slate-50/70 text-sm font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                No open task load
            </div>
        );
    }

    return (
        <div className="grid gap-3 rounded-md border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
            {rows.map((row, index) => {
                const percent = Math.max(
                    (row.open_tasks / maxOpenTasks) * 100,
                    8,
                );

                return (
                    <div key={row.assignee_id ?? `task-load-${index}`}>
                        <div className="mb-1 flex items-center justify-between gap-3 text-xs font-semibold">
                            <span className="truncate text-slate-700 dark:text-slate-200">
                                {row.assignee_name ?? 'Unassigned'}
                            </span>
                            <span className="font-mono text-slate-950 dark:text-white">
                                {row.open_tasks}
                            </span>
                        </div>
                        <div className="h-3 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                            <div
                                className="h-full rounded-full bg-sky-500 transition-all duration-700 ease-out"
                                style={{ width: `${percent}%` }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function FinanceTrendPanel({
    financeSummary,
}: {
    financeSummary: FinanceSummary;
}) {
    if (!financeSummary.previous) return null;

    const trendRows: {
        label: string;
        current: number;
        previous: number;
        tone: 'good-up' | 'bad-up';
    }[] = [
        {
            label: 'Invoiced Revenue',
            current: financeSummary.invoiced_revenue,
            previous: financeSummary.previous.invoiced_revenue,
            tone: 'good-up',
        },
        {
            label: 'Cash In',
            current: financeSummary.cash_in,
            previous: financeSummary.previous.cash_in,
            tone: 'good-up',
        },
        {
            label: 'Outstanding AR',
            current: financeSummary.outstanding_ar,
            previous: financeSummary.previous.outstanding_ar,
            tone: 'bad-up',
        },
        {
            label: 'Gross Profit',
            current: financeSummary.gross_profit,
            previous: financeSummary.previous.gross_profit,
            tone: 'good-up',
        },
    ];

    return (
        <Card
            title="Finance Trend"
            description="Current selected range compared with the previous same-length period"
        >
            <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                {trendRows.map((row) => (
                    <FinanceTrendTile key={row.label} {...row} />
                ))}
            </div>
        </Card>
    );
}

function FinanceTrendTile({
    label,
    current,
    previous,
    tone,
}: {
    label: string;
    current: number;
    previous: number;
    tone: 'good-up' | 'bad-up';
}) {
    const delta = current - previous;
    const percent =
        previous !== 0 ? delta / Math.abs(previous) : current > 0 ? 1 : 0;
    const isUp = delta > 0;
    const isFlat = delta === 0;
    const isPositive = isFlat || (tone === 'good-up' ? isUp : !isUp);
    const colorClass = isPositive
        ? 'text-emerald-700 dark:text-emerald-200'
        : 'text-rose-700 dark:text-rose-200';
    const bgClass = isPositive
        ? 'border-emerald-200 bg-emerald-50/70 dark:border-emerald-900/70 dark:bg-emerald-950/40'
        : 'border-rose-200 bg-rose-50/70 dark:border-rose-900/70 dark:bg-rose-950/40';
    const sparkWidth = Math.min(Math.abs(percent) * 100, 100);
    const trendLabel = isFlat
        ? '0.0%'
        : (isUp ? '+' : '') + (percent * 100).toFixed(1) + '%';

    return (
        <div className={'rounded-md border p-4 ' + bgClass}>
            <div className="text-xs font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                {label}
            </div>
            <div className="mt-2 text-xl font-bold text-slate-950 dark:text-white">
                {money(current)}
            </div>
            <div className={'mt-1 text-sm font-semibold ' + colorClass}>
                {trendLabel}
            </div>
            <div className="mt-3 h-2 overflow-hidden rounded-full bg-white/80 dark:bg-slate-950/50">
                <div
                    className={
                        'h-full rounded-full transition-all duration-700 ease-out ' +
                        (isPositive ? 'bg-emerald-500' : 'bg-rose-500')
                    }
                    style={{
                        width:
                            String(Math.max(sparkWidth, isFlat ? 4 : 8)) + '%',
                    }}
                />
            </div>
            <div className="mt-2 text-xs font-medium text-slate-500 dark:text-slate-400">
                Previous {money(previous)}
            </div>
        </div>
    );
}
function InvoiceStatusDonut({ items }: { items: InvoiceStatusItem[] }) {
    const total = items.reduce((sum, item) => sum + Number(item.total ?? 0), 0);
    const count = items.reduce((sum, item) => sum + Number(item.count), 0);

    if (total <= 0) {
        return (
            <div className="flex min-h-56 items-center justify-center rounded-md border border-slate-200/80 bg-slate-50/70 text-sm font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                No invoice value yet
            </div>
        );
    }

    const segments = items.reduce<
        {
            item: InvoiceStatusItem;
            value: number;
            percent: number;
            offset: number;
            color: ReturnType<typeof invoiceStatusColor>;
        }[]
    >((carry, item) => {
        const value = Number(item.total ?? 0);
        const percent = value / total;
        const offset = carry.reduce((sum, segment) => sum + segment.percent, 0);

        return [
            ...carry,
            {
                item,
                value,
                percent,
                offset,
                color: invoiceStatusColor(item.status),
            },
        ];
    }, []);

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
                    {segments.map((segment) => (
                        <circle
                            key={segment.item.status}
                            cx="21"
                            cy="21"
                            r="15.915"
                            className={`${segment.color.stroke} fill-none transition-all duration-500 ease-out`}
                            strokeWidth="6"
                            strokeDasharray={`${segment.percent * 100} ${100 - segment.percent * 100}`}
                            strokeDashoffset={String(-segment.offset * 100)}
                        />
                    ))}
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <div className="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">
                        Total
                    </div>
                    <div className="text-xl font-bold text-slate-950 dark:text-white">
                        {money(total)}
                    </div>
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                        {count} invoices
                    </div>
                </div>
            </div>
            <div className="grid gap-2">
                {segments.map((segment) => (
                    <div
                        key={segment.item.status}
                        className="flex items-center justify-between rounded-md border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-900/80"
                    >
                        <div className="flex items-center gap-2">
                            <span
                                className={`h-2.5 w-2.5 rounded-full ${segment.color.bg}`}
                            />
                            <span className="font-semibold capitalize text-slate-700 dark:text-slate-200">
                                {segment.item.status.replace('_', ' ')}
                            </span>
                        </div>
                        <div className="text-right">
                            <div className="font-bold text-slate-950 dark:text-white">
                                {money(segment.value)}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {(segment.percent * 100).toFixed(1)}%
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function PaymentReversalBar({
    financeSummary,
}: {
    financeSummary: FinanceSummary;
}) {
    const receiptTotal = Math.max(financeSummary.cash_in_receipts, 0);
    const reversalTotal = Math.max(financeSummary.cash_in_reversals, 0);
    const grossCashMovement = receiptTotal + reversalTotal;
    const reversalPercent =
        grossCashMovement > 0 ? reversalTotal / grossCashMovement : 0;
    const receiptPercent =
        grossCashMovement > 0 ? receiptTotal / grossCashMovement : 0;

    return (
        <div className="rounded-md border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="text-xs font-bold uppercase tracking-wide text-slate-500 dark:text-slate-400">
                        Reversal Ratio
                    </div>
                    <div className="mt-1 text-2xl font-bold text-slate-950 dark:text-white">
                        {(reversalPercent * 100).toFixed(1)}%
                    </div>
                </div>
                <Badge variant={reversalTotal > 0 ? 'warning' : 'success'}>
                    Net {money(financeSummary.cash_in)}
                </Badge>
            </div>
            <div className="mt-4 h-4 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-800">
                <div className="flex h-full w-full">
                    <div
                        className="h-full bg-emerald-500 transition-all duration-500 ease-out"
                        style={{ width: `${receiptPercent * 100}%` }}
                    />
                    <div
                        className="h-full bg-amber-500 transition-all duration-500 ease-out"
                        style={{ width: `${reversalPercent * 100}%` }}
                    />
                </div>
            </div>
            <div className="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <div className="flex items-center justify-between rounded-md bg-white px-3 py-2 dark:bg-slate-950/60">
                    <span className="font-medium text-slate-600 dark:text-slate-300">
                        Receipts
                    </span>
                    <span className="font-bold text-slate-950 dark:text-white">
                        {money(receiptTotal)}
                    </span>
                </div>
                <div className="flex items-center justify-between rounded-md bg-white px-3 py-2 dark:bg-slate-950/60">
                    <span className="font-medium text-slate-600 dark:text-slate-300">
                        Reversals
                    </span>
                    <span className="font-bold text-slate-950 dark:text-white">
                        {money(reversalTotal)}
                    </span>
                </div>
            </div>
        </div>
    );
}

function SecurityAlertDonut({
    alerts,
    alertCards,
}: {
    alerts: SecurityAlerts;
    alertCards: {
        label: string;
        value: number;
        variant: 'danger' | 'warning' | 'info' | 'success' | 'neutral';
    }[];
}) {
    const visibleTotal = Math.max(
        alertCards.reduce((sum, alert) => sum + alert.value, 0),
        0,
    );

    if (visibleTotal === 0) {
        return (
            <div className="flex min-h-56 flex-col items-center justify-center rounded-md border border-emerald-200 bg-emerald-50/70 p-5 text-center dark:border-emerald-900/70 dark:bg-emerald-950/40">
                <div className="flex h-28 w-28 items-center justify-center rounded-full border-8 border-emerald-200 bg-white text-3xl font-bold text-emerald-700 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-100">
                    0
                </div>
                <div className="mt-4 text-sm font-bold uppercase text-emerald-700 dark:text-emerald-100">
                    System Normal
                </div>
                <div className="mt-1 text-xs font-medium text-emerald-700/80 dark:text-emerald-200/80">
                    No active security alerts
                </div>
            </div>
        );
    }

    const segments = alertCards.reduce<
        {
            label: string;
            value: number;
            percent: number;
            offset: number;
            color: string;
            dot: string;
        }[]
    >((carry, alert) => {
        if (alert.value <= 0) return carry;

        const percent = alert.value / visibleTotal;
        const offset = carry.reduce((sum, segment) => sum + segment.percent, 0);
        const color =
            alert.variant === 'danger'
                ? 'stroke-rose-500'
                : alert.variant === 'warning'
                  ? 'stroke-amber-500'
                  : alert.variant === 'info'
                    ? 'stroke-sky-500'
                    : 'stroke-emerald-500';
        const dot = color.replace('stroke-', 'bg-');

        return [
            ...carry,
            {
                label: alert.label,
                value: alert.value,
                percent,
                offset,
                color,
                dot,
            },
        ];
    }, []);

    return (
        <div className="rounded-md border border-slate-200/80 bg-slate-50/70 p-5 dark:border-slate-800 dark:bg-slate-900/80">
            <div className="relative mx-auto h-44 w-44">
                <svg viewBox="0 0 42 42" className="h-44 w-44 -rotate-90">
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className="fill-none stroke-slate-100 dark:stroke-slate-800"
                        strokeWidth="6"
                    />
                    {segments.map((segment) => (
                        <circle
                            key={segment.label}
                            cx="21"
                            cy="21"
                            r="15.915"
                            className={
                                segment.color +
                                ' fill-none transition-all duration-700 ease-out'
                            }
                            strokeWidth="6"
                            strokeDasharray={
                                String(segment.percent * 100) +
                                ' ' +
                                String(100 - segment.percent * 100)
                            }
                            strokeDashoffset={String(-segment.offset * 100)}
                        />
                    ))}
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <div className="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">
                        Alerts
                    </div>
                    <div className="text-3xl font-bold text-slate-950 dark:text-white">
                        {alerts.total}
                    </div>
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                        open
                    </div>
                </div>
            </div>
            <div className="mt-4 grid gap-2">
                {segments.map((segment) => (
                    <div
                        key={segment.label}
                        className="flex items-center justify-between rounded-md bg-white px-3 py-2 text-xs dark:bg-slate-950/60"
                    >
                        <span className="flex min-w-0 items-center gap-2 font-semibold text-slate-700 dark:text-slate-200">
                            <span
                                className={
                                    'h-2.5 w-2.5 rounded-full ' + segment.dot
                                }
                            />
                            <span className="truncate">{segment.label}</span>
                        </span>
                        <span className="font-mono font-bold text-slate-950 dark:text-white">
                            {segment.value}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
function ProjectStatusDonut({
    items,
}: {
    items: DeliverySummary['project_status'];
}) {
    const total = items.reduce((sum, item) => sum + Number(item.count), 0);

    if (total === 0) {
        return (
            <div className="flex min-h-48 items-center justify-center rounded-md border border-slate-200/80 bg-slate-50/70 text-sm font-medium text-slate-500 dark:border-slate-800 dark:bg-slate-900/80 dark:text-slate-300">
                No project status yet
            </div>
        );
    }

    const segments = items.reduce<
        {
            status: string;
            count: number;
            percent: number;
            offset: number;
            color: ReturnType<typeof projectStatusColor>;
        }[]
    >((carry, item) => {
        const count = Number(item.count);
        const percent = count / total;
        const offset = carry.reduce((sum, segment) => sum + segment.percent, 0);

        return [
            ...carry,
            {
                status: item.status,
                count,
                percent,
                offset,
                color: projectStatusColor(item.status),
            },
        ];
    }, []);

    return (
        <div className="grid gap-5 rounded-md border border-slate-200/80 bg-slate-50/70 p-4 dark:border-slate-800 dark:bg-slate-900/80 md:grid-cols-[160px,1fr] md:items-center">
            <div className="relative mx-auto h-40 w-40">
                <svg viewBox="0 0 42 42" className="h-40 w-40 -rotate-90">
                    <circle
                        cx="21"
                        cy="21"
                        r="15.915"
                        className="fill-none stroke-slate-100 dark:stroke-slate-800"
                        strokeWidth="6"
                    />
                    {segments.map((segment) => (
                        <circle
                            key={segment.status}
                            cx="21"
                            cy="21"
                            r="15.915"
                            className={
                                segment.color.stroke +
                                ' fill-none transition-all duration-700 ease-out'
                            }
                            strokeWidth="6"
                            strokeDasharray={
                                String(segment.percent * 100) +
                                ' ' +
                                String(100 - segment.percent * 100)
                            }
                            strokeDashoffset={String(-segment.offset * 100)}
                        />
                    ))}
                </svg>
                <div className="absolute inset-0 flex flex-col items-center justify-center text-center">
                    <div className="text-xs font-bold uppercase text-slate-500 dark:text-slate-400">
                        Projects
                    </div>
                    <div className="text-2xl font-bold text-slate-950 dark:text-white">
                        {total}
                    </div>
                    <div className="text-xs font-medium text-slate-500 dark:text-slate-400">
                        visible
                    </div>
                </div>
            </div>
            <div className="grid gap-2">
                {segments.map((segment) => (
                    <div
                        key={segment.status}
                        className="flex items-center justify-between rounded-md bg-white px-3 py-2 text-sm dark:bg-slate-950/60"
                    >
                        <div className="flex min-w-0 items-center gap-2">
                            <span
                                className={
                                    'h-2.5 w-2.5 rounded-full ' +
                                    segment.color.bg
                                }
                            />
                            <span className="truncate font-semibold capitalize text-slate-700 dark:text-slate-200">
                                {segment.status.replace('_', ' ')}
                            </span>
                        </div>
                        <div className="text-right">
                            <div className="font-bold text-slate-950 dark:text-white">
                                {segment.count}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {(segment.percent * 100).toFixed(1)}%
                            </div>
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

function DeliveryRiskBadges({
    risk,
}: {
    risk: DeliverySummary['delivery_risk'];
}) {
    return (
        <div className="grid gap-3">
            <RiskBadge label="Over Budget" value={risk.over_budget} />
            <RiskBadge
                label="Past Due Projects"
                value={risk.past_due_projects}
            />
            <RiskBadge
                label="High/Urgent Open Tasks"
                value={risk.urgent_or_high_open_tasks}
            />
        </div>
    );
}

function RiskBadge({ label, value }: { label: string; value: number }) {
    const hasRisk = value > 0;
    const toneClass = hasRisk
        ? 'border-amber-200 bg-amber-50 text-amber-800 shadow-sm dark:border-amber-900/70 dark:bg-amber-950/40 dark:text-amber-100'
        : 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900/70 dark:bg-emerald-950/40 dark:text-emerald-100';
    const dotClass = hasRisk ? 'bg-amber-500 animate-pulse' : 'bg-emerald-500';

    return (
        <div
            className={
                'rounded-md border px-4 py-3 transition-all duration-500 ease-out ' +
                toneClass
            }
        >
            <div className="flex items-center justify-between gap-3">
                <div className="flex min-w-0 items-center gap-2">
                    <span className={'h-2.5 w-2.5 rounded-full ' + dotClass} />
                    <span className="truncate text-sm font-semibold">
                        {label}
                    </span>
                </div>
                <span className="font-mono text-xl font-bold">{value}</span>
            </div>
        </div>
    );
}
function projectStatusColor(status: string) {
    if (status === 'completed') {
        return { stroke: 'stroke-emerald-500', bg: 'bg-emerald-500' };
    }
    if (status === 'active') {
        return { stroke: 'stroke-sky-500', bg: 'bg-sky-500' };
    }
    if (status === 'on_hold') {
        return { stroke: 'stroke-amber-500', bg: 'bg-amber-500' };
    }
    if (status === 'cancelled') {
        return { stroke: 'stroke-rose-500', bg: 'bg-rose-500' };
    }

    return { stroke: 'stroke-indigo-500', bg: 'bg-indigo-500' };
}

function projectStatusVariant(status: string) {
    if (status === 'completed') return 'success';
    if (status === 'active') return 'info';
    if (status === 'on_hold') return 'warning';
    if (status === 'cancelled') return 'danger';

    return 'neutral';
}

function invoiceStatusColor(status: string) {
    if (status === 'paid') {
        return { stroke: 'stroke-emerald-500', bg: 'bg-emerald-500' };
    }
    if (status === 'overdue') {
        return { stroke: 'stroke-rose-500', bg: 'bg-rose-500' };
    }
    if (status === 'void') {
        return { stroke: 'stroke-slate-400', bg: 'bg-slate-400' };
    }
    if (status === 'partially_paid') {
        return { stroke: 'stroke-sky-500', bg: 'bg-sky-500' };
    }
    if (status === 'sent') {
        return { stroke: 'stroke-amber-500', bg: 'bg-amber-500' };
    }

    return { stroke: 'stroke-indigo-500', bg: 'bg-indigo-500' };
}
function invoiceStatusVariant(status: string) {
    if (status === 'paid') {
        return 'success';
    }
    if (status === 'overdue' || status === 'void') {
        return 'danger';
    }
    if (status === 'partially_paid') {
        return 'warning';
    }
    return 'info';
}
