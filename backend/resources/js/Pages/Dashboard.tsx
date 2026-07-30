import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { money } from '@/Utils/format';

type Summary = Record<string, number>;

type SecurityAlerts = {
    inactive_users: number;
    pending_invites: number;
    expired_invites: number;
    sensitive_audit_events_24h: number;
    total: number;
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

type FinanceSummary = {
    invoiced_revenue: number;
    cash_in: number;
    cash_in_receipts: number;
    cash_in_reversals: number;
    outstanding_ar: number;
    overdue_ar: number;
    recognized_expense: number;
    cash_out: number;
    net_cash_flow: number;
    gross_profit: number;
    invoice_status: InvoiceStatusItem[];
    payment_reversals: { count: number; amount: number };
};

export default function Dashboard({
    summary,
    securityAlerts,
    recentAudits,
    financeSummary,
    deliverySummary,
}: {
    summary: Summary;
    securityAlerts: SecurityAlerts;
    recentAudits: AuditItem[];
    financeSummary?: FinanceSummary | null;
    deliverySummary?: DeliverySummary | null;
}) {
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

    return (
        <AuthenticatedLayout>
            <Head title="Admin Dashboard" />

            <div className="space-y-6">
                <PageHeader
                    title="System Administration Overview"
                    description="Real-time metrics, system structure counts, and security monitor."
                />

                {/* KPI Metrics Grid */}
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
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Card
                                title="Invoice Status"
                                description="Count and value by status"
                            >
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
                            </Card>
                            <Card
                                title="Payment Reversal"
                                description="Reversal impact on Cash In"
                            >
                                <div className="grid gap-4 sm:grid-cols-2">
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
                                title="Project Status"
                                description="Visible projects by delivery status"
                            >
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
                            </Card>
                            <Card
                                title="Task Load"
                                description="Open tasks by assignee"
                            >
                                <DataTable
                                    data={deliverySummary.task_load}
                                    keyExtractor={(row, index) =>
                                        row.assignee_id ?? `unassigned-${index}`
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
                                            accessor: (row) => row.open_tasks,
                                        },
                                    ]}
                                    emptyMessage="No open tasks"
                                />
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
                                <div className="grid gap-3">
                                    <RiskRow
                                        label="Over Budget"
                                        value={
                                            deliverySummary.delivery_risk
                                                .over_budget
                                        }
                                    />
                                    <RiskRow
                                        label="Past Due Projects"
                                        value={
                                            deliverySummary.delivery_risk
                                                .past_due_projects
                                        }
                                    />
                                    <RiskRow
                                        label="High/Urgent Open Tasks"
                                        value={
                                            deliverySummary.delivery_risk
                                                .urgent_or_high_open_tasks
                                        }
                                    />
                                </div>
                            </Card>
                        </div>
                    </div>
                )}
                {/* Security Alerts Hero Banner */}
                <Card
                    title="Security & System Alerts"
                    description="Active alerts requiring administrative review"
                    action={
                        <Badge
                            variant={
                                securityAlerts.total > 0 ? 'warning' : 'success'
                            }
                            dot
                        >
                            {securityAlerts.total} Open Alerts
                        </Badge>
                    }
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        {alertCards.map((alert, idx) => (
                            <div
                                key={idx}
                                className="rounded-lg border border-slate-200/80 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/80 p-4 transition-all hover:bg-slate-50 dark:hover:bg-slate-900"
                            >
                                <div className="text-xs font-bold text-slate-700 dark:text-white uppercase tracking-wider">
                                    {alert.label}
                                </div>
                                <div className="mt-2 flex items-baseline justify-between">
                                    <span className="text-2xl font-bold text-slate-900 dark:text-white font-sans">
                                        {alert.value}
                                    </span>
                                    <Badge variant={alert.variant} size="sm">
                                        {alert.value > 0
                                            ? 'Action Req'
                                            : 'Normal'}
                                    </Badge>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>

                {/* Recent Audit Activity Table */}
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
            </div>
        </AuthenticatedLayout>
    );
}

function RiskRow({ label, value }: { label: string; value: number }) {
    return (
        <div className="flex items-center justify-between rounded-md border border-slate-200/80 bg-slate-50/70 px-3 py-2 text-sm dark:border-slate-800 dark:bg-slate-900/80">
            <span className="font-medium text-slate-700 dark:text-slate-200">
                {label}
            </span>
            <span className="font-bold text-slate-950 dark:text-white">
                {value}
            </span>
        </div>
    );
}

function projectStatusVariant(status: string) {
    if (status === 'completed') return 'success';
    if (status === 'active') return 'info';
    if (status === 'on_hold') return 'warning';
    if (status === 'cancelled') return 'danger';

    return 'neutral';
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
