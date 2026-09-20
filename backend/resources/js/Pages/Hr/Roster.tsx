import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Shift = {
    id: string;
    code: string;
    name: string;
    starts_at: string;
    ends_at: string;
    break_minutes: number;
    work_minutes_per_day: number;
    ot_rate_normal: number;
    ot_rate_holiday: number;
    grace_period_minutes: number;
    overtime_starts_after_minutes: number;
};

type ScheduleItem = {
    id: string;
    employee_id: string;
    employee_shift_id?: string | null;
    schedule_date: string;
    is_day_off: boolean;
    note?: string | null;
    shift?: Shift | null;
};

type EmployeeRow = {
    id: string;
    code: string;
    name: string;
    position?: string | null;
    department?: string | null;
    branch?: string | null;
    default_shift?: Shift | null;
    schedules: Record<string, ScheduleItem>;
};

type Ref = { id: string; name: string; branch_id?: string };

type Props = {
    month: string;
    daysInMonth: number;
    employees: EmployeeRow[];
    shifts: Shift[];
    branches: Ref[];
    departments: Ref[];
    filters: {
        branch_id?: string | null;
        department_id?: string | null;
        month: string;
    };
    canManage: boolean;
};

export default function Roster({
    month,
    daysInMonth,
    employees,
    shifts,
    branches,
    departments,
    filters,
    canManage,
}: Props) {
    const [selectedCell, setSelectedCell] = useState<{
        employee: EmployeeRow;
        dateStr: string;
        current?: ScheduleItem;
    } | null>(null);

    const [batchOpen, setBatchOpen] = useState(false);

    // Filter changes
    const updateFilter = (key: string, value: string) => {
        router.get(
            route('hr.roster.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true }
        );
    };

    // Single schedule form
    const singleForm = useForm({
        employee_id: '',
        schedule_date: '',
        employee_shift_id: '',
        is_day_off: false,
        note: '',
    });

    const openSingleSchedule = (employee: EmployeeRow, day: number) => {
        if (!canManage) return;
        const dayStr = String(day).padStart(2, '0');
        const dateStr = `${month}-${dayStr}`;
        const current = employee.schedules[dateStr];

        singleForm.setData({
            employee_id: employee.id,
            schedule_date: dateStr,
            employee_shift_id: current?.employee_shift_id ?? employee.default_shift?.id ?? '',
            is_day_off: current?.is_day_off ?? false,
            note: current?.note ?? '',
        });

        setSelectedCell({ employee, dateStr, current });
    };

    const submitSingleSchedule = (e: FormEvent) => {
        e.preventDefault();
        singleForm.post(route('hr.roster.schedules.store'), {
            preserveScroll: true,
            onSuccess: () => setSelectedCell(null),
        });
    };

    // Batch assignment form
    const batchForm = useForm<{
        branch_id: string;
        department_id: string;
        employee_shift_id: string;
        is_day_off: boolean;
        start_date: string;
        end_date: string;
        days_of_week: number[];
        note: string;
    }>({
        branch_id: filters.branch_id ?? '',
        department_id: filters.department_id ?? '',
        employee_shift_id: shifts[0]?.id ?? '',
        is_day_off: false,
        start_date: `${month}-01`,
        end_date: `${month}-${String(daysInMonth).padStart(2, '0')}`,
        days_of_week: [1, 2, 3, 4, 5], // Mon-Fri
        note: '',
    });

    const toggleDayOfWeek = (dow: number) => {
        const current = batchForm.data.days_of_week;
        if (current.includes(dow)) {
            batchForm.setData('days_of_week', current.filter((d) => d !== dow));
        } else {
            batchForm.setData('days_of_week', [...current, dow]);
        }
    };

    const submitBatch = (e: FormEvent) => {
        e.preventDefault();
        batchForm.post(route('hr.roster.batch.store'), {
            preserveScroll: true,
            onSuccess: () => setBatchOpen(false),
        });
    };

    const days = useMemo(() => Array.from({ length: daysInMonth }, (_, i) => i + 1), [daysInMonth]);

    return (
        <AuthenticatedLayout>
            <Head title="Shift Roster Management" />

            <div className="space-y-6">
                <PageHeader
                    title="Shift Roster Management"
                    description="Schedule daily shifts and rest days for factory and field employees."
                    actions={
                        canManage ? (
                            <PrimaryButton onClick={() => setBatchOpen(true)}>
                                Batch Shift Assignment
                            </PrimaryButton>
                        ) : undefined
                    }
                />

                {/* Filter Bar */}
                <div className="flex flex-wrap items-center gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300">Month</label>
                        <input
                            type="month"
                            value={month}
                            onChange={(e) => updateFilter('month', e.target.value)}
                            className="mt-1 rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900"
                        />
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300">Branch</label>
                        <select
                            value={filters.branch_id ?? ''}
                            onChange={(e) => updateFilter('branch_id', e.target.value)}
                            className="mt-1 rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900"
                        >
                            <option value="">All branches</option>
                            {branches.map((b) => (
                                <option key={b.id} value={b.id}>{b.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300">Department</label>
                        <select
                            value={filters.department_id ?? ''}
                            onChange={(e) => updateFilter('department_id', e.target.value)}
                            className="mt-1 rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900"
                        >
                            <option value="">All departments</option>
                            {departments.map((d) => (
                                <option key={d.id} value={d.id}>{d.name}</option>
                            ))}
                        </select>
                    </div>
                    <div className="ml-auto flex items-center gap-4 text-xs">
                        <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-blue-500" /> Scheduled</span>
                        <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-slate-300 dark:bg-slate-600" /> Default</span>
                        <span className="flex items-center gap-1.5"><span className="h-3 w-3 rounded bg-rose-400" /> Day Off</span>
                    </div>
                </div>

                {/* Matrix Grid */}
                <div className="overflow-x-auto rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <table className="w-full border-collapse text-left text-xs">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50 dark:border-slate-700 dark:bg-slate-900/50">
                                <th className="sticky left-0 z-20 bg-slate-50 p-3 font-semibold text-slate-700 dark:bg-slate-900 dark:text-slate-200" style={{ minWidth: '220px' }}>
                                    Employee
                                </th>
                                {days.map((day) => {
                                    const dateObj = new Date(`${month}-${String(day).padStart(2, '0')}`);
                                    const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;
                                    return (
                                        <th
                                            key={day}
                                            className={`p-2 text-center font-medium ${isWeekend ? 'bg-slate-100 text-rose-600 dark:bg-slate-800/80 dark:text-rose-400' : 'text-slate-600 dark:text-slate-300'}`}
                                            style={{ minWidth: '42px' }}
                                        >
                                            <div>{day}</div>
                                            <div className="text-[10px] uppercase font-normal">{dateObj.toLocaleDateString('en-US', { weekday: 'narrow' })}</div>
                                        </th>
                                    );
                                })}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
                            {employees.length === 0 ? (
                                <tr>
                                    <td colSpan={daysInMonth + 1} className="p-6 text-center text-slate-500">
                                        No active employees found matching filters.
                                    </td>
                                </tr>
                            ) : (
                                employees.map((emp) => (
                                    <tr key={emp.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30">
                                        <td className="sticky left-0 z-10 bg-white p-2.5 dark:bg-slate-800">
                                            <div className="font-semibold text-slate-900 dark:text-slate-100">{emp.name}</div>
                                            <div className="text-[11px] text-slate-500">{emp.code} • {emp.department ?? 'No dept'}</div>
                                        </td>
                                        {days.map((day) => {
                                            const dayStr = String(day).padStart(2, '0');
                                            const dateStr = `${month}-${dayStr}`;
                                            const schedule = emp.schedules[dateStr];
                                            const isCustom = !!schedule;
                                            const isDayOff = schedule ? schedule.is_day_off : false;
                                            const shift = schedule?.shift ?? emp.default_shift;

                                            return (
                                                <td
                                                    key={day}
                                                    onClick={() => openSingleSchedule(emp, day)}
                                                    className={`cursor-pointer border-l border-slate-100 p-1 text-center transition-colors hover:bg-indigo-50 dark:border-slate-700/50 dark:hover:bg-indigo-950/40`}
                                                    title={schedule?.note ? `Note: ${schedule.note}` : undefined}
                                                >
                                                    {isDayOff ? (
                                                        <span className="inline-block rounded bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                            OFF
                                                        </span>
                                                    ) : shift ? (
                                                        <span className={`inline-block rounded px-1.5 py-0.5 text-[10px] font-semibold ${isCustom ? 'bg-blue-100 text-blue-800 dark:bg-blue-950 dark:text-blue-200' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'}`}>
                                                            {shift.code}
                                                        </span>
                                                    ) : (
                                                        <span className="text-slate-300 dark:text-slate-600">—</span>
                                                    )}
                                                </td>
                                            );
                                        })}
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Edit Single Schedule Dialog */}
                {selectedCell && (
                    <CreateEditDialog
                        open={true}
                        minimized={false}
                        onMinimize={() => {}}
                        onClose={() => setSelectedCell(null)}
                        title={`Assign Shift: ${selectedCell.employee.name}`}
                        breadcrumb={`HR / Roster / ${selectedCell.dateStr}`}
                        entityLabel="Schedule"
                        draftLabel={selectedCell.dateStr}
                        storageKey="roster-single-editor"
                        status="Active"
                        formId="single-schedule-form"
                        processing={singleForm.processing}
                        saveLabel="Save Schedule"
                        errors={singleForm.errors}
                        onSubmit={submitSingleSchedule}
                    >
                        {() => (
                            <div className="space-y-4 p-5">
                                <div>
                                    <span className="text-xs font-semibold uppercase text-slate-500">Date</span>
                                    <div className="font-semibold text-slate-900 dark:text-slate-100">{selectedCell.dateStr}</div>
                                </div>

                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <input
                                        type="checkbox"
                                        checked={singleForm.data.is_day_off}
                                        onChange={(e) => singleForm.setData('is_day_off', e.target.checked)}
                                        className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                    Mark as Day Off (Rest Day)
                                </label>

                                {!singleForm.data.is_day_off && (
                                    <div>
                                        <label className="block text-sm font-medium">Shift</label>
                                        <select
                                            value={singleForm.data.employee_shift_id}
                                            onChange={(e) => singleForm.setData('employee_shift_id', e.target.value)}
                                            className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                        >
                                            <option value="">No shift assigned</option>
                                            {shifts.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.code} — {s.name} ({s.starts_at} - {s.ends_at})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}

                                <div>
                                    <label className="block text-sm font-medium">Note / Reason</label>
                                    <input
                                        type="text"
                                        value={singleForm.data.note}
                                        onChange={(e) => singleForm.setData('note', e.target.value)}
                                        placeholder="Optional shift note"
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                </div>
                            </div>
                        )}
                    </CreateEditDialog>
                )}

                {/* Batch Assignment Dialog */}
                {batchOpen && (
                    <CreateEditDialog
                        open={batchOpen}
                        minimized={false}
                        onMinimize={() => {}}
                        onClose={() => setBatchOpen(false)}
                        title="Batch Shift Assignment"
                        breadcrumb="HR / Roster / Batch Assignment"
                        entityLabel="Batch Schedule"
                        draftLabel="Batch Roster"
                        storageKey="roster-batch-editor"
                        status="Draft"
                        formId="batch-schedule-form"
                        processing={batchForm.processing}
                        saveLabel="Apply Batch Roster"
                        errors={batchForm.errors}
                        onSubmit={submitBatch}
                    >
                        {() => (
                            <div className="grid gap-4 p-5 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium">Branch</label>
                                    <select
                                        value={batchForm.data.branch_id}
                                        onChange={(e) => batchForm.setData('branch_id', e.target.value)}
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    >
                                        <option value="">All branches</option>
                                        {branches.map((b) => (
                                            <option key={b.id} value={b.id}>{b.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium">Department</label>
                                    <select
                                        value={batchForm.data.department_id}
                                        onChange={(e) => batchForm.setData('department_id', e.target.value)}
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    >
                                        <option value="">All departments</option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>{d.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="block text-sm font-medium">Start Date</label>
                                    <input
                                        type="date"
                                        value={batchForm.data.start_date}
                                        onChange={(e) => batchForm.setData('start_date', e.target.value)}
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium">End Date</label>
                                    <input
                                        type="date"
                                        value={batchForm.data.end_date}
                                        onChange={(e) => batchForm.setData('end_date', e.target.value)}
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="flex items-center gap-2 text-sm font-medium">
                                        <input
                                            type="checkbox"
                                            checked={batchForm.data.is_day_off}
                                            onChange={(e) => batchForm.setData('is_day_off', e.target.checked)}
                                            className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        />
                                        Assign as Day Off (Rest Day)
                                    </label>
                                </div>
                                {!batchForm.data.is_day_off && (
                                    <div className="sm:col-span-2">
                                        <label className="block text-sm font-medium">Shift to Assign</label>
                                        <select
                                            value={batchForm.data.employee_shift_id}
                                            onChange={(e) => batchForm.setData('employee_shift_id', e.target.value)}
                                            className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                        >
                                            {shifts.map((s) => (
                                                <option key={s.id} value={s.id}>
                                                    {s.code} — {s.name} ({s.starts_at} - {s.ends_at})
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                )}
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium">Apply on Days of Week</label>
                                    <div className="mt-2 flex flex-wrap gap-2">
                                        {[
                                            { d: 1, label: 'Mon' },
                                            { d: 2, label: 'Tue' },
                                            { d: 3, label: 'Wed' },
                                            { d: 4, label: 'Thu' },
                                            { d: 5, label: 'Fri' },
                                            { d: 6, label: 'Sat' },
                                            { d: 0, label: 'Sun' },
                                        ].map(({ d, label }) => {
                                            const checked = batchForm.data.days_of_week.includes(d);
                                            return (
                                                <button
                                                    key={d}
                                                    type="button"
                                                    onClick={() => toggleDayOfWeek(d)}
                                                    className={`rounded-md px-3 py-1.5 text-xs font-semibold transition-colors ${checked ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white text-slate-700 hover:bg-slate-50 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300'}`}
                                                >
                                                    {label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium">Note / Reason</label>
                                    <input
                                        type="text"
                                        value={batchForm.data.note}
                                        onChange={(e) => batchForm.setData('note', e.target.value)}
                                        placeholder="Optional assignment note"
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                </div>
                            </div>
                        )}
                    </CreateEditDialog>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
