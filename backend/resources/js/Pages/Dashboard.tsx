import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

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

export default function Dashboard({
    summary,
    securityAlerts,
    recentAudits,
}: {
    summary: Summary;
    securityAlerts: SecurityAlerts;
    recentAudits: AuditItem[];
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
