import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type AttendanceRecord = {
    id: string;
    work_date: string;
    clock_in_at?: string | null;
    clock_out_at?: string | null;
    late_minutes: number;
    early_leave_minutes: number;
    worked_minutes: number;
    ot_normal_minutes: number;
    ot_holiday_minutes: number;
    ot_special_minutes?: number;
    is_reconciled: boolean;
    is_absent: boolean;
    correction_reason?: string | null;
    employee?: {
        id: string;
        employee_code: string;
        first_name: string;
        last_name?: string | null;
        department?: { id: string; name: string } | null;
    } | null;
    shift?: { id: string; code: string; name: string } | null;
    correctedByUser?: { id: string; name: string } | null;
};

type RawLog = {
    id: string;
    employee_code: string;
    punch_time: string;
    punch_type: string;
    device_id?: string | null;
    status: string;
};

type Ref = { id: string; name: string };

type Props = {
    attendances: AttendanceRecord[];
    recentLogs: RawLog[];
    departments: Ref[];
    filters: {
        date: string;
        department_id?: string | null;
        status: string;
    };
    canManage: boolean;
};

export default function AttendanceImport({
    attendances,
    recentLogs,
    departments,
    filters,
    canManage,
}: Props) {
    const [importOpen, setImportOpen] = useState(false);
    const [reconcileOpen, setReconcileOpen] = useState(false);
    const [correcting, setCorrecting] = useState<AttendanceRecord | null>(null);

    // Filter changes
    const updateFilter = (key: string, value: string) => {
        router.get(
            route('hr.attendance-import.index'),
            { ...filters, [key]: value || undefined },
            { preserveState: true, replace: true },
        );
    };

    // Import form
    const importForm = useForm<{ file: File | null }>({
        file: null,
    });

    const submitImport = (e: FormEvent) => {
        e.preventDefault();
        importForm.post(route('hr.attendance-import.store'), {
            preserveScroll: true,
            onSuccess: () => {
                importForm.reset();
                setImportOpen(false);
            },
        });
    };

    // Reconcile form
    const reconcileForm = useForm({
        start_date: filters.date,
        end_date: filters.date,
        department_id: filters.department_id ?? '',
    });

    const submitReconcile = (e: FormEvent) => {
        e.preventDefault();
        reconcileForm.post(route('hr.attendance-import.reconcile'), {
            preserveScroll: true,
            onSuccess: () => setReconcileOpen(false),
        });
    };

    // Correction form
    const correctionForm = useForm({
        clock_in_at: '',
        clock_out_at: '',
        is_absent: false,
        correction_reason: '',
    });

    const openCorrection = (record: AttendanceRecord) => {
        const formatDateTimeLocal = (dateStr?: string | null) => {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            const pad = (n: number) => String(n).padStart(2, '0');
            return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
        };

        correctionForm.setData({
            clock_in_at: formatDateTimeLocal(record.clock_in_at),
            clock_out_at: formatDateTimeLocal(record.clock_out_at),
            is_absent: record.is_absent,
            correction_reason: record.correction_reason ?? '',
        });

        setCorrecting(record);
    };

    const submitCorrection = (e: FormEvent) => {
        e.preventDefault();
        if (!correcting) return;

        correctionForm.post(
            route('hr.attendance-import.correct', correcting.id),
            {
                preserveScroll: true,
                onSuccess: () => setCorrecting(null),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Attendance Import & Reconciliation" />

            <div className="space-y-6">
                <PageHeader
                    title="Attendance Import & Reconciliation"
                    description="Upload biometric time punch logs and reconcile actual work hours with schedules."
                    actions={
                        canManage ? (
                            <div className="flex items-center gap-2">
                                <SecondaryButton
                                    onClick={() =>
                                        window.open(
                                            route(
                                                'hr.attendance-import.template',
                                            ),
                                            '_blank',
                                        )
                                    }
                                >
                                    Download Template
                                </SecondaryButton>
                                <SecondaryButton
                                    onClick={() => setImportOpen(true)}
                                >
                                    Import CSV
                                </SecondaryButton>
                                <PrimaryButton
                                    onClick={() => setReconcileOpen(true)}
                                >
                                    Run Reconciliation
                                </PrimaryButton>
                            </div>
                        ) : undefined
                    }
                />

                {/* Filter & Status Tabs */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div className="flex flex-wrap items-center gap-3">
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                                Date
                            </label>
                            <input
                                type="date"
                                value={filters.date}
                                onChange={(e) =>
                                    updateFilter('date', e.target.value)
                                }
                                className="mt-1 rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300">
                                Department
                            </label>
                            <select
                                value={filters.department_id ?? ''}
                                onChange={(e) =>
                                    updateFilter(
                                        'department_id',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-900"
                            >
                                <option value="">All departments</option>
                                {departments.map((d) => (
                                    <option key={d.id} value={d.id}>
                                        {d.name}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Status filter tabs */}
                    <div className="flex flex-wrap gap-1 rounded-md bg-slate-100 p-1 dark:bg-slate-900">
                        {[
                            { key: 'all', label: 'All' },
                            { key: 'missing_punch', label: 'Missing Punch' },
                            { key: 'late', label: 'Late' },
                            { key: 'ot', label: 'OT' },
                            { key: 'absent', label: 'Absent' },
                            { key: 'reconciled', label: 'Reconciled' },
                        ].map(({ key, label }) => (
                            <button
                                key={key}
                                type="button"
                                onClick={() => updateFilter('status', key)}
                                className={`rounded px-3 py-1 text-xs font-medium transition-colors ${filters.status === key ? 'bg-white font-semibold text-slate-900 shadow-sm dark:bg-slate-800 dark:text-slate-100' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'}`}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                {/* Attendance Reconciled Records Table */}
                <div className="rounded-lg border border-slate-200 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <div className="border-b border-slate-200 px-4 py-3 font-semibold text-slate-800 dark:border-slate-700 dark:text-slate-200">
                        Attendance Records for {filters.date}
                    </div>
                    <DataTable
                        columns={[
                            {
                                header: 'Employee',
                                accessor: (x: AttendanceRecord) => (
                                    <div>
                                        <div className="font-semibold text-slate-900 dark:text-slate-100">
                                            {x.employee
                                                ? `${x.employee.first_name} ${x.employee.last_name ?? ''}`
                                                : 'Unknown'}
                                        </div>
                                        <div className="text-xs text-slate-500">
                                            {x.employee?.employee_code} •{' '}
                                            {x.employee?.department?.name ??
                                                'No dept'}
                                        </div>
                                    </div>
                                ),
                            },
                            {
                                header: 'Shift',
                                accessor: (x: AttendanceRecord) =>
                                    x.shift
                                        ? `${x.shift.code} (${x.shift.name})`
                                        : '—',
                            },
                            {
                                header: 'Clock In',
                                accessor: (x: AttendanceRecord) =>
                                    x.clock_in_at ? (
                                        new Date(
                                            x.clock_in_at,
                                        ).toLocaleTimeString([], {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    ) : x.is_absent ? (
                                        <span className="font-semibold text-rose-600">
                                            Absent
                                        </span>
                                    ) : (
                                        <span className="text-amber-500">
                                            Missing
                                        </span>
                                    ),
                            },
                            {
                                header: 'Clock Out',
                                accessor: (x: AttendanceRecord) =>
                                    x.clock_out_at ? (
                                        new Date(
                                            x.clock_out_at,
                                        ).toLocaleTimeString([], {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })
                                    ) : x.is_absent ? (
                                        '—'
                                    ) : (
                                        <span className="text-amber-500">
                                            Missing
                                        </span>
                                    ),
                            },
                            {
                                header: 'Late / Early',
                                accessor: (x: AttendanceRecord) => (
                                    <div className="text-xs">
                                        {x.late_minutes > 0 && (
                                            <span className="text-rose-600 font-medium">
                                                Late: {x.late_minutes}m{' '}
                                            </span>
                                        )}
                                        {x.early_leave_minutes > 0 && (
                                            <span className="text-amber-600 font-medium">
                                                Early: {x.early_leave_minutes}m
                                            </span>
                                        )}
                                        {x.late_minutes === 0 &&
                                            x.early_leave_minutes === 0 && (
                                                <span className="text-slate-400">
                                                    On time
                                                </span>
                                            )}
                                    </div>
                                ),
                            },
                            {
                                header: 'Worked Hours',
                                accessor: (x: AttendanceRecord) =>
                                    `${(x.worked_minutes / 60).toFixed(1)} hrs`,
                            },
                            {
                                header: 'OT Hours',
                                accessor: (x: AttendanceRecord) => {
                                    const totalOtMins =
                                        x.ot_normal_minutes +
                                        x.ot_holiday_minutes +
                                        (x.ot_special_minutes ?? 0);
                                    if (totalOtMins === 0) return '—';
                                    return (
                                        <span className="font-semibold text-indigo-600 dark:text-indigo-400">
                                            {(totalOtMins / 60).toFixed(1)} hrs
                                            {x.ot_holiday_minutes > 0 && (
                                                <span className="text-[10px] text-purple-600">
                                                    {' '}
                                                    (Hol)
                                                </span>
                                            )}
                                            {(x.ot_special_minutes ?? 0) >
                                                0 && (
                                                <span className="text-[10px] text-rose-600">
                                                    {' '}
                                                    (Hol 3x)
                                                </span>
                                            )}
                                        </span>
                                    );
                                },
                            },
                            {
                                header: 'Status',
                                accessor: (x: AttendanceRecord) => {
                                    if (x.is_absent) {
                                        return (
                                            <span className="rounded bg-rose-100 px-2 py-0.5 text-xs font-bold text-rose-700 dark:bg-rose-950 dark:text-rose-300">
                                                Absent
                                            </span>
                                        );
                                    }
                                    if (!x.clock_out_at) {
                                        return (
                                            <span className="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                Missing Punch
                                            </span>
                                        );
                                    }
                                    if (x.late_minutes > 0) {
                                        return (
                                            <span className="rounded bg-yellow-100 px-2 py-0.5 text-xs font-semibold text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300">
                                                Late
                                            </span>
                                        );
                                    }
                                    return (
                                        <span className="rounded bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                                            Normal
                                        </span>
                                    );
                                },
                            },
                            ...(canManage
                                ? [
                                      {
                                          header: 'Actions',
                                          accessor: (x: AttendanceRecord) => (
                                              <button
                                                  type="button"
                                                  onClick={() =>
                                                      openCorrection(x)
                                                  }
                                                  className="text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                                              >
                                                  Correct
                                              </button>
                                          ),
                                      },
                                  ]
                                : []),
                        ]}
                        data={attendances}
                        keyExtractor={(x) => x.id}
                        emptyMessage="No attendance records found for this date. Run reconciliation or import logs."
                    />
                </div>

                {/* Recent Raw Logs Accordion/Box */}
                <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-800">
                    <h3 className="mb-3 text-sm font-semibold text-slate-800 dark:text-slate-200">
                        Recent Raw Time Punch Logs (Last 50)
                    </h3>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left text-xs text-slate-600 dark:text-slate-400">
                            <thead className="border-b border-slate-200 bg-slate-50 text-[11px] uppercase font-semibold text-slate-500 dark:border-slate-700 dark:bg-slate-900/40">
                                <tr>
                                    <th className="p-2">Punch Time</th>
                                    <th className="p-2">Employee Code</th>
                                    <th className="p-2">Type</th>
                                    <th className="p-2">Device ID</th>
                                    <th className="p-2">Status</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 dark:divide-slate-700/50">
                                {recentLogs.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="p-4 text-center text-slate-400"
                                        >
                                            No raw punch logs imported yet.
                                        </td>
                                    </tr>
                                ) : (
                                    recentLogs.map((log) => (
                                        <tr key={log.id}>
                                            <td className="p-2 font-mono">
                                                {log.punch_time}
                                            </td>
                                            <td className="p-2 font-semibold text-slate-900 dark:text-slate-100">
                                                {log.employee_code}
                                            </td>
                                            <td className="p-2 uppercase font-medium">
                                                {log.punch_type}
                                            </td>
                                            <td className="p-2">
                                                {log.device_id ?? '—'}
                                            </td>
                                            <td className="p-2">
                                                <span
                                                    className={`rounded px-1.5 py-0.5 text-[10px] font-semibold ${log.status === 'reconciled' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-700 dark:text-slate-300'}`}
                                                >
                                                    {log.status}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Import CSV / Excel Dialog */}
                {importOpen && (
                    <CreateEditDialog
                        open={importOpen}
                        minimized={false}
                        onMinimize={() => {}}
                        onClose={() => setImportOpen(false)}
                        title="Import Biometric Punch Logs (CSV / Excel)"
                        breadcrumb="HR / Attendance / Import"
                        entityLabel="Punch File"
                        draftLabel="File Upload"
                        storageKey="attendance-import-dialog"
                        status="Draft"
                        formId="import-punch-form"
                        processing={importForm.processing}
                        saveLabel="Upload & Import"
                        errors={importForm.errors}
                        onSubmit={submitImport}
                    >
                        {() => (
                            <div className="space-y-4 p-5">
                                <p className="text-sm text-slate-600 dark:text-slate-300">
                                    Upload a CSV or Excel (.xlsx, .xls, .xml)
                                    file containing employee punch times from
                                    your biometric device. Required columns:{' '}
                                    <code className="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-slate-800">
                                        employee_code
                                    </code>
                                    ,{' '}
                                    <code className="rounded bg-slate-100 px-1 py-0.5 text-xs dark:bg-slate-800">
                                        punch_time
                                    </code>
                                    .
                                </p>
                                <div>
                                    <input
                                        type="file"
                                        accept=".csv,.tsv,.txt,.xlsx,.xls,.xml"
                                        onChange={(e) =>
                                            importForm.setData(
                                                'file',
                                                e.target.files?.[0] ?? null,
                                            )
                                        }
                                        className="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                                    />
                                    {importForm.errors.file && (
                                        <span className="text-xs text-rose-600">
                                            {importForm.errors.file}
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </CreateEditDialog>
                )}

                {/* Reconciliation Trigger Dialog */}
                {reconcileOpen && (
                    <CreateEditDialog
                        open={reconcileOpen}
                        minimized={false}
                        onMinimize={() => {}}
                        onClose={() => setReconcileOpen(false)}
                        title="Run Attendance Reconciliation"
                        breadcrumb="HR / Attendance / Reconciliation"
                        entityLabel="Reconciliation"
                        draftLabel="Batch Reconcile"
                        storageKey="reconciliation-dialog"
                        status="Ready"
                        formId="reconcile-form"
                        processing={reconcileForm.processing}
                        saveLabel="Process Reconciliation"
                        errors={reconcileForm.errors}
                        onSubmit={submitReconcile}
                    >
                        {() => (
                            <div className="grid gap-4 p-5 sm:grid-cols-2">
                                <div>
                                    <label className="block text-sm font-medium">
                                        Start Date
                                    </label>
                                    <input
                                        type="date"
                                        value={reconcileForm.data.start_date}
                                        onChange={(e) =>
                                            reconcileForm.setData(
                                                'start_date',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium">
                                        End Date
                                    </label>
                                    <input
                                        type="date"
                                        value={reconcileForm.data.end_date}
                                        onChange={(e) =>
                                            reconcileForm.setData(
                                                'end_date',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    />
                                </div>
                                <div className="sm:col-span-2">
                                    <label className="block text-sm font-medium">
                                        Department (Optional)
                                    </label>
                                    <select
                                        value={reconcileForm.data.department_id}
                                        onChange={(e) =>
                                            reconcileForm.setData(
                                                'department_id',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                    >
                                        <option value="">
                                            All departments
                                        </option>
                                        {departments.map((d) => (
                                            <option key={d.id} value={d.id}>
                                                {d.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                        )}
                    </CreateEditDialog>
                )}

                {/* Punch Correction Dialog */}
                {correcting && (
                    <CreateEditDialog
                        open={true}
                        minimized={false}
                        onMinimize={() => {}}
                        onClose={() => setCorrecting(null)}
                        title={`Correct Attendance: ${correcting.employee?.first_name ?? 'Employee'}`}
                        breadcrumb={`HR / Attendance / Correct / ${correcting.work_date}`}
                        entityLabel="Correction"
                        draftLabel={correcting.work_date}
                        storageKey="attendance-correction-dialog"
                        status="Edit"
                        formId="correction-form"
                        processing={correctionForm.processing}
                        saveLabel="Save Correction"
                        errors={correctionForm.errors}
                        onSubmit={submitCorrection}
                    >
                        {() => (
                            <div className="space-y-4 p-5">
                                <div>
                                    <span className="text-xs font-semibold uppercase text-slate-500">
                                        Date
                                    </span>
                                    <div className="font-semibold text-slate-900 dark:text-slate-100">
                                        {correcting.work_date}
                                    </div>
                                </div>

                                <label className="flex items-center gap-2 text-sm font-medium">
                                    <input
                                        type="checkbox"
                                        checked={correctionForm.data.is_absent}
                                        onChange={(e) =>
                                            correctionForm.setData(
                                                'is_absent',
                                                e.target.checked,
                                            )
                                        }
                                        className="rounded border-slate-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    />
                                    Mark as Absent
                                </label>

                                {!correctionForm.data.is_absent && (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <label className="block text-sm font-medium">
                                                Clock In Time
                                            </label>
                                            <input
                                                type="datetime-local"
                                                value={
                                                    correctionForm.data
                                                        .clock_in_at
                                                }
                                                onChange={(e) =>
                                                    correctionForm.setData(
                                                        'clock_in_at',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                            />
                                        </div>
                                        <div>
                                            <label className="block text-sm font-medium">
                                                Clock Out Time
                                            </label>
                                            <input
                                                type="datetime-local"
                                                value={
                                                    correctionForm.data
                                                        .clock_out_at
                                                }
                                                onChange={(e) =>
                                                    correctionForm.setData(
                                                        'clock_out_at',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1 w-full rounded-md border-slate-300 dark:border-slate-600 dark:bg-slate-800"
                                            />
                                        </div>
                                    </div>
                                )}

                                <div>
                                    <label className="block text-sm font-medium">
                                        Reason for Correction{' '}
                                        <span className="text-rose-500">*</span>
                                    </label>
                                    <textarea
                                        required
                                        value={
                                            correctionForm.data
                                                .correction_reason
                                        }
                                        onChange={(e) =>
                                            correctionForm.setData(
                                                'correction_reason',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. Employee forgot biometric badge at gate; verified with supervisor log."
                                        className="mt-1 w-full rounded-md border-slate-300 text-sm dark:border-slate-600 dark:bg-slate-800"
                                        rows={3}
                                    />
                                    {correctionForm.errors
                                        .correction_reason && (
                                        <span className="text-xs text-rose-600">
                                            {
                                                correctionForm.errors
                                                    .correction_reason
                                            }
                                        </span>
                                    )}
                                </div>
                            </div>
                        )}
                    </CreateEditDialog>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
