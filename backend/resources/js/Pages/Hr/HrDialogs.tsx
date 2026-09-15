import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type User = { id: string; name: string; email?: string };
type Shift = { id: string; name: string };
type LeaveType = {
    id: string;
    code: string;
    name: string;
    is_paid: boolean;
    annual_entitlement_days: string;
};

// -------------------------------------------------------------
// 1. Leave Request Dialog
// -------------------------------------------------------------
export function LeaveRequestDialog({
    open,
    onClose,
    leaveTypes,
}: {
    open: boolean;
    onClose: () => void;
    leaveTypes: LeaveType[];
}) {
    const form = useForm({
        leave_type_id: '',
        starts_on: '',
        ends_on: '',
        reason: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        form.data.leave_type_id !== '' ||
        form.data.starts_on !== '' ||
        form.data.ends_on !== '' ||
        form.data.reason !== '';

    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.leave_type_id) return;
        form.post(route('hr.leave-requests.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="New Leave Request"
                breadcrumb="HR & Attendance / Leave"
                entityLabel="Leave Request"
                draftLabel={
                    form.data.starts_on
                        ? `${form.data.starts_on} to ${form.data.ends_on}`
                        : 'New Leave Request'
                }
                storageKey="leave-request-editor-size"
                formId="leave-request-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.leave_type_id ||
                    !form.data.starts_on ||
                    !form.data.ends_on
                }
                saveLabel="Save Draft"
                status="Draft / Pending Submission"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorReference
                            id="leave-type-id"
                            label="Leave Type"
                            required
                            value={form.data.leave_type_id}
                            error={form.errors.leave_type_id}
                            emptyLabel="Select leave type"
                            options={leaveTypes.map((type) => ({
                                id: type.id,
                                label: `${type.name} (${type.code})${type.is_paid ? '' : ' - unpaid'}`,
                            }))}
                            onChange={(value) =>
                                form.setData('leave_type_id', value)
                            }
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="leave-starts-on"
                                label="Start Date"
                                type="date"
                                required
                                value={form.data.starts_on}
                                error={form.errors.starts_on}
                                onChange={(value) =>
                                    form.setData('starts_on', value)
                                }
                            />
                            <EditorField
                                id="leave-ends-on"
                                label="End Date"
                                type="date"
                                required
                                value={form.data.ends_on}
                                error={form.errors.ends_on}
                                onChange={(value) =>
                                    form.setData('ends_on', value)
                                }
                            />
                        </div>
                        <EditorField
                            id="leave-reason"
                            label="Reason / Notes"
                            multiline
                            value={form.data.reason}
                            error={form.errors.reason}
                            onChange={(value) => form.setData('reason', value)}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 2. HR Attendance Summary Dialog
// -------------------------------------------------------------
export function HrSummaryDialog({
    open,
    onClose,
    users,
}: {
    open: boolean;
    onClose: () => void;
    users: User[];
}) {
    const form = useForm({
        user_id: '',
        period_start: '',
        period_end: '',
        cutoff_date: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        form.data.user_id !== '' ||
        form.data.period_start !== '' ||
        form.data.period_end !== '' ||
        form.data.cutoff_date !== '';

    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.user_id) return;
        form.post(route('hr.summaries.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Create Attendance Summary"
                breadcrumb="HR & Attendance / Summaries"
                entityLabel="Attendance Summary"
                draftLabel={
                    form.data.cutoff_date
                        ? `Cutoff ${form.data.cutoff_date}`
                        : 'Draft Summary'
                }
                storageKey="hr-summary-editor-size"
                formId="hr-summary-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.user_id ||
                    !form.data.period_start ||
                    !form.data.period_end ||
                    !form.data.cutoff_date
                }
                saveLabel="Create Draft Summary"
                status="Draft / Unlocked"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorReference
                            id="summary-user-id"
                            label="Employee"
                            required
                            value={form.data.user_id}
                            error={form.errors.user_id}
                            emptyLabel="Select employee"
                            options={users.map((u) => ({
                                id: u.id,
                                label: u.email
                                    ? `${u.name} (${u.email})`
                                    : u.name,
                            }))}
                            onChange={(value) => form.setData('user_id', value)}
                        />
                        <div className="grid gap-4 sm:grid-cols-3">
                            <EditorField
                                id="summary-period-start"
                                label="Period Start"
                                type="date"
                                required
                                value={form.data.period_start}
                                error={form.errors.period_start}
                                onChange={(value) =>
                                    form.setData('period_start', value)
                                }
                            />
                            <EditorField
                                id="summary-period-end"
                                label="Period End"
                                type="date"
                                required
                                value={form.data.period_end}
                                error={form.errors.period_end}
                                onChange={(value) =>
                                    form.setData('period_end', value)
                                }
                            />
                            <EditorField
                                id="summary-cutoff-date"
                                label="Cutoff Date"
                                type="date"
                                required
                                value={form.data.cutoff_date}
                                error={form.errors.cutoff_date}
                                onChange={(value) =>
                                    form.setData('cutoff_date', value)
                                }
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 3. Shift Dialog
// -------------------------------------------------------------
export function ShiftDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        code: '',
        name: '',
        starts_at: '09:00',
        ends_at: '18:00',
        break_minutes: '60',
        work_minutes_per_day: '480',
        is_active: '1',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        form.data.code !== '' ||
        form.data.name !== '' ||
        form.data.starts_at !== '09:00' ||
        form.data.ends_at !== '18:00';

    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.code || !form.data.name) return;
        form.post(route('hr.shifts.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Create Work Shift"
                breadcrumb="HR & Attendance / Configuration"
                entityLabel="Work Shift"
                draftLabel={form.data.name || form.data.code || 'New Shift'}
                storageKey="shift-editor-size"
                formId="shift-form"
                processing={form.processing}
                saveDisabled={!form.data.code || !form.data.name}
                saveLabel="Save Shift"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="shift-code"
                                label="Shift Code"
                                required
                                value={form.data.code}
                                error={form.errors.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                            />
                            <EditorField
                                id="shift-name"
                                label="Shift Name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="shift-starts-at"
                                label="Start Time"
                                type="time"
                                required
                                value={form.data.starts_at}
                                error={form.errors.starts_at}
                                onChange={(value) =>
                                    form.setData('starts_at', value)
                                }
                            />
                            <EditorField
                                id="shift-ends-at"
                                label="End Time"
                                type="time"
                                required
                                value={form.data.ends_at}
                                error={form.errors.ends_at}
                                onChange={(value) =>
                                    form.setData('ends_at', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="shift-break-minutes"
                                label="Break (Minutes)"
                                type="number"
                                required
                                value={form.data.break_minutes}
                                error={form.errors.break_minutes}
                                onChange={(value) =>
                                    form.setData('break_minutes', value)
                                }
                            />
                            <EditorField
                                id="shift-work-minutes"
                                label="Work Minutes Per Day"
                                type="number"
                                required
                                value={form.data.work_minutes_per_day}
                                error={form.errors.work_minutes_per_day}
                                onChange={(value) =>
                                    form.setData('work_minutes_per_day', value)
                                }
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 4. Leave Type Dialog
// -------------------------------------------------------------
export function LeaveTypeDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        code: '',
        name: '',
        annual_entitlement_days: '0',
        carry_over_limit_days: '0',
        is_paid: true,
        is_active: '1',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.code !== '' || form.data.name !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.code || !form.data.name) return;
        form.transform((data) => ({
            ...data,
            is_paid: data.is_paid ? '1' : '0',
        }));
        form.post(route('hr.leave-types.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Create Leave Type"
                breadcrumb="HR & Attendance / Configuration"
                entityLabel="Leave Type"
                draftLabel={
                    form.data.name || form.data.code || 'New Leave Type'
                }
                storageKey="leave-type-editor-size"
                formId="leave-type-form"
                processing={form.processing}
                saveDisabled={!form.data.code || !form.data.name}
                saveLabel="Save Leave Type"
                status="Policy Configuration"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="leave-type-code"
                                label="Code"
                                required
                                value={form.data.code}
                                error={form.errors.code}
                                onChange={(value) =>
                                    form.setData('code', value)
                                }
                            />
                            <EditorField
                                id="leave-type-name"
                                label="Name"
                                required
                                value={form.data.name}
                                error={form.errors.name}
                                onChange={(value) =>
                                    form.setData('name', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="leave-type-entitlement"
                                label="Annual Entitlement (Days)"
                                type="number"
                                step="0.5"
                                required
                                value={form.data.annual_entitlement_days}
                                error={form.errors.annual_entitlement_days}
                                onChange={(value) =>
                                    form.setData(
                                        'annual_entitlement_days',
                                        value,
                                    )
                                }
                            />
                            <EditorField
                                id="leave-type-carry-over"
                                label="Carry-over Limit (Days)"
                                type="number"
                                step="0.5"
                                required
                                value={form.data.carry_over_limit_days}
                                error={form.errors.carry_over_limit_days}
                                onChange={(value) =>
                                    form.setData('carry_over_limit_days', value)
                                }
                            />
                        </div>
                        <label className="flex items-center gap-2 text-sm text-slate-700 dark:text-slate-200">
                            <input
                                type="checkbox"
                                checked={form.data.is_paid}
                                onChange={(e) =>
                                    form.setData('is_paid', e.target.checked)
                                }
                                className="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-800"
                            />
                            <span>
                                Paid Leave (eligible for salary calculation)
                            </span>
                        </label>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 5. Work Profile Dialog
// -------------------------------------------------------------
export function WorkProfileDialog({
    open,
    onClose,
    users,
    shifts,
}: {
    open: boolean;
    onClose: () => void;
    users: User[];
    shifts: Shift[];
}) {
    const form = useForm({
        user_id: '',
        employee_code: '',
        employee_shift_id: '',
        manager_user_id: '',
        employment_type: 'employee',
        started_on: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.user_id !== '' || form.data.employee_code !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.user_id) return;
        form.post(route('hr.work-profiles.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Assign Employee Work Profile"
                breadcrumb="HR & Attendance / Staff"
                entityLabel="Work Profile"
                draftLabel={form.data.employee_code || 'Work Profile'}
                storageKey="work-profile-editor-size"
                formId="work-profile-form"
                processing={form.processing}
                saveDisabled={!form.data.user_id}
                saveLabel="Save Profile"
                status="Active"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorReference
                            id="profile-user-id"
                            label="Employee"
                            required
                            value={form.data.user_id}
                            error={form.errors.user_id}
                            emptyLabel="Select employee"
                            options={users.map((u) => ({
                                id: u.id,
                                label: u.name,
                            }))}
                            onChange={(value) => form.setData('user_id', value)}
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="profile-employee-code"
                                label="Employee Code"
                                value={form.data.employee_code}
                                error={form.errors.employee_code}
                                onChange={(value) =>
                                    form.setData('employee_code', value)
                                }
                            />
                            <EditorReference
                                id="profile-shift-id"
                                label="Assigned Shift"
                                value={form.data.employee_shift_id}
                                error={form.errors.employee_shift_id}
                                emptyLabel="No shift assigned"
                                options={shifts.map((s) => ({
                                    id: s.id,
                                    label: s.name,
                                }))}
                                onChange={(value) =>
                                    form.setData('employee_shift_id', value)
                                }
                            />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="profile-manager-id"
                                label="Direct Manager"
                                value={form.data.manager_user_id}
                                error={form.errors.manager_user_id}
                                emptyLabel="No manager"
                                options={users.map((u) => ({
                                    id: u.id,
                                    label: u.name,
                                }))}
                                onChange={(value) =>
                                    form.setData('manager_user_id', value)
                                }
                            />
                            <EditorReference
                                id="profile-employment-type"
                                label="Employment Type"
                                required
                                value={form.data.employment_type}
                                error={form.errors.employment_type}
                                options={[
                                    { id: 'employee', label: 'Employee' },
                                    { id: 'contractor', label: 'Contractor' },
                                    { id: 'intern', label: 'Intern' },
                                ]}
                                onChange={(value) =>
                                    form.setData('employment_type', value)
                                }
                            />
                        </div>
                        <EditorField
                            id="profile-started-on"
                            label="Employment Start Date"
                            type="date"
                            value={form.data.started_on}
                            error={form.errors.started_on}
                            onChange={(value) =>
                                form.setData('started_on', value)
                            }
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

// -------------------------------------------------------------
// 6. Holiday Dialog
// -------------------------------------------------------------
export function HolidayDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const form = useForm({
        holiday_date: '',
        name: '',
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty = form.data.holiday_date !== '' || form.data.name !== '';
    const guard = useDiscardGuard(isDirty, form.processing);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || !form.data.holiday_date || !form.data.name)
            return;
        form.post(route('hr.holidays.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    };

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Add Company Holiday"
                breadcrumb="HR & Attendance / Calendar"
                entityLabel="Holiday"
                draftLabel={
                    form.data.name || form.data.holiday_date || 'New Holiday'
                }
                storageKey="holiday-editor-size"
                formId="holiday-form"
                processing={form.processing}
                saveDisabled={!form.data.holiday_date || !form.data.name}
                saveLabel="Save Holiday"
                status="Calendar Date"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <EditorField
                            id="holiday-date"
                            label="Date"
                            type="date"
                            required
                            value={form.data.holiday_date}
                            error={form.errors.holiday_date}
                            onChange={(value) =>
                                form.setData('holiday_date', value)
                            }
                        />
                        <EditorField
                            id="holiday-name"
                            label="Holiday Name"
                            required
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(value) => form.setData('name', value)}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
