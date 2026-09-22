import React, { FormEvent } from 'react';
import { useForm, usePage } from '@inertiajs/react';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';

interface EmployeeImportDialogProps {
    open: boolean;
    onClose: () => void;
}

export default function EmployeeImportDialog({
    open,
    onClose,
}: EmployeeImportDialogProps) {
    const page = usePage();
    const errors = page.props.errors as
        Record<string, string | string[]> | undefined;
    const importErrors = Array.isArray(errors?.import_errors)
        ? errors?.import_errors
        : typeof errors?.import_errors === 'string'
          ? [errors.import_errors]
          : [];

    const form = useForm<{
        file: File | null;
        auto_create_position: boolean;
        update_existing: boolean;
    }>({
        file: null,
        auto_create_position: true,
        update_existing: false,
    });

    if (!open) return null;

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(route('hr.employees.import'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onClose();
            },
        });
    };

    return (
        <CreateEditDialog
            open={open}
            minimized={false}
            onMinimize={() => {}}
            onClose={() => {
                form.reset();
                onClose();
            }}
            title="Import Employees (นำเข้าข้อมูลพนักงาน)"
            breadcrumb="HR / Employees / Import"
            entityLabel="Employee Import"
            draftLabel={form.data.file ? form.data.file.name : 'Select file'}
            storageKey="employee-import-dialog"
            status="Ready"
            formId="employee-import-form"
            processing={form.processing}
            saveLabel="Upload & Import"
            errors={form.errors}
            onSubmit={handleSubmit}
        >
            {() => (
                <div className="space-y-5 p-6 text-slate-800 dark:text-slate-100">
                    {/* Instructions & Template Download */}
                    <div className="rounded-lg border border-indigo-100 bg-indigo-50/70 p-4 dark:border-indigo-900/50 dark:bg-indigo-950/40">
                        <div className="flex items-start gap-3">
                            <div className="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-indigo-600 text-xs font-bold text-white dark:bg-indigo-500">
                                i
                            </div>
                            <div className="text-sm">
                                <p className="font-medium text-indigo-900 dark:text-indigo-200">
                                    นำเข้าข้อมูลพนักงานจากไฟล์ Excel หรือ CSV
                                </p>
                                <p className="mt-1 text-xs text-indigo-700 dark:text-indigo-300">
                                    รองรับไฟล์นามสกุล{' '}
                                    <code className="rounded bg-indigo-100 px-1 py-0.5 font-mono dark:bg-indigo-900">
                                        .csv
                                    </code>
                                    ,{' '}
                                    <code className="rounded bg-indigo-100 px-1 py-0.5 font-mono dark:bg-indigo-900">
                                        .xlsx
                                    </code>
                                    ,{' '}
                                    <code className="rounded bg-indigo-100 px-1 py-0.5 font-mono dark:bg-indigo-900">
                                        .xls
                                    </code>{' '}
                                    คอลัมน์ที่จำเป็นต้องมี:{' '}
                                    <span className="font-semibold">
                                        employee_code
                                    </span>{' '}
                                    (รหัสพนักงาน) และ{' '}
                                    <span className="font-semibold">
                                        first_name
                                    </span>{' '}
                                    (ชื่อ)
                                </p>
                                <div className="mt-3">
                                    <a
                                        href={route('hr.employees.template')}
                                        download="employee_import_template.csv"
                                        className="inline-flex items-center gap-1.5 rounded-md border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-sm transition hover:bg-indigo-50 dark:border-indigo-800 dark:bg-slate-800 dark:text-indigo-300 dark:hover:bg-slate-700"
                                    >
                                        <svg
                                            className="h-4 w-4"
                                            fill="none"
                                            viewBox="0 0 24 24"
                                            stroke="currentColor"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                            />
                                        </svg>
                                        ดาวน์โหลดไฟล์แม่แบบ (Download CSV
                                        Template)
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* File Upload Zone */}
                    <div>
                        <label className="block text-sm font-medium text-slate-700 dark:text-slate-300">
                            เลือกไฟล์ข้อมูลพนักงาน{' '}
                            <span className="text-rose-500">*</span>
                        </label>
                        <div className="mt-1 flex justify-center rounded-lg border-2 border-dashed border-slate-300 px-6 py-6 dark:border-slate-700">
                            <div className="text-center">
                                <svg
                                    className="mx-auto h-10 w-10 text-slate-400 dark:text-slate-500"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={1.5}
                                        d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"
                                    />
                                </svg>
                                <div className="mt-2 flex text-sm text-slate-600 dark:text-slate-400">
                                    <label
                                        htmlFor="employee-file-upload"
                                        className="relative cursor-pointer rounded-md font-semibold text-indigo-600 focus-within:outline-none hover:text-indigo-500 dark:text-indigo-400"
                                    >
                                        <span>อัปโหลดไฟล์ (Upload file)</span>
                                        <input
                                            id="employee-file-upload"
                                            name="file"
                                            type="file"
                                            accept=".csv,.tsv,.txt,.xlsx,.xls,.xml"
                                            className="sr-only"
                                            onChange={(e) =>
                                                form.setData(
                                                    'file',
                                                    e.target.files?.[0] ?? null,
                                                )
                                            }
                                        />
                                    </label>
                                    <p className="pl-1">
                                        หรือลากไฟล์มาวางที่นี่
                                    </p>
                                </div>
                                <p className="text-xs text-slate-500 dark:text-slate-400">
                                    CSV, TSV, XLSX, XLS สูงสุด 20MB
                                </p>
                                {form.data.file && (
                                    <div className="mt-3 inline-flex items-center gap-2 rounded-full bg-emerald-50 px-3 py-1 text-xs font-medium text-emerald-700 ring-1 ring-inset ring-emerald-600/20 dark:bg-emerald-950/50 dark:text-emerald-300">
                                        <span>
                                            ไฟล์ที่เลือก: {form.data.file.name}
                                        </span>
                                        <span className="text-emerald-500">
                                            (
                                            {(
                                                form.data.file.size / 1024
                                            ).toFixed(1)}{' '}
                                            KB)
                                        </span>
                                    </div>
                                )}
                            </div>
                        </div>
                        {form.errors.file && (
                            <p className="mt-1 text-xs text-rose-600 dark:text-rose-400">
                                {form.errors.file}
                            </p>
                        )}
                    </div>

                    {/* Import Options */}
                    <div className="space-y-3 rounded-lg border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700/60 dark:bg-slate-800/40">
                        <h4 className="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            ตัวเลือกการนำเข้า (Import Options)
                        </h4>
                        <div className="space-y-2">
                            <label className="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    checked={form.data.auto_create_position}
                                    onChange={(e) =>
                                        form.setData(
                                            'auto_create_position',
                                            e.target.checked,
                                        )
                                    }
                                    className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                                <div>
                                    <span className="font-medium">
                                        สร้างตำแหน่งงานใหม่อัตโนมัติ
                                        (Auto-create missing positions)
                                    </span>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        หากพบตำแหน่งที่ยังไม่มีใน Position
                                        Master ระบบจะบันทึกเพิ่มให้ทันที
                                    </p>
                                </div>
                            </label>

                            <label className="flex items-start gap-3 text-sm text-slate-700 dark:text-slate-300">
                                <input
                                    type="checkbox"
                                    checked={form.data.update_existing}
                                    onChange={(e) =>
                                        form.setData(
                                            'update_existing',
                                            e.target.checked,
                                        )
                                    }
                                    className="mt-0.5 h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500 dark:border-slate-600 dark:bg-slate-700"
                                />
                                <div>
                                    <span className="font-medium">
                                        อัปเดตข้อมูลพนักงานเดิมหากพบรหัสซ้ำ
                                        (Update existing employees)
                                    </span>
                                    <p className="text-xs text-slate-500 dark:text-slate-400">
                                        หากไม่เลือก
                                        ระบบจะข้ามรายการที่มีรหัสพนักงานซ้ำและรายงานเป็นข้อผิดพลาด
                                    </p>
                                </div>
                            </label>
                        </div>
                    </div>

                    {/* Import Errors if any */}
                    {importErrors.length > 0 && (
                        <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 dark:border-rose-900/50 dark:bg-rose-950/40">
                            <h4 className="text-sm font-semibold text-rose-800 dark:text-rose-300">
                                พบข้อผิดพลาดระหว่างนำเข้า ({importErrors.length}{' '}
                                รายการ):
                            </h4>
                            <ul className="mt-2 max-h-36 list-disc space-y-1 overflow-y-auto pl-5 text-xs text-rose-700 dark:text-rose-400">
                                {importErrors.map((err, idx) => (
                                    <li key={idx}>{err}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </CreateEditDialog>
    );
}
