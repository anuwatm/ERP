import {
    DashboardKpiCard,
    DashboardLayout,
    DashboardList,
} from '@/Components/Dashboard';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Leave = {
    id: string;
    employee: string | null;
    starts_on: string | null;
    ends_on: string | null;
};
type Run = {
    id: string;
    run_no: string;
    period_end: string | null;
    status: string;
};

export default function HrDashboard({
    can,
    pendingLeaveCount,
    pendingLeave,
    onLeaveToday,
    latestPayrollRun,
}: {
    can: { hr: boolean; payroll: boolean };
    pendingLeaveCount: number | null;
    pendingLeave: Leave[] | null;
    onLeaveToday: Leave[] | null;
    latestPayrollRun: Run | null;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="HR / Payroll Dashboard" />
            <DashboardLayout
                title="HR / Payroll"
                description="Current leave activity and the latest payroll run, scoped by permission."
            >
                <div className="grid gap-4 sm:grid-cols-3">
                    {can.hr && (
                        <>
                            <DashboardKpiCard
                                question="Active Employees"
                                state="unavailable"
                                context="Employment status and department mapping are not defined."
                            />
                            <DashboardKpiCard
                                question="Attendance Present"
                                state="unavailable"
                                context="Schedule, absence and holiday rules need a validated definition."
                            />
                            <DashboardKpiCard
                                question="Pending Leave"
                                value={pendingLeaveCount ?? 0}
                                context="Submitted leave requests awaiting decision"
                                tone="pending"
                            />
                        </>
                    )}
                    {can.payroll && (
                        <DashboardKpiCard
                            question="Latest Payroll Run"
                            value={latestPayrollRun?.run_no ?? 'None'}
                            context={
                                latestPayrollRun
                                    ? `${latestPayrollRun.status} · period ended ${latestPayrollRun.period_end ?? 'unknown'}`
                                    : 'No payroll run'
                            }
                        />
                    )}
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    {can.hr && (
                        <>
                            <DashboardList
                                title="Headcount by Department"
                                description="Current organization structure"
                                items={[]}
                                state="unavailable"
                                unavailableMessage="Employee-to-department relationship has not been defined."
                                emptyMessage="No employees."
                            />
                            <DashboardList
                                title="Attendance Trend"
                                description="Scheduled presence over time"
                                items={[]}
                                state="unavailable"
                                unavailableMessage="Attendance presence and schedule rules need validation."
                                emptyMessage="No attendance."
                            />
                            <DashboardList
                                title="Pending Leave"
                                description="Submitted leave requests"
                                emptyMessage="No pending leave."
                                items={(pendingLeave ?? []).map((leave) => ({
                                    id: leave.id,
                                    title: leave.employee ?? 'Employee',
                                    detail: `${leave.starts_on ?? '?'} – ${leave.ends_on ?? '?'}`,
                                    routeName: 'hr.index',
                                    routeParameters: {
                                        leave_request_id: leave.id,
                                    },
                                }))}
                            />
                            <DashboardList
                                title="Employees on Leave Today"
                                description="Up to 10 approved leave requests overlapping today"
                                emptyMessage="No approved leave today."
                                items={(onLeaveToday ?? []).map((leave) => ({
                                    id: leave.id,
                                    title: leave.employee ?? 'Employee',
                                    detail: `${leave.starts_on ?? '?'} – ${leave.ends_on ?? '?'}`,
                                    routeName: 'hr.index',
                                    routeParameters: {
                                        leave_request_id: leave.id,
                                    },
                                }))}
                            />
                        </>
                    )}
                    {can.payroll && latestPayrollRun && (
                        <DashboardList
                            title="Latest Payroll Run"
                            description="Most recent period end"
                            emptyMessage="No payroll run."
                            items={[
                                {
                                    id: latestPayrollRun.id,
                                    title: latestPayrollRun.run_no,
                                    detail: `${latestPayrollRun.status} · ${latestPayrollRun.period_end ?? '?'}`,
                                    routeName: 'payroll.index',
                                    routeParameters: {
                                        run_id: latestPayrollRun.id,
                                    },
                                },
                            ]}
                        />
                    )}
                </div>
            </DashboardLayout>
        </AuthenticatedLayout>
    );
}
