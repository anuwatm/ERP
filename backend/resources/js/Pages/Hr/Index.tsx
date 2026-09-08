import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

type User = { id: string; name: string; email?: string };
type LeaveType = {
    id: string;
    code: string;
    name: string;
    is_paid: boolean;
    annual_entitlement_days: string;
};
type Summary = {
    id: string;
    user: User;
    period_start: string;
    period_end: string;
    cutoff_date: string;
    scheduled_minutes: number;
    worked_minutes: number;
    overtime_minutes: number;
    paid_leave_days: string;
    unpaid_leave_days: string;
    status: string;
};
type Attendance = {
    id: string;
    user_id: string;
    user: User;
    work_date: string;
    clock_in_at: string;
    clock_out_at: string | null;
    shift?: { name: string } | null;
};
type LeaveBalance = {
    id: string;
    leave_year: number;
    opening_days: string;
    accrued_days: string;
    carry_over_days: string;
    used_days: string;
    leave_type: { name: string };
};
type LeaveRequestRow = {
    id: string;
    user_id: string;
    user: User;
    leave_type: { name: string };
    starts_on: string;
    ends_on: string;
    total_days: string;
    status: string;
};
type Shift = { id: string; name: string };
type Holiday = { id: string; holiday_date: string; name: string };
type WorkProfile = {
    user_id: string;
    shift?: { name: string; starts_at: string; ends_at: string } | null;
} | null;
type Props = {
    workProfile: WorkProfile;
    attendance: Attendance[];
    leaveBalances: LeaveBalance[];
    leaveRequests: LeaveRequestRow[];
    summaries: Summary[];
    shifts: Shift[];
    leaveTypes: LeaveType[];
    holidays: Holiday[];
    users: User[];
    attendancePolicy: {
        capture_ip: boolean;
        capture_gps: boolean;
        require_employee_consent: boolean;
    };
    can: { manage: boolean; summaryManage: boolean; teamView: boolean };
};

export default function HrIndex({
    workProfile,
    attendance,
    leaveBalances,
    leaveRequests,
    summaries,
    shifts,
    leaveTypes,
    holidays,
    users,
    attendancePolicy,
    can,
}: Props) {
    const post = (event: FormEvent<HTMLFormElement>, name: string) => {
        event.preventDefault();
        router.post(
            route(name),
            Object.fromEntries(new FormData(event.currentTarget)),
            { preserveScroll: true },
        );
    };
    const openAttendance = attendance.find(
        (record: Attendance) =>
            !record.clock_out_at && record.user_id === workProfile?.user_id,
    );
    const hours = (minutes: number) =>
        `${Math.floor(minutes / 60)}h ${minutes % 60}m`;

    return (
        <AuthenticatedLayout>
            <Head title="HR & Attendance" />
            <div className="space-y-6">
                <PageHeader
                    title="HR & Attendance"
                    description="Attendance, leave balances and locked payroll-ready time summaries."
                />
                <div className="grid gap-6 lg:grid-cols-3">
                    <Card
                        title="Clock"
                        description={
                            workProfile?.shift
                                ? `${workProfile.shift.name}: ${workProfile.shift.starts_at} - ${workProfile.shift.ends_at}`
                                : 'No shift assigned'
                        }
                    >
                        <div className="space-y-3">
                            <div className="text-sm text-slate-600 dark:text-slate-200">
                                {openAttendance
                                    ? `Clocked in ${openAttendance.clock_in_at}`
                                    : 'No open attendance record'}
                            </div>
                            <form
                                onSubmit={(e) =>
                                    post(
                                        e,
                                        openAttendance
                                            ? 'hr.clock-out'
                                            : 'hr.clock-in',
                                    )
                                }
                                className="space-y-2"
                            >
                                <input
                                    type="hidden"
                                    name="source"
                                    value="web"
                                />
                                {(attendancePolicy.capture_ip ||
                                    attendancePolicy.capture_gps) && (
                                    <label className="flex gap-2 text-sm">
                                        <input
                                            required={
                                                attendancePolicy.require_employee_consent
                                            }
                                            type="checkbox"
                                            name="privacy_consent"
                                            value="1"
                                        />{' '}
                                        I consent to optional attendance data
                                        collection.
                                    </label>
                                )}
                                {attendancePolicy.capture_gps && (
                                    <div className="grid grid-cols-2 gap-2">
                                        <input
                                            required
                                            name="latitude"
                                            type="number"
                                            step="0.0000001"
                                            placeholder="Latitude"
                                            className="rounded-md border-slate-300"
                                        />
                                        <input
                                            required
                                            name="longitude"
                                            type="number"
                                            step="0.0000001"
                                            placeholder="Longitude"
                                            className="rounded-md border-slate-300"
                                        />
                                    </div>
                                )}
                                <PrimaryButton>
                                    {openAttendance ? 'Clock Out' : 'Clock In'}
                                </PrimaryButton>
                            </form>
                        </div>
                    </Card>
                    <Card title="My Leave Balance">
                        <div className="space-y-2 text-sm">
                            {leaveBalances.length === 0 && (
                                <p className="text-slate-500">
                                    No leave balance configured.
                                </p>
                            )}
                            {leaveBalances.map((balance) => (
                                <div
                                    key={balance.id}
                                    className="flex justify-between border-b border-slate-100 pb-2 dark:border-slate-800"
                                >
                                    <span>
                                        {balance.leave_type.name} (
                                        {balance.leave_year})
                                    </span>
                                    <span>
                                        {Number(balance.opening_days) +
                                            Number(balance.accrued_days) +
                                            Number(balance.carry_over_days) -
                                            Number(balance.used_days)}{' '}
                                        days
                                    </span>
                                </div>
                            ))}
                        </div>
                    </Card>
                    <Card
                        title="New Leave Request"
                        description="Submit records the balance. Approval workflow starts in Phase 20."
                    >
                        <form
                            className="grid gap-2"
                            onSubmit={(e) => post(e, 'hr.leave-requests.store')}
                        >
                            <select
                                required
                                name="leave_type_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">Leave type</option>
                                {leaveTypes.map((type: LeaveType) => (
                                    <option key={type.id} value={type.id}>
                                        {type.name}
                                        {type.is_paid ? '' : ' (unpaid)'}
                                    </option>
                                ))}
                            </select>
                            <div className="grid grid-cols-2 gap-2">
                                <input
                                    required
                                    name="starts_on"
                                    type="date"
                                    className="rounded-md border-slate-300"
                                />
                                <input
                                    required
                                    name="ends_on"
                                    type="date"
                                    className="rounded-md border-slate-300"
                                />
                            </div>
                            <textarea
                                name="reason"
                                placeholder="Reason"
                                className="rounded-md border-slate-300"
                            />
                            <PrimaryButton>Save Draft</PrimaryButton>
                        </form>
                    </Card>
                </div>
                <Card title="Leave Requests">
                    <DataTable
                        data={leaveRequests}
                        keyExtractor={(row: LeaveRequestRow) => row.id}
                        columns={[
                            {
                                header: 'Employee',
                                accessor: (row: LeaveRequestRow) =>
                                    row.user.name,
                            },
                            {
                                header: 'Type',
                                accessor: (row: LeaveRequestRow) =>
                                    row.leave_type.name,
                            },
                            {
                                header: 'Dates',
                                accessor: (row: LeaveRequestRow) =>
                                    `${row.starts_on} to ${row.ends_on}`,
                            },
                            {
                                header: 'Days',
                                accessor: (row: LeaveRequestRow) =>
                                    row.total_days,
                            },
                            {
                                header: 'Status',
                                accessor: (row: LeaveRequestRow) => row.status,
                            },
                            {
                                header: 'Action',
                                accessor: (row: LeaveRequestRow) =>
                                    row.user_id === workProfile?.user_id &&
                                    row.status === 'draft' ? (
                                        <button
                                            className="text-indigo-700"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'hr.leave-requests.submit',
                                                        row.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Submit
                                        </button>
                                    ) : row.user_id === workProfile?.user_id &&
                                      row.status === 'submitted' ? (
                                        <div className="flex gap-3">
                                            <button
                                                className="text-indigo-700"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'workflows.submit',
                                                            [
                                                                'leave_request',
                                                                row.id,
                                                            ],
                                                        ),
                                                    )
                                                }
                                            >
                                                Request approval
                                            </button>
                                            <button
                                                className="text-rose-700"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'hr.leave-requests.cancel',
                                                            row.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Cancel
                                            </button>
                                        </div>
                                    ) : (
                                        '-'
                                    ),
                            },
                        ]}
                    />
                </Card>
                <Card title="Attendance Records">
                    <DataTable
                        data={attendance}
                        keyExtractor={(row: Attendance) => row.id}
                        columns={[
                            {
                                header: 'Employee',
                                accessor: (row: Attendance) => row.user.name,
                            },
                            {
                                header: 'Date',
                                accessor: (row: Attendance) => row.work_date,
                            },
                            {
                                header: 'In',
                                accessor: (row: Attendance) => row.clock_in_at,
                            },
                            {
                                header: 'Out',
                                accessor: (row: Attendance) =>
                                    row.clock_out_at ?? 'Open',
                            },
                            {
                                header: 'Shift',
                                accessor: (row: Attendance) =>
                                    row.shift?.name ?? '-',
                            },
                        ]}
                    />
                </Card>
                <Card
                    title="Payroll Summary Bridge"
                    description="Locked summaries do not create payroll items or GL entries automatically."
                >
                    {can.summaryManage && (
                        <form
                            className="mb-5 grid gap-2 md:grid-cols-4"
                            onSubmit={(e) => post(e, 'hr.summaries.store')}
                        >
                            <select
                                required
                                name="user_id"
                                className="rounded-md border-slate-300"
                            >
                                <option value="">Employee</option>
                                {users.map((user: User) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            <input
                                required
                                name="period_start"
                                type="date"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="period_end"
                                type="date"
                                className="rounded-md border-slate-300"
                            />
                            <input
                                required
                                name="cutoff_date"
                                type="date"
                                className="rounded-md border-slate-300"
                            />
                            <div className="md:col-span-4">
                                <PrimaryButton>
                                    Create Draft Summary
                                </PrimaryButton>
                            </div>
                        </form>
                    )}
                    <DataTable
                        data={summaries}
                        keyExtractor={(row: Summary) => row.id}
                        columns={[
                            {
                                header: 'Employee / Period',
                                accessor: (row: Summary) => (
                                    <>
                                        <div>{row.user.name}</div>
                                        <div className="text-xs text-slate-500">
                                            {row.period_start} to{' '}
                                            {row.period_end}; cutoff{' '}
                                            {row.cutoff_date}
                                        </div>
                                    </>
                                ),
                            },
                            {
                                header: 'Hours',
                                accessor: (row: Summary) =>
                                    `${hours(row.worked_minutes)} / OT ${hours(row.overtime_minutes)}`,
                            },
                            {
                                header: 'Leave',
                                accessor: (row: Summary) =>
                                    `Paid ${row.paid_leave_days}, LWOP ${row.unpaid_leave_days}`,
                            },
                            {
                                header: 'Status',
                                accessor: (row: Summary) => row.status,
                            },
                            {
                                header: 'Action',
                                accessor: (row: Summary) =>
                                    can.summaryManage &&
                                    row.status === 'draft' ? (
                                        <button
                                            className="text-indigo-700"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'hr.summaries.lock',
                                                        row.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Lock
                                        </button>
                                    ) : can.summaryManage &&
                                      row.status === 'locked' ? (
                                        <button
                                            className="text-rose-700"
                                            onClick={() =>
                                                router.post(
                                                    route(
                                                        'hr.summaries.reverse',
                                                        row.id,
                                                    ),
                                                )
                                            }
                                        >
                                            Reverse
                                        </button>
                                    ) : (
                                        '-'
                                    ),
                            },
                        ]}
                    />
                </Card>
                {can.manage && (
                    <div className="grid gap-6 xl:grid-cols-2">
                        <Card title="HR Setup">
                            <div className="grid gap-5">
                                <form
                                    className="grid gap-2 md:grid-cols-2"
                                    onSubmit={(e) => post(e, 'hr.shifts.store')}
                                >
                                    <input
                                        required
                                        name="code"
                                        placeholder="Shift code"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="name"
                                        placeholder="Shift name"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="starts_at"
                                        type="time"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="ends_at"
                                        type="time"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="break_minutes"
                                        type="number"
                                        defaultValue="60"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="work_minutes_per_day"
                                        type="number"
                                        defaultValue="480"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="1"
                                    />
                                    <div className="md:col-span-2">
                                        <PrimaryButton>
                                            Save Shift
                                        </PrimaryButton>
                                    </div>
                                </form>
                                <form
                                    className="grid gap-2 md:grid-cols-2"
                                    onSubmit={(e) =>
                                        post(e, 'hr.leave-types.store')
                                    }
                                >
                                    <input
                                        required
                                        name="code"
                                        placeholder="Leave code"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="name"
                                        placeholder="Leave name"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="annual_entitlement_days"
                                        type="number"
                                        step="0.5"
                                        defaultValue="0"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="carry_over_limit_days"
                                        type="number"
                                        step="0.5"
                                        defaultValue="0"
                                        className="rounded-md border-slate-300"
                                    />
                                    <label className="text-sm">
                                        <input
                                            type="checkbox"
                                            name="is_paid"
                                            value="1"
                                            defaultChecked
                                        />{' '}
                                        Paid leave
                                    </label>
                                    <input
                                        type="hidden"
                                        name="is_active"
                                        value="1"
                                    />
                                    <div className="md:col-span-2">
                                        <PrimaryButton>
                                            Save Leave Type
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </div>
                        </Card>
                        <Card title="Employee Profile & Calendar">
                            <div className="grid gap-5">
                                <form
                                    className="grid gap-2 md:grid-cols-2"
                                    onSubmit={(e) =>
                                        post(e, 'hr.work-profiles.store')
                                    }
                                >
                                    <select
                                        required
                                        name="user_id"
                                        className="rounded-md border-slate-300"
                                    >
                                        <option value="">Employee</option>
                                        {users.map((user: User) => (
                                            <option
                                                key={user.id}
                                                value={user.id}
                                            >
                                                {user.name}
                                            </option>
                                        ))}
                                    </select>
                                    <input
                                        name="employee_code"
                                        placeholder="Employee code"
                                        className="rounded-md border-slate-300"
                                    />
                                    <select
                                        name="employee_shift_id"
                                        className="rounded-md border-slate-300"
                                    >
                                        <option value="">No shift</option>
                                        {shifts.map((shift) => (
                                            <option
                                                key={shift.id}
                                                value={shift.id}
                                            >
                                                {shift.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        name="manager_user_id"
                                        className="rounded-md border-slate-300"
                                    >
                                        <option value="">No manager</option>
                                        {users.map((user: User) => (
                                            <option
                                                key={user.id}
                                                value={user.id}
                                            >
                                                {user.name}
                                            </option>
                                        ))}
                                    </select>
                                    <select
                                        name="employment_type"
                                        defaultValue="employee"
                                        className="rounded-md border-slate-300"
                                    >
                                        <option value="employee">
                                            Employee
                                        </option>
                                        <option value="contractor">
                                            Contractor
                                        </option>
                                        <option value="intern">Intern</option>
                                    </select>
                                    <input
                                        name="started_on"
                                        type="date"
                                        className="rounded-md border-slate-300"
                                    />
                                    <div className="md:col-span-2">
                                        <PrimaryButton>
                                            Save Work Profile
                                        </PrimaryButton>
                                    </div>
                                </form>
                                <form
                                    className="grid gap-2 md:grid-cols-2"
                                    onSubmit={(e) =>
                                        post(e, 'hr.holidays.store')
                                    }
                                >
                                    <input
                                        required
                                        name="holiday_date"
                                        type="date"
                                        className="rounded-md border-slate-300"
                                    />
                                    <input
                                        required
                                        name="name"
                                        placeholder="Holiday name"
                                        className="rounded-md border-slate-300"
                                    />
                                    <div className="md:col-span-2">
                                        <PrimaryButton>
                                            Save Holiday
                                        </PrimaryButton>
                                    </div>
                                </form>
                                <div className="text-sm text-slate-600 dark:text-slate-200">
                                    {holidays.map((holiday) => (
                                        <div key={holiday.id}>
                                            {holiday.holiday_date}:{' '}
                                            {holiday.name}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </Card>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
