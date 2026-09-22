import Badge from '@/Components/UI/Badge';
import {
    DashboardBreakdown,
    DashboardChart,
    DashboardKpiCard,
    DashboardLayout,
    DashboardList,
} from '@/Components/Dashboard';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import StatCard from '@/Components/UI/StatCard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';
import { money } from '@/Utils/format';

function cashMonthHref(month: string, source: 'ar' | 'ap'): string {
    const [year, monthNumber] = month.split('-').map(Number);
    const lastDay = new Date(Date.UTC(year, monthNumber, 0)).getUTCDate();
    return route(source === 'ar' ? 'payments.index' : 'vendor-payments.index', {
        from: `${month}-01`,
        to: `${month}-${String(lastDay).padStart(2, '0')}`,
    });
}

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
    budget_remaining: number;
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
    billed_minus_expense_estimate: number;
};

type FinanceSummary = FinanceTrendMetrics & {
    cash_in_receipts: number;
    cash_in_reversals: number;
    invoice_status: InvoiceStatusItem[];
    payment_reversals: { count: number; amount: number };
    previous: FinanceTrendMetrics | null;
};

export default function Dashboard({
    summary,
    securityAlerts,
    recentAudits,

    financeSummary,
    deliverySummary,
    dashboardFilters,
    dashboardScope,
    dashboardWidgets,
}: {
    summary?: Summary | null;
    securityAlerts?: SecurityAlerts | null;
    recentAudits: AuditItem[];

    financeSummary?: FinanceSummary | null;
    deliverySummary?: DeliverySummary | null;
    dashboardFilters: DashboardFilters;
    dashboardScope: DashboardScope;
    dashboardWidgets?: {
        executive?: {
            sales?: {
                open_deals: number;
                won_deals: number;
                pipeline_by_currency: { currency: string; amount: number }[];
                won_by_currency: { currency: string; amount: number }[];
            };
            revenue_expense_trend?: {
                label: string;
                revenue: number | null;
                expense: number | null;
            }[];
            top_customers?: {
                customer_id: string;
                name: string;
                amount: number;
            }[];
            project_risks?: {
                count: number;
                items: {
                    id: string;
                    project_code: string;
                    name: string;
                    reasons: string[];
                }[];
            };
        };
        admin?: {
            users?: {
                total: number;
                active: number;
                invited: number;
                expired_invites: number;
                inactive: number;
            };
            recent_invites?: {
                id: string;
                name: string;
                email: string;
                invite_expires_at: string | null;
            }[];
            roles?: {
                total: number;
                distribution: {
                    id: string;
                    code: string;
                    name: string;
                    users_count: number;
                }[];
            };
            audit?: {
                today: number;
                today_date: string;
                sensitive_24h: number;
            };
            recent_audits?: {
                id: string;
                action: string;
                entity_type: string;
                created_at: string;
            }[];
        };
        finance?: {
            revenue?: { invoiced_revenue: number };
            ar?: {
                outstanding_ar: number;
                overdue_ar: number;
                aging: Record<string, number>;
            };
            invoice_status?: { status: string; count: number }[];
            overdue_invoices?: {
                id: string;
                invoice_no: string;
                due_date: string;
                base_balance_due: number;
            }[];
            cash_in?: { receipts: number; reversals: number };
            recent_payments?: {
                id: string;
                invoice_id: string;
                entry_type: string;
                base_amount: number;
                payment_date: string;
            }[];
            expense?: { recognized_expense: number; ap_cash_out: number };
            expenses_awaiting_approval?: {
                id: string;
                expense_no: string;
                title: string;
                expense_date: string;
            }[];
            cash_flow_trend?: {
                label: string;
                cash_in: number | null;
                ap_cash_out: number | null;
            }[];
        };
        delivery?: {
            projects?: {
                active_projects: number;
                average_progress: number | null;
                status: { status: string; count: number }[];
            };
            past_due_projects?: {
                id: string;
                project_code: string;
                name: string;
                due_date: string;
            }[];
            tasks?: { overdue_tasks: number; due_this_week: number };
            overdue_tasks?: {
                id: string;
                title: string;
                due_date: string;
                status: string;
            }[];
            due_this_week?: {
                id: string;
                title: string;
                due_date: string;
                status: string;
            }[];
            assignee_load?: {
                assignee_id: string | null;
                name: string;
                open_tasks: number;
            }[];
        };
    };
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

    const kpiCards = summary
        ? [
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
          ]
        : [];

    const alertCards = securityAlerts
        ? [
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
          ]
        : [];
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
    const [filterError, setFilterError] = useState<string | null>(null);

    const submitFilters = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        if (filters.period === 'custom') {
            if (!filters.from || !filters.to) {
                setFilterError('Choose both dates for a custom range.');
                return;
            }
            if (filters.from > filters.to) {
                setFilterError(
                    'The start date must be on or before the end date.',
                );
                return;
            }
        }
        setFilterError(null);
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

            <DashboardLayout
                title={dashboardTitle}
                description="Permission-scoped operational metrics and follow-up signals."
            >
                {isAdminDashboard && summary && !dashboardWidgets?.admin && (
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
                                    <option value="today">Today</option>
                                    <option value="this_week">This week</option>
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
                                    aria-invalid={Boolean(filterError)}
                                    aria-describedby={
                                        filterError
                                            ? 'dashboard-filter-error'
                                            : undefined
                                    }
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
                                    aria-invalid={Boolean(filterError)}
                                    aria-describedby={
                                        filterError
                                            ? 'dashboard-filter-error'
                                            : undefined
                                    }
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
                        {filterError && (
                            <p
                                id="dashboard-filter-error"
                                role="alert"
                                className="mt-2 text-sm text-rose-700 dark:text-rose-300"
                            >
                                {filterError}
                            </p>
                        )}
                    </Card>
                )}
                {/* KPI Metrics Grid */}
                {isAdminDashboard &&
                    securityAlerts &&
                    !dashboardWidgets?.admin && (
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

                {isAdminDashboard && dashboardWidgets?.admin && (
                    <section
                        className="space-y-4"
                        aria-label="Permission-scoped administration widgets"
                    >
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {dashboardWidgets.admin.users && (
                                <>
                                    <DashboardKpiCard
                                        question="How many users are active?"
                                        value={
                                            dashboardWidgets.admin.users.active
                                        }
                                        context={`${dashboardWidgets.admin.users.total} total users`}
                                        tone="positive"
                                    />
                                    <DashboardKpiCard
                                        question="How many invites are pending?"
                                        value={
                                            dashboardWidgets.admin.users.invited
                                        }
                                        context={`${dashboardWidgets.admin.users.expired_invites} expired`}
                                        tone="pending"
                                    />
                                </>
                            )}
                            {dashboardWidgets.admin.audit && (
                                <>
                                    <DashboardKpiCard
                                        question="How many audit events happened today?"
                                        value={
                                            dashboardWidgets.admin.audit.today
                                        }
                                        context="Current calendar day"
                                        tone="neutral"
                                    />
                                    <DashboardKpiCard
                                        question="How many sensitive events happened in 24 hours?"
                                        value={
                                            dashboardWidgets.admin.audit
                                                .sensitive_24h
                                        }
                                        context="Role, disable and hierarchy changes"
                                        tone="risk"
                                    />
                                </>
                            )}
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {dashboardWidgets.admin.roles && (
                                <DashboardList
                                    title="Role distribution"
                                    description={`Top 12 roles by assigned users · ${dashboardWidgets.admin.roles.total} roles total`}
                                    emptyMessage="No roles in this organization."
                                    items={dashboardWidgets.admin.roles.distribution.map(
                                        (role) => ({
                                            id: role.id,
                                            title: role.name,
                                            detail: `${role.users_count} users`,
                                            routeName: dashboardWidgets.admin
                                                ?.users
                                                ? 'users.index'
                                                : 'roles.index',
                                            routeParameters: {
                                                role_id: role.id,
                                            },
                                        }),
                                    )}
                                />
                            )}
                            {(dashboardWidgets.admin.users ||
                                dashboardWidgets.admin.audit) && (
                                <DashboardList
                                    title="Access follow-ups"
                                    description="Expired invitations, inactive accounts and audit activity"
                                    emptyMessage="No access follow-ups."
                                    items={[
                                        ...(dashboardWidgets.admin.users
                                            ? [
                                                  {
                                                      id: 'expired',
                                                      title: 'Expired invites',
                                                      detail: `${dashboardWidgets.admin.users.expired_invites} users`,
                                                      routeName: 'users.index',
                                                      routeParameters: {
                                                          status: 'invited',
                                                          expired: '1',
                                                      },
                                                  },
                                                  {
                                                      id: 'inactive',
                                                      title: 'Inactive users',
                                                      detail: `${dashboardWidgets.admin.users.inactive} users`,
                                                      routeName: 'users.index',
                                                      routeParameters: {
                                                          status: 'inactive',
                                                      },
                                                  },
                                              ]
                                            : []),
                                        ...(dashboardWidgets.admin.audit
                                            ? [
                                                  {
                                                      id: 'sensitive',
                                                      title: 'Sensitive audit events (24h)',
                                                      detail: `${dashboardWidgets.admin.audit.sensitive_24h} events`,
                                                      routeName:
                                                          'audit-logs.index',
                                                      routeParameters: {
                                                          window: '24h',
                                                          sensitive: '1',
                                                      },
                                                  },
                                                  {
                                                      id: 'today',
                                                      title: 'Audit events today',
                                                      detail: `${dashboardWidgets.admin.audit.today} events`,
                                                      routeName:
                                                          'audit-logs.index',
                                                      routeParameters: {
                                                          from: dashboardWidgets
                                                              .admin.audit
                                                              .today_date,
                                                          to: dashboardWidgets
                                                              .admin.audit
                                                              .today_date,
                                                      },
                                                  },
                                              ]
                                            : []),
                                    ]}
                                />
                            )}
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {dashboardWidgets.admin.recent_invites && (
                                <DashboardList
                                    title="Pending invites"
                                    description="Latest eight pending invitations"
                                    emptyMessage="No pending invitations."
                                    items={dashboardWidgets.admin.recent_invites.map(
                                        (invite) => ({
                                            id: invite.id,
                                            title: invite.name,
                                            detail: `${invite.email} · expires ${invite.invite_expires_at ?? 'unknown'}`,
                                            routeName: 'users.index',
                                            routeParameters: {
                                                user_id: invite.id,
                                            },
                                        }),
                                    )}
                                />
                            )}
                            {dashboardWidgets.admin.recent_audits && (
                                <DashboardList
                                    title="Recent audits"
                                    description="Latest eight organization audit events"
                                    emptyMessage="No audit events yet."
                                    items={dashboardWidgets.admin.recent_audits.map(
                                        (audit) => ({
                                            id: audit.id,
                                            title: audit.action,
                                            detail: `${audit.entity_type} · ${audit.created_at}`,
                                            routeName: 'audit-logs.index',
                                            routeParameters: {
                                                audit_id: audit.id,
                                            },
                                        }),
                                    )}
                                />
                            )}
                        </div>
                    </section>
                )}

                {(dashboardScope === 'executive' || isAdminDashboard) &&
                    dashboardWidgets?.executive && (
                        <section
                            className="space-y-4"
                            aria-label="Permission-scoped executive dashboard"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {dashboardWidgets.finance?.revenue && (
                                    <DashboardKpiCard
                                        question="Invoiced Revenue"
                                        value={money(
                                            dashboardWidgets.finance.revenue
                                                .invoiced_revenue,
                                        )}
                                        context="Document total in base currency · selected period"
                                    />
                                )}
                                {dashboardWidgets.finance?.cash_in && (
                                    <DashboardKpiCard
                                        question="AR Cash In"
                                        value={money(
                                            dashboardWidgets.finance?.cash_in
                                                .receipts -
                                                dashboardWidgets.finance.cash_in
                                                    .reversals,
                                        )}
                                        context="Receipts minus reversals · selected period"
                                    />
                                )}
                                {dashboardWidgets.finance?.ar && (
                                    <DashboardKpiCard
                                        question="Outstanding AR"
                                        value={money(
                                            dashboardWidgets.finance.ar
                                                .outstanding_ar,
                                        )}
                                        context="Current balance in base currency"
                                        tone="pending"
                                    />
                                )}
                                {dashboardWidgets.finance?.expense && (
                                    <DashboardKpiCard
                                        question="Recognized Expense"
                                        value={money(
                                            dashboardWidgets.finance.expense
                                                .recognized_expense,
                                        )}
                                        context="Approved and paid · selected period"
                                    />
                                )}
                                {dashboardWidgets.delivery?.projects && (
                                    <DashboardKpiCard
                                        question="Active Projects"
                                        value={
                                            dashboardWidgets.delivery.projects
                                                .active_projects
                                        }
                                        context="Current planning, active and on-hold projects"
                                    />
                                )}
                                {dashboardWidgets.delivery?.tasks && (
                                    <DashboardKpiCard
                                        question="Overdue Tasks"
                                        value={
                                            dashboardWidgets.delivery.tasks
                                                .overdue_tasks
                                        }
                                        context="Current open tasks past due"
                                        tone="risk"
                                    />
                                )}
                            </div>
                            <div className="grid gap-4 lg:grid-cols-2">
                                {dashboardWidgets.executive
                                    .revenue_expense_trend && (
                                    <DashboardChart
                                        title="Invoiced Revenue / Recognized Expense"
                                        description="Monthly base-currency document flows"
                                        kind="bar"
                                        primaryLabel={
                                            dashboardWidgets.finance?.revenue
                                                ? 'Invoiced Revenue'
                                                : 'Recognized Expense'
                                        }
                                        secondaryLabel={
                                            dashboardWidgets.finance?.revenue &&
                                            dashboardWidgets.finance?.expense
                                                ? 'Recognized Expense'
                                                : undefined
                                        }
                                        data={dashboardWidgets.executive.revenue_expense_trend.map(
                                            (point) => ({
                                                label: point.label,
                                                value: dashboardWidgets.finance
                                                    ?.revenue
                                                    ? (point.revenue ?? 0)
                                                    : (point.expense ?? 0),
                                                secondaryValue:
                                                    dashboardWidgets.finance
                                                        ?.revenue &&
                                                    dashboardWidgets.finance
                                                        ?.expense
                                                        ? (point.expense ?? 0)
                                                        : undefined,
                                            }),
                                        )}
                                    />
                                )}
                                {dashboardWidgets.finance?.cash_flow_trend && (
                                    <DashboardChart
                                        title="AR Cash In / AP Cash Out"
                                        description="Monthly net receipts and vendor payments in base currency"
                                        kind="bar"
                                        primaryLabel={
                                            dashboardWidgets.finance?.cash_in
                                                ? 'AR Cash In'
                                                : 'AP Cash Out'
                                        }
                                        secondaryLabel={
                                            dashboardWidgets.finance?.cash_in &&
                                            dashboardWidgets.finance?.expense
                                                ? 'AP Cash Out'
                                                : undefined
                                        }
                                        data={dashboardWidgets.finance.cash_flow_trend.map(
                                            (point) => ({
                                                label: point.label,
                                                value: dashboardWidgets.finance
                                                    ?.cash_in
                                                    ? (point.cash_in ?? 0)
                                                    : (point.ap_cash_out ?? 0),
                                                secondaryValue:
                                                    dashboardWidgets.finance
                                                        ?.cash_in &&
                                                    dashboardWidgets.finance
                                                        ?.expense
                                                        ? (point.ap_cash_out ??
                                                          0)
                                                        : undefined,
                                                primaryHref: cashMonthHref(
                                                    point.label,
                                                    dashboardWidgets.finance
                                                        ?.cash_in
                                                        ? 'ar'
                                                        : 'ap',
                                                ),
                                                secondaryHref:
                                                    dashboardWidgets.finance
                                                        ?.cash_in &&
                                                    dashboardWidgets.finance
                                                        ?.expense
                                                        ? cashMonthHref(
                                                              point.label,
                                                              'ap',
                                                          )
                                                        : undefined,
                                            }),
                                        )}
                                    />
                                )}
                            </div>
                            <div className="grid gap-4 lg:grid-cols-2">
                                {dashboardWidgets.executive.sales && (
                                    <DashboardList
                                        title="Open Pipeline"
                                        description={`${dashboardWidgets.executive.sales.open_deals} current open deals; values separated by currency`}
                                        items={dashboardWidgets.executive.sales.pipeline_by_currency.map(
                                            (row) => ({
                                                id: row.currency,
                                                title: row.currency,
                                                detail: row.amount.toLocaleString(),
                                                routeName: 'deals.index',
                                                routeParameters: {
                                                    currency:
                                                        row.currency ===
                                                        'Unspecified'
                                                            ? '__unspecified'
                                                            : row.currency,
                                                    open_only: 1,
                                                },
                                            }),
                                        )}
                                        emptyMessage="No open deals."
                                    />
                                )}
                                {dashboardWidgets.executive.sales && (
                                    <DashboardList
                                        title="Won Deal Value"
                                        description={`${dashboardWidgets.executive.sales.won_deals} won deals in selected period; values separated by currency`}
                                        items={dashboardWidgets.executive.sales.won_by_currency.map(
                                            (row) => ({
                                                id: row.currency,
                                                title: row.currency,
                                                detail: row.amount.toLocaleString(),
                                                routeName: 'deals.index',
                                                routeParameters: {
                                                    stage: 'won',
                                                    currency:
                                                        row.currency ===
                                                        'Unspecified'
                                                            ? '__unspecified'
                                                            : row.currency,
                                                    won_from:
                                                        dashboardFilters.start_date,
                                                    won_to: dashboardFilters.end_date,
                                                },
                                            }),
                                        )}
                                        emptyMessage="No won deals in this period."
                                    />
                                )}
                                {dashboardWidgets.delivery?.projects && (
                                    <DashboardList
                                        title="Project Status"
                                        description="Current accessible projects by status"
                                        items={dashboardWidgets.delivery.projects.status.map(
                                            (row) => ({
                                                id: row.status,
                                                title: row.status,
                                                detail: `${row.count} projects`,
                                                routeName: 'projects.index',
                                                routeParameters: {
                                                    status: row.status,
                                                },
                                            }),
                                        )}
                                        emptyMessage="No projects."
                                    />
                                )}
                                {dashboardWidgets.executive.top_customers && (
                                    <DashboardList
                                        title="Top Customers"
                                        description="Invoiced document value in base currency · selected period"
                                        items={dashboardWidgets.executive.top_customers.map(
                                            (row) => ({
                                                id: row.customer_id,
                                                title: row.name,
                                                detail: money(row.amount),
                                                routeName: 'customers.index',
                                                routeParameters: {
                                                    customer_id:
                                                        row.customer_id,
                                                },
                                            }),
                                        )}
                                        emptyMessage="No invoiced customers in this period."
                                    />
                                )}
                                {dashboardWidgets.finance?.overdue_invoices && (
                                    <DashboardList
                                        title="Overdue Invoices"
                                        description="Current overdue receivables"
                                        items={dashboardWidgets.finance.overdue_invoices.map(
                                            (row) => ({
                                                id: row.id,
                                                title: row.invoice_no,
                                                detail: `${row.due_date} · ${money(row.base_balance_due)}`,
                                                routeName: 'invoices.index',
                                                routeParameters: {
                                                    invoice_id: row.id,
                                                },
                                            }),
                                        )}
                                        emptyMessage="No overdue invoices."
                                    />
                                )}
                                {dashboardWidgets.executive.project_risks && (
                                    <DashboardList
                                        title="Projects at Risk"
                                        description={`${dashboardWidgets.executive.project_risks.count} projects with visible past-due, high-priority task or same-currency over-budget signals`}
                                        items={dashboardWidgets.executive.project_risks.items.map(
                                            (row) => ({
                                                id: row.id,
                                                title: row.name,
                                                detail: `${row.project_code} · ${row.reasons.join(', ')}`,
                                                routeName: 'projects.index',
                                                routeParameters: {
                                                    project_id: row.id,
                                                },
                                            }),
                                        )}
                                        emptyMessage="No projects with visible risk signals."
                                    />
                                )}
                            </div>
                        </section>
                    )}
                {financeSummary && dashboardScope !== 'finance' && (
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
                                title="Net AR/AP cash movement"
                                value={money(financeSummary.net_cash_flow)}
                                subtitle="Cash In - Cash Out"
                                iconBgColor="bg-blue-50 text-blue-600"
                            />
                            <StatCard
                                title="Billed-minus-expense estimate"
                                value={money(
                                    financeSummary.billed_minus_expense_estimate,
                                )}
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

                {dashboardScope === 'finance' && dashboardWidgets?.finance && (
                    <section
                        className="space-y-4"
                        aria-label="Permission-scoped finance widgets"
                    >
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white">
                            Available finance metrics
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            {dashboardWidgets.finance.revenue && (
                                <DashboardKpiCard
                                    question="How much was invoiced in this period?"
                                    value={money(
                                        dashboardWidgets.finance.revenue
                                            .invoiced_revenue,
                                    )}
                                    context="Non-void invoices in the selected period"
                                    tone="positive"
                                />
                            )}
                            {dashboardWidgets.finance.ar && (
                                <DashboardKpiCard
                                    question="How much AR remains outstanding?"
                                    value={money(
                                        dashboardWidgets.finance.ar
                                            .outstanding_ar,
                                    )}
                                    context={`${money(dashboardWidgets.finance.ar.overdue_ar)} overdue`}
                                    tone="pending"
                                />
                            )}
                            {dashboardWidgets.finance.ar && (
                                <DashboardKpiCard
                                    question="How much AR is overdue now?"
                                    value={money(
                                        dashboardWidgets.finance.ar.overdue_ar,
                                    )}
                                    context="Current open invoice snapshot"
                                    tone="risk"
                                />
                            )}
                            {dashboardWidgets.finance.cash_in && (
                                <DashboardKpiCard
                                    question="What AR cash was received after reversals?"
                                    value={money(
                                        dashboardWidgets.finance.cash_in
                                            .receipts -
                                            dashboardWidgets.finance.cash_in
                                                .reversals,
                                    )}
                                    context={`Receipts ${money(dashboardWidgets.finance.cash_in.receipts)}; reversals ${money(dashboardWidgets.finance.cash_in.reversals)}`}
                                    tone="neutral"
                                />
                            )}
                            {dashboardWidgets.finance.expense && (
                                <DashboardKpiCard
                                    question="What approved or paid expense was recognized?"
                                    value={money(
                                        dashboardWidgets.finance.expense
                                            .recognized_expense,
                                    )}
                                    context="Approved and paid expenses in the selected period"
                                    tone="pending"
                                />
                            )}
                            {dashboardWidgets.finance.expense && (
                                <DashboardKpiCard
                                    question="How much AP cash was paid after reversals?"
                                    value={money(
                                        dashboardWidgets.finance.expense
                                            .ap_cash_out,
                                    )}
                                    context="Vendor payments in the selected period"
                                    tone="neutral"
                                />
                            )}
                        </div>
                        {dashboardWidgets.finance.cash_flow_trend && (
                            <DashboardChart
                                title="AR Cash In / AP Cash Out"
                                description="Monthly base-currency cash entries; latest 12 months within the selected period"
                                kind="bar"
                                data={dashboardWidgets.finance.cash_flow_trend.map(
                                    (point) => ({
                                        label: point.label,
                                        value:
                                            point.cash_in ??
                                            point.ap_cash_out ??
                                            0,
                                        secondaryValue:
                                            point.cash_in !== null &&
                                            point.ap_cash_out !== null
                                                ? point.ap_cash_out
                                                : undefined,
                                        primaryHref: cashMonthHref(
                                            point.label,
                                            dashboardWidgets.finance?.cash_in
                                                ? 'ar'
                                                : 'ap',
                                        ),
                                        secondaryHref:
                                            dashboardWidgets.finance?.cash_in &&
                                            dashboardWidgets.finance?.expense
                                                ? cashMonthHref(
                                                      point.label,
                                                      'ap',
                                                  )
                                                : undefined,
                                    }),
                                )}
                                primaryLabel={
                                    dashboardWidgets.finance.cash_in
                                        ? 'AR Cash In'
                                        : 'AP Cash Out'
                                }
                                secondaryLabel={
                                    dashboardWidgets.finance.cash_in &&
                                    dashboardWidgets.finance.expense
                                        ? 'AP Cash Out'
                                        : undefined
                                }
                            />
                        )}
                        <div className="grid gap-4 lg:grid-cols-2">
                            {dashboardWidgets.finance.ar && (
                                <DashboardBreakdown
                                    title="AR Aging"
                                    description="Outstanding AR by due-date bucket"
                                    items={[
                                        {
                                            label: 'No due date',
                                            value: dashboardWidgets.finance.ar
                                                .aging.no_due_date,
                                            tone: 'neutral',
                                            href: route('invoices.index', {
                                                aging_bucket: 'no_due_date',
                                            }),
                                        },
                                        {
                                            label: 'Current',
                                            value: dashboardWidgets.finance.ar
                                                .aging.current,
                                            tone: 'neutral',
                                            href: route('invoices.index', {
                                                aging_bucket: 'current',
                                            }),
                                        },
                                        {
                                            label: '1–30 days',
                                            value: dashboardWidgets.finance.ar
                                                .aging.days_1_30,
                                            tone: 'pending',
                                            href: route('invoices.index', {
                                                aging_bucket: 'days_1_30',
                                            }),
                                        },
                                        {
                                            label: '31–60 days',
                                            value: dashboardWidgets.finance.ar
                                                .aging.days_31_60,
                                            tone: 'risk',
                                            href: route('invoices.index', {
                                                aging_bucket: 'days_31_60',
                                            }),
                                        },
                                        {
                                            label: '61+ days',
                                            value: dashboardWidgets.finance.ar
                                                .aging.days_61_plus,
                                            tone: 'risk',
                                            href: route('invoices.index', {
                                                aging_bucket: 'days_61_plus',
                                            }),
                                        },
                                    ]}
                                />
                            )}
                            {dashboardWidgets.finance.invoice_status && (
                                <DashboardBreakdown
                                    title="Invoice Status"
                                    description="Invoice count by current status"
                                    items={dashboardWidgets.finance.invoice_status.map(
                                        (row) => ({
                                            label: row.status.replace('_', ' '),
                                            value: row.count,
                                            href: route('invoices.index', {
                                                status: row.status,
                                                from:
                                                    dashboardFilters.start_date ??
                                                    undefined,
                                                to:
                                                    dashboardFilters.end_date ??
                                                    undefined,
                                            }),
                                            tone:
                                                row.status === 'paid'
                                                    ? 'positive'
                                                    : row.status === 'overdue'
                                                      ? 'risk'
                                                      : row.status === 'sent'
                                                        ? 'pending'
                                                        : 'neutral',
                                        }),
                                    )}
                                />
                            )}
                        </div>
                        <div className="grid gap-4 lg:grid-cols-2">
                            {dashboardWidgets.finance.overdue_invoices && (
                                <DashboardList
                                    title="Overdue invoices"
                                    description="Oldest due dates first; current AR snapshot"
                                    emptyMessage="No overdue invoices."
                                    items={dashboardWidgets.finance.overdue_invoices.map(
                                        (invoice) => ({
                                            id: invoice.id,
                                            title: invoice.invoice_no,
                                            detail: `${invoice.due_date} · ${money(invoice.base_balance_due)}`,
                                            routeName: 'invoices.index',
                                            routeParameters: {
                                                overdue: '1',
                                                search: invoice.invoice_no,
                                            },
                                        }),
                                    )}
                                />
                            )}
                            {dashboardWidgets.finance.recent_payments && (
                                <DashboardList
                                    title="Recent payments"
                                    description="Latest payment and reversal entries in the selected period"
                                    emptyMessage="No payments in this period."
                                    items={dashboardWidgets.finance.recent_payments.map(
                                        (payment) => ({
                                            id: payment.id,
                                            title: `${payment.entry_type === 'reversal' ? 'Reversal' : 'Receipt'} · ${money(payment.base_amount)}`,
                                            detail: payment.payment_date,
                                            routeName: 'payments.index',
                                            routeParameters: {
                                                payment_id: payment.id,
                                            },
                                        }),
                                    )}
                                />
                            )}
                        </div>
                        {dashboardWidgets.finance
                            .expenses_awaiting_approval && (
                            <DashboardList
                                title="Expenses awaiting approval"
                                description="Draft expenses in the selected period"
                                emptyMessage="No draft expenses in this period."
                                items={dashboardWidgets.finance.expenses_awaiting_approval.map(
                                    (expense) => ({
                                        id: expense.id,
                                        title: `${expense.expense_no} · ${expense.title}`,
                                        detail: expense.expense_date,
                                        routeName: 'expenses.index',
                                        routeParameters: {
                                            status: 'draft',
                                            search: expense.expense_no,
                                        },
                                    }),
                                )}
                            />
                        )}
                    </section>
                )}

                {dashboardScope === 'delivery' &&
                    dashboardWidgets?.delivery && (
                        <section
                            className="space-y-4"
                            aria-label="Permission-scoped delivery widgets"
                        >
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                {dashboardWidgets.delivery.projects && (
                                    <>
                                        <DashboardKpiCard
                                            question="How many projects are active now?"
                                            value={
                                                dashboardWidgets.delivery
                                                    .projects.active_projects
                                            }
                                            context="Planning, active and on hold"
                                            tone="positive"
                                        />
                                        <DashboardKpiCard
                                            question="What is average active project progress?"
                                            value={
                                                dashboardWidgets.delivery
                                                    .projects
                                                    .average_progress === null
                                                    ? 'N/A'
                                                    : `${dashboardWidgets.delivery.projects.average_progress}%`
                                            }
                                            context="Current project progress, not historical"
                                            tone="neutral"
                                        />
                                    </>
                                )}
                                {dashboardWidgets.delivery.tasks && (
                                    <>
                                        <DashboardKpiCard
                                            question="How many tasks are overdue now?"
                                            value={
                                                dashboardWidgets.delivery.tasks
                                                    .overdue_tasks
                                            }
                                            context="Todo or in progress past due"
                                            tone="risk"
                                        />
                                        <DashboardKpiCard
                                            question="How many open tasks are due this week?"
                                            value={
                                                dashboardWidgets.delivery.tasks
                                                    .due_this_week
                                            }
                                            context="Todo, in progress or blocked"
                                            tone="pending"
                                        />
                                    </>
                                )}
                            </div>
                            <div className="grid gap-4 lg:grid-cols-2">
                                {dashboardWidgets.delivery.projects && (
                                    <DashboardList
                                        title="Project status"
                                        description="Current visible projects by status"
                                        emptyMessage="No visible projects."
                                        items={dashboardWidgets.delivery.projects.status.map(
                                            (row) => ({
                                                id: row.status,
                                                title: row.status.replace(
                                                    '_',
                                                    ' ',
                                                ),
                                                detail: `${row.count} projects`,
                                                routeName: 'projects.index',
                                                routeParameters: {
                                                    status: row.status,
                                                },
                                            }),
                                        )}
                                    />
                                )}
                                {dashboardWidgets.delivery.assignee_load && (
                                    <DashboardList
                                        title="Assignee load"
                                        description="Current open tasks by assignee, top eight"
                                        emptyMessage="No open tasks."
                                        items={dashboardWidgets.delivery.assignee_load.map(
                                            (row) => ({
                                                id:
                                                    row.assignee_id ??
                                                    'unassigned',
                                                title: row.name,
                                                detail: `${row.open_tasks} open tasks`,
                                                routeName: 'tasks.index',
                                                routeParameters: row.assignee_id
                                                    ? {
                                                          assignee_id:
                                                              row.assignee_id,
                                                      }
                                                    : { unassigned: '1' },
                                            }),
                                        )}
                                    />
                                )}
                            </div>
                            <div className="grid gap-4 lg:grid-cols-3">
                                {dashboardWidgets.delivery.overdue_tasks && (
                                    <DashboardList
                                        title="Overdue tasks"
                                        description="Oldest due dates first"
                                        emptyMessage="No overdue tasks."
                                        items={dashboardWidgets.delivery.overdue_tasks.map(
                                            (task) => ({
                                                id: task.id,
                                                title: task.title,
                                                detail: `${task.status} · ${task.due_date}`,
                                                routeName: 'tasks.index',
                                                routeParameters: {
                                                    task_id: task.id,
                                                },
                                            }),
                                        )}
                                    />
                                )}
                                {dashboardWidgets.delivery.due_this_week && (
                                    <DashboardList
                                        title="Due this week"
                                        description="Open tasks due in the current week"
                                        emptyMessage="No tasks due this week."
                                        items={dashboardWidgets.delivery.due_this_week.map(
                                            (task) => ({
                                                id: task.id,
                                                title: task.title,
                                                detail: `${task.status} · ${task.due_date}`,
                                                routeName: 'tasks.index',
                                                routeParameters: {
                                                    task_id: task.id,
                                                },
                                            }),
                                        )}
                                    />
                                )}
                                {dashboardWidgets.delivery
                                    .past_due_projects && (
                                    <DashboardList
                                        title="Past-due projects"
                                        description="Active projects past their due date"
                                        emptyMessage="No past-due projects."
                                        items={dashboardWidgets.delivery.past_due_projects.map(
                                            (project) => ({
                                                id: project.id,
                                                title: `${project.project_code} · ${project.name}`,
                                                detail: project.due_date,
                                                routeName: 'projects.index',
                                                routeParameters: {
                                                    project_id: project.id,
                                                },
                                            }),
                                        )}
                                    />
                                )}
                            </div>
                        </section>
                    )}

                {deliverySummary && dashboardScope !== 'delivery' && (
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
                                title="Budget Remaining"
                                value={money(deliverySummary.budget_remaining)}
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
                {isAdminDashboard &&
                    securityAlerts &&
                    !dashboardWidgets?.admin && (
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
                {isAdminDashboard && !dashboardWidgets?.admin && (
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
            </DashboardLayout>
        </AuthenticatedLayout>
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
            label: 'Billed-minus-expense estimate',
            current: financeSummary.billed_minus_expense_estimate,
            previous: financeSummary.previous.billed_minus_expense_estimate,
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
