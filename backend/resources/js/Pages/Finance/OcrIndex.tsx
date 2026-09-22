import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type ExtractionItem = {
    id: string;
    status: string;
    document_type: string;
    confidence_score: number;
    math_mismatch: boolean;
    tax_id_valid: boolean | null;
    target_type: string | null;
    target_id: string | null;
    created_at: string;
    stored_file?: {
        id: string;
        file_name: string;
        mime_type: string;
        size_bytes: number;
    };
    creator?: {
        id: string;
        name: string;
    };
};

export default function OcrIndex({
    extractions,
    filters,
    statuses,
    canUpload,
}: {
    extractions: {
        data: ExtractionItem[];
        current_page: number;
        last_page: number;
        total: number;
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { status?: string; search?: string };
    statuses: string[];
    canUpload: boolean;
    canConvert: boolean;
}) {
    const [uploadModalOpen, setUploadModalOpen] = useState(false);

    const uploadForm = useForm<{ document: File | null }>({
        document: null,
    });

    const handleUploadSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        uploadForm.post(route('ocr.upload'), {
            forceFormData: true,
            onSuccess: () => {
                setUploadModalOpen(false);
                uploadForm.reset();
            },
        });
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'converted':
                return 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300';
            case 'extracted':
                return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300';
            case 'processing':
                return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 animate-pulse';
            case 'rejected':
                return 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300';
            case 'failed':
                return 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300';
            default:
                return 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-400';
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h2 className="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100">
                            AI Document OCR Ingestion
                        </h2>
                        <p className="text-xs text-gray-500 dark:text-gray-400">
                            Scan receipts, full tax invoices, and bank transfer
                            slips into drafted expenses.
                        </p>
                    </div>

                    <div className="flex items-center gap-2">
                        <Link
                            href={route('settings.ai-configuration.index')}
                            className="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                        >
                            ⚙️ AI Settings
                        </Link>
                        {canUpload && (
                            <button
                                type="button"
                                onClick={() => setUploadModalOpen(true)}
                                className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700"
                            >
                                📤 Upload Document
                            </button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="AI Document OCR" />

            <div className="mx-auto max-w-7xl space-y-6 p-6">
                {/* Status and Search Filters */}
                <div className="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <div className="flex flex-wrap items-center gap-2">
                        <Link
                            href={route('ocr.index')}
                            className={`rounded-lg px-3 py-1.5 text-xs font-medium ${
                                !filters.status
                                    ? 'bg-indigo-600 text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300'
                            }`}
                        >
                            All Documents
                        </Link>
                        {statuses.map((st) => (
                            <Link
                                key={st}
                                href={route('ocr.index', { status: st })}
                                className={`rounded-lg px-3 py-1.5 text-xs font-medium capitalize ${
                                    filters.status === st
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300'
                                }`}
                            >
                                {st}
                            </Link>
                        ))}
                    </div>

                    <div className="text-xs text-gray-500 dark:text-gray-400">
                        Total {extractions.total} documents found
                    </div>
                </div>

                {/* Table of Ingested Documents */}
                <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
                    <table className="min-w-full divide-y divide-gray-200 text-left text-xs dark:divide-gray-800">
                        <thead className="bg-gray-50 text-gray-500 dark:bg-gray-800/60 dark:text-gray-400">
                            <tr>
                                <th className="px-4 py-3 font-semibold">
                                    Document / File
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Type
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    AI Confidence
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Math & Tax Validation
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Status
                                </th>
                                <th className="px-4 py-3 font-semibold">
                                    Uploaded By / Date
                                </th>
                                <th className="px-4 py-3 text-right font-semibold">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-gray-200 dark:divide-gray-800">
                            {extractions.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="py-12 text-center text-sm text-gray-500 dark:text-gray-400"
                                    >
                                        No scanned documents found. Click{' '}
                                        <strong>"Upload Document"</strong> to
                                        start.
                                    </td>
                                </tr>
                            ) : (
                                extractions.data.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="hover:bg-gray-50 dark:hover:bg-gray-800/40"
                                    >
                                        <td className="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            <div className="flex items-center gap-2">
                                                <span className="text-lg">
                                                    📄
                                                </span>
                                                <div>
                                                    <div className="max-w-xs truncate font-semibold">
                                                        {item.stored_file
                                                            ?.file_name ??
                                                            'Untitled document'}
                                                    </div>
                                                    <span className="text-[10px] text-gray-400 font-mono">
                                                        ID:{' '}
                                                        {item.id.slice(0, 8)}...
                                                    </span>
                                                </div>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 capitalize text-gray-700 dark:text-gray-300">
                                            {item.document_type?.replace(
                                                '_',
                                                ' ',
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-1.5">
                                                <div className="h-1.5 w-16 overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                                    <div
                                                        className={`h-full ${
                                                            item.confidence_score >=
                                                            0.9
                                                                ? 'bg-emerald-500'
                                                                : item.confidence_score >=
                                                                    0.7
                                                                  ? 'bg-amber-500'
                                                                  : 'bg-rose-500'
                                                        }`}
                                                        style={{
                                                            width: `${Math.round(
                                                                item.confidence_score *
                                                                    100,
                                                            )}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="font-mono text-[11px] font-semibold">
                                                    {Math.round(
                                                        item.confidence_score *
                                                            100,
                                                    )}
                                                    %
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex flex-col gap-0.5">
                                                {item.math_mismatch ? (
                                                    <span className="inline-flex w-fit items-center rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-medium text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">
                                                        ⚠️ Math Mismatch
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex w-fit items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300">
                                                        ✓ Math OK
                                                    </span>
                                                )}
                                                {item.tax_id_valid === true && (
                                                    <span className="text-[10px] text-emerald-600 dark:text-emerald-400">
                                                        ✓ 13-digit Tax ID valid
                                                    </span>
                                                )}
                                                {item.tax_id_valid ===
                                                    false && (
                                                    <span className="text-[10px] text-amber-600 dark:text-amber-400">
                                                        ⚠️ Checksum invalid
                                                    </span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold capitalize ${getStatusBadge(
                                                    item.status,
                                                )}`}
                                            >
                                                {item.status}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3 text-gray-500 dark:text-gray-400">
                                            <div>
                                                {item.creator?.name ?? 'System'}
                                            </div>
                                            <div className="text-[10px]">
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleString('th-TH')}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <Link
                                                href={route(
                                                    'ocr.show',
                                                    item.id,
                                                )}
                                                className="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-gray-700"
                                            >
                                                🔍 Review
                                            </Link>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Upload Modal */}
            {uploadModalOpen && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
                    <div className="w-full max-w-md rounded-xl bg-white p-6 shadow-xl dark:bg-gray-900">
                        <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                            <h3 className="text-base font-bold text-gray-800 dark:text-gray-100">
                                Upload Document for AI OCR
                            </h3>
                            <button
                                type="button"
                                onClick={() => setUploadModalOpen(false)}
                                className="text-gray-400 hover:text-gray-600"
                            >
                                ✕
                            </button>
                        </div>

                        <form
                            onSubmit={handleUploadSubmit}
                            className="mt-4 space-y-4"
                        >
                            <div>
                                <label className="block text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    Select Image or PDF Receipt (Max 10MB)
                                </label>
                                <input
                                    type="file"
                                    accept="image/jpeg,image/png,image/webp,application/pdf"
                                    onChange={(e) =>
                                        uploadForm.setData(
                                            'document',
                                            e.target.files?.[0] || null,
                                        )
                                    }
                                    className="mt-2 block w-full text-xs text-gray-500 file:mr-4 file:rounded-lg file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-950 dark:file:text-indigo-300"
                                    required
                                />
                                {uploadForm.errors.document && (
                                    <p className="mt-1 text-xs text-rose-500">
                                        {uploadForm.errors.document}
                                    </p>
                                )}
                            </div>

                            <div className="rounded-lg bg-gray-50 p-3 text-[11px] text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                                ℹ️ Supported formats: JPG, PNG, WEBP, and PDF.
                                The file will be stored in your private
                                organization vault and analyzed with your
                                configured AI model.
                            </div>

                            <div className="flex justify-end gap-2 pt-2">
                                <button
                                    type="button"
                                    onClick={() => setUploadModalOpen(false)}
                                    className="rounded-lg border px-3 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={
                                        uploadForm.processing ||
                                        !uploadForm.data.document
                                    }
                                    className="inline-flex items-center gap-1.5 rounded-lg bg-indigo-600 px-4 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:opacity-50"
                                >
                                    {uploadForm.processing ? (
                                        <>
                                            <svg
                                                className="h-3.5 w-3.5 animate-spin"
                                                viewBox="0 0 24 24"
                                            >
                                                <circle
                                                    className="opacity-25"
                                                    cx="12"
                                                    cy="12"
                                                    r="10"
                                                    stroke="currentColor"
                                                    strokeWidth="4"
                                                    fill="none"
                                                />
                                                <path
                                                    className="opacity-75"
                                                    fill="currentColor"
                                                    d="M4 12a8 8 0 018-8v8H4z"
                                                />
                                            </svg>
                                            Analyzing Document...
                                        </>
                                    ) : (
                                        'Upload & Scan'
                                    )}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AuthenticatedLayout>
    );
}
