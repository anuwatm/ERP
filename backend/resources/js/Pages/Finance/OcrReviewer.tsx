import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

type FieldResult = {
    id: string;
    field_key: string;
    extracted_value: string | null;
    confidence: number;
    is_corrected_by_user: boolean;
};

type ExtractionDetail = {
    id: string;
    status: string;
    document_type: string;
    confidence_score: number;
    math_mismatch: boolean;
    tax_id_valid: boolean | null;
    target_type?: string | null;
    target_id?: string | null;
    error_message?: string | null;
    parsed_data_json: {
        vendor_name?: string;
        tax_id?: string;
        invoice_no?: string;
        document_date?: string;
        subtotal?: number;
        vat_rate?: number;
        vat_amount?: number;
        total_amount?: number;
        currency?: string;
        matched_supplier_id?: string;
        suggested_category?: string;
        line_items?: {
            description: string;
            quantity: number;
            unit_price: number;
            amount: number;
        }[];
    } | null;
    stored_file?: {
        id: string;
        file_name: string;
        mime_type: string;
        size_bytes: number;
    };
    field_results?: FieldResult[];
    created_at: string;
};

type SupplierOption = {
    id: string;
    name: string;
    tax_id?: string | null;
};

type ProjectOption = {
    id: string;
    project_code: string;
    name: string;
};

type PurchaseOrderOption = {
    id: string;
    po_no: string;
    supplier_id: string;
    total: number;
};

export default function OcrReviewer({
    extraction,
    suppliers,
    projects,
    purchaseOrders = [],
    categories,
    canConvert,
}: {
    extraction: ExtractionDetail;
    suppliers: SupplierOption[];
    projects: ProjectOption[];
    purchaseOrders?: PurchaseOrderOption[];
    categories: string[];
    canConvert: boolean;
}) {
    const parsed = extraction.parsed_data_json || {};

    const [zoom, setZoom] = useState(100);
    const [rotation, setRotation] = useState(0);
    const [targetType, setTargetType] = useState<'expense' | 'vendor_bill'>('expense');

    const initialTotal = parsed.total_amount ?? 0;
    const initialVat = parsed.vat_amount ?? 0;

    const form = useForm({
        title: parsed.vendor_name
            ? `Expense - ${parsed.vendor_name}`
            : 'Expense draft from OCR',
        category: parsed.suggested_category || 'office',
        amount: initialTotal,
        currency: parsed.currency || 'THB',
        expense_date:
            parsed.document_date || new Date().toISOString().slice(0, 10),
        tax_mode: initialVat > 0 ? 'include' : 'none',
        tax_invoice_no: parsed.invoice_no || '',
        tax_amount: initialVat,
        supplier_id: parsed.matched_supplier_id || '',
        purchase_order_id: '',
        project_id: '',
        note: `Scanned from AI OCR (${extraction.stored_file?.file_name ?? 'receipt'})`,
    });

    const isPdf = extraction.stored_file?.mime_type === 'application/pdf';
    const previewUrl = extraction.stored_file
        ? route('files.preview', extraction.stored_file.id)
        : '';

    const candidatePOs = (purchaseOrders || []).filter(
        (po) => !form.data.supplier_id || po.supplier_id === form.data.supplier_id,
    );

    const handleConvert = (e: React.FormEvent) => {
        e.preventDefault();
        if (targetType === 'vendor_bill') {
            form.post(route('ocr.convert-vendor-bill', extraction.id));
        } else {
            form.post(route('ocr.convert-expense', extraction.id));
        }
    };

    const handleReject = () => {
        if (
            confirm(
                'Are you sure you want to reject and archive this document?',
            )
        ) {
            form.post(route('ocr.reject', extraction.id));
        }
    };

    // Calculate live math check on form inputs
    const numAmount = Number(form.data.amount) || 0;
    const numVat = Number(form.data.tax_amount) || 0;
    const computedSubtotal = Math.max(0, numAmount - numVat);

    const getConfidenceBadge = (confidence?: number) => {
        const score = confidence ?? 0.85;
        if (score >= 0.9) {
            return (
                <span className="rounded bg-emerald-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    {Math.round(score * 100)}% Confident
                </span>
            );
        }
        if (score >= 0.7) {
            return (
                <span className="rounded bg-amber-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    {Math.round(score * 100)}% Moderate
                </span>
            );
        }
        return (
            <span className="rounded bg-rose-100 px-1.5 py-0.5 font-mono text-[10px] font-semibold text-rose-800 dark:bg-rose-950 dark:text-rose-300">
                {Math.round(score * 100)}% Check Needed
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <Link
                            href={route('ocr.index')}
                            className="rounded-lg border border-gray-200 bg-white p-1.5 text-gray-500 shadow-sm hover:bg-gray-50 dark:border-gray-800 dark:bg-gray-800 dark:text-gray-300"
                        >
                            ← Back
                        </Link>
                        <div>
                            <div className="flex items-center gap-2">
                                <h2 className="text-xl font-bold text-gray-800 dark:text-gray-100">
                                    Split-Screen Draft Reviewer
                                </h2>
                                <span className="rounded-full bg-indigo-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-indigo-800 dark:bg-indigo-950 dark:text-indigo-300">
                                    {extraction.document_type}
                                </span>
                            </div>
                            <p className="text-xs text-gray-500 dark:text-gray-400">
                                {extraction.stored_file?.file_name} • ID:{' '}
                                {extraction.id.slice(0, 8)}
                            </p>
                        </div>
                    </div>

                    <div className="flex items-center gap-2">
                        {extraction.stored_file && (
                            <a
                                href={route(
                                    'files.download',
                                    extraction.stored_file.id,
                                )}
                                className="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 shadow-sm hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200"
                            >
                                ⬇️ Download Original
                            </a>
                        )}
                    </div>
                </div>
            }
        >
            <Head
                title={`Review OCR - ${extraction.stored_file?.file_name ?? 'Document'}`}
            />

            <div className="mx-auto max-w-7xl p-4 lg:p-6">
                {/* Human-in-the-loop Guard Banner */}
                <div className="mb-4 rounded-xl border border-amber-200 bg-amber-50/80 p-3.5 text-xs text-amber-900 shadow-sm dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-200">
                    <div className="flex items-center gap-2">
                        <span className="text-base">🛡️</span>
                        <div>
                            <strong>Human-in-the-Loop Accounting Guard:</strong>{' '}
                            This document is in <em>Assisted Draft</em> state
                            and{' '}
                            <strong>
                                has NOT been posted to the General Ledger
                            </strong>
                            . Please verify the extracted numbers against the
                            document image on the left before converting.
                        </div>
                    </div>
                </div>

                {/* OCR Error Banner if Failed */}
                {(extraction.status === 'failed' || extraction.error_message) && (
                    <div className="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-900 shadow-sm dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-200">
                        <div className="flex items-start gap-2.5">
                            <span className="text-lg">⚠️</span>
                            <div>
                                <strong className="font-semibold text-rose-800 dark:text-rose-300">
                                    AI OCR Scan Failed / Notice:
                                </strong>
                                <p className="mt-1 font-mono text-[11px] leading-relaxed text-rose-700 dark:text-rose-300">
                                    {extraction.error_message || 'The AI model could not extract text from this document. Please verify your AI Configuration, model quota, or try uploading an image format (JPG/PNG).'}
                                </p>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-12">
                    {/* Left Panel: Document Viewer (6 cols) */}
                    <div className="flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-6">
                        {/* Viewer Toolbar */}
                        <div className="flex items-center justify-between border-b border-gray-100 p-3 text-xs dark:border-gray-800">
                            <span className="font-semibold text-gray-700 dark:text-gray-300">
                                📄 Original Document
                            </span>
                            <div className="flex items-center gap-1.5">
                                <button
                                    type="button"
                                    onClick={() =>
                                        setZoom((z) => Math.max(50, z - 20))
                                    }
                                    className="rounded border px-2 py-1 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                                    title="Zoom Out"
                                >
                                    -
                                </button>
                                <span className="font-mono text-[11px] w-12 text-center">
                                    {zoom}%
                                </span>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setZoom((z) => Math.min(250, z + 20))
                                    }
                                    className="rounded border px-2 py-1 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                                    title="Zoom In"
                                >
                                    +
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setRotation((r) => (r + 90) % 360)
                                    }
                                    className="rounded border px-2 py-1 hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                                    title="Rotate 90 degrees"
                                >
                                    ↻ Rotate
                                </button>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setZoom(100);
                                        setRotation(0);
                                    }}
                                    className="rounded border px-2 py-1 text-[10px] hover:bg-gray-100 dark:border-gray-700 dark:hover:bg-gray-800"
                                >
                                    Reset
                                </button>
                            </div>
                        </div>

                        {/* Document View Canvas */}
                        <div className="flex-1 overflow-auto bg-gray-100/70 p-4 dark:bg-gray-950 flex items-center justify-center min-h-[520px] max-h-[750px]">
                            {isPdf ? (
                                <iframe
                                    src={`${previewUrl}#toolbar=0`}
                                    className="h-full w-full rounded border border-gray-200 dark:border-gray-800"
                                    style={{
                                        minHeight: '600px',
                                        transform: `scale(${zoom / 100}) rotate(${rotation}deg)`,
                                        transformOrigin: 'center center',
                                    }}
                                />
                            ) : (
                                <img
                                    src={previewUrl}
                                    alt="Document receipt"
                                    className="rounded shadow-md transition-transform duration-150"
                                    style={{
                                        transform: `scale(${zoom / 100}) rotate(${rotation}deg)`,
                                        transformOrigin: 'center center',
                                        maxWidth: '100%',
                                    }}
                                />
                            )}
                        </div>
                    </div>

                    {/* Right Panel: Verified Draft Form (6 cols) */}
                    <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900 lg:col-span-6 space-y-5">
                        <div className="flex items-center justify-between border-b border-gray-100 pb-3 dark:border-gray-800">
                            <div>
                                <h3 className="text-sm font-bold text-gray-800 dark:text-gray-100">
                                    AI-Extracted Data & Verification
                                </h3>
                                <p className="text-[11px] text-gray-500">
                                    Verify fields and select accounting
                                    dimensions.
                                </p>
                            </div>
                            <div className="text-right">
                                <span className="text-[10px] text-gray-400 block">
                                    Overall AI Score
                                </span>
                                {getConfidenceBadge(
                                    extraction.confidence_score,
                                )}
                            </div>
                        </div>

                        {/* Mode Switcher: Expense vs Vendor Bill */}
                        <div className="flex rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                            <button
                                type="button"
                                onClick={() => {
                                    setTargetType('expense');
                                    if (form.data.title.startsWith('Vendor Bill:')) {
                                        form.setData('title', parsed.vendor_name ? `Expense - ${parsed.vendor_name}` : 'Expense draft from OCR');
                                    }
                                }}
                                className={`flex-1 rounded-md py-1.5 text-xs font-semibold transition ${
                                    targetType === 'expense'
                                        ? 'bg-white text-indigo-700 shadow-sm dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                }`}
                            >
                                General Expense
                            </button>
                            <button
                                type="button"
                                onClick={() => {
                                    setTargetType('vendor_bill');
                                    if (!form.data.title.startsWith('Vendor Bill:')) {
                                        form.setData('title', `Vendor Bill: ${parsed.invoice_no || parsed.vendor_name || 'Supplier'}`);
                                    }
                                }}
                                className={`flex-1 rounded-md py-1.5 text-xs font-semibold transition ${
                                    targetType === 'vendor_bill'
                                        ? 'bg-white text-indigo-700 shadow-sm dark:bg-gray-700 dark:text-white'
                                        : 'text-gray-500 hover:text-gray-700 dark:text-gray-400'
                                }`}
                            >
                                Vendor Bill (AP)
                            </button>
                        </div>

                        <form
                            onSubmit={handleConvert}
                            className="space-y-4 text-xs"
                        >
                            {/* Title */}
                            <div>
                                <div className="flex items-center justify-between mb-1">
                                    <label className="font-semibold text-gray-700 dark:text-gray-300">
                                        {targetType === 'vendor_bill' ? 'Bill Title / Description *' : 'Expense Title / Description *'}
                                    </label>
                                    {getConfidenceBadge(
                                        parsed.vendor_name ? 0.95 : 0.75,
                                    )}
                                </div>
                                <input
                                    type="text"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    required
                                />
                            </div>

                            {/* Supplier & Category */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="font-semibold text-gray-700 dark:text-gray-300">
                                            Supplier / Vendor {targetType === 'vendor_bill' ? '*' : ''}
                                        </label>
                                        {parsed.matched_supplier_id && (
                                            <span className="rounded bg-emerald-50 px-1 text-[10px] text-emerald-700 font-medium dark:bg-emerald-950 dark:text-emerald-300">
                                                ✓ Auto-matched
                                            </span>
                                        )}
                                    </div>
                                    <select
                                        value={form.data.supplier_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'supplier_id',
                                                e.target.value,
                                            )
                                        }
                                        required={targetType === 'vendor_bill'}
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="">
                                            {targetType === 'vendor_bill' ? '-- Select Supplier (Required for AP Bill) --' : '-- No Supplier (General Merchant) --'}
                                        </option>
                                        {suppliers.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}{' '}
                                                {s.tax_id
                                                    ? `(${s.tax_id})`
                                                    : ''}
                                            </option>
                                        ))}
                                    </select>
                                    {parsed.vendor_name && (
                                        <p className="mt-1 text-[10px] text-gray-400 truncate">
                                            OCR Name: {parsed.vendor_name}
                                        </p>
                                    )}
                                </div>

                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="font-semibold text-gray-700 dark:text-gray-300">
                                            Expense Category *
                                        </label>
                                        {parsed.suggested_category && (
                                            <span className="rounded bg-blue-50 px-1 text-[10px] text-blue-700 font-medium dark:bg-blue-950 dark:text-blue-300">
                                                Suggested
                                            </span>
                                        )}
                                    </div>
                                    <select
                                        value={form.data.category}
                                        onChange={(e) =>
                                            form.setData(
                                                'category',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm capitalize focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        required
                                    >
                                        {categories.map((c) => (
                                            <option key={c} value={c}>
                                                {c}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            {/* Tax ID & Invoice No */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="font-semibold text-gray-700 dark:text-gray-300">
                                            Thai Tax ID (13 Digits)
                                        </label>
                                        {extraction.tax_id_valid === true && (
                                            <span className="text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                                ✓ Valid Modulo 11
                                            </span>
                                        )}
                                        {extraction.tax_id_valid === false && (
                                            <span className="text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                                ⚠️ Checksum Invalid
                                            </span>
                                        )}
                                    </div>
                                    <input
                                        type="text"
                                        value={parsed.tax_id || ''}
                                        readOnly
                                        className="w-full rounded-lg border-gray-200 bg-gray-50 font-mono text-xs text-gray-600 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300"
                                        placeholder="Not detected or not applicable"
                                    />
                                </div>

                                <div>
                                    <div className="flex items-center justify-between mb-1">
                                        <label className="font-semibold text-gray-700 dark:text-gray-300">
                                            Tax Invoice / Receipt No.
                                        </label>
                                        {getConfidenceBadge(
                                            parsed.invoice_no ? 0.92 : 0.7,
                                        )}
                                    </div>
                                    <input
                                        type="text"
                                        value={form.data.tax_invoice_no}
                                        onChange={(e) =>
                                            form.setData(
                                                'tax_invoice_no',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="e.g. INV-2026-001"
                                        className="w-full rounded-lg border-gray-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            {/* Date & Tax Mode */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Expense Date *
                                    </label>
                                    <input
                                        type="date"
                                        value={form.data.expense_date}
                                        onChange={(e) =>
                                            form.setData(
                                                'expense_date',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        required
                                    />
                                </div>

                                <div>
                                    <label className="block font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Tax Mode
                                    </label>
                                    <select
                                        value={form.data.tax_mode}
                                        onChange={(e) =>
                                            form.setData(
                                                'tax_mode',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="none">
                                            No VAT (ไม่มีภาษี)
                                        </option>
                                        <option value="include">
                                            VAT Included 7% (รวมใน)
                                        </option>
                                        <option value="exclude">
                                            VAT Excluded 7% (แยกนอก)
                                        </option>
                                    </select>
                                </div>
                            </div>

                            {/* Financial Figures: Subtotal, VAT, Total */}
                            <div className="rounded-lg border border-gray-200 bg-gray-50/70 p-3.5 dark:border-gray-800 dark:bg-gray-800/40 space-y-3">
                                <div className="grid grid-cols-3 gap-3">
                                    <div>
                                        <span className="block text-[11px] text-gray-500 dark:text-gray-400">
                                            Subtotal (Before VAT)
                                        </span>
                                        <span className="font-mono text-sm font-semibold text-gray-800 dark:text-gray-200">
                                            ฿
                                            {computedSubtotal.toLocaleString(
                                                'en-US',
                                                {
                                                    minimumFractionDigits: 2,
                                                    maximumFractionDigits: 2,
                                                },
                                            )}
                                        </span>
                                    </div>

                                    <div>
                                        <label className="block text-[11px] font-medium text-gray-700 dark:text-gray-300">
                                            VAT Amount (7%)
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            value={form.data.tax_amount}
                                            onChange={(e) =>
                                                form.setData(
                                                    'tax_amount',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="mt-1 w-full rounded border-gray-300 font-mono text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                        />
                                    </div>

                                    <div>
                                        <label className="block text-[11px] font-bold text-gray-800 dark:text-gray-200">
                                            Grand Total (สุทธิ) *
                                        </label>
                                        <input
                                            type="number"
                                            step="0.01"
                                            value={form.data.amount}
                                            onChange={(e) =>
                                                form.setData(
                                                    'amount',
                                                    parseFloat(
                                                        e.target.value,
                                                    ) || 0,
                                                )
                                            }
                                            className="mt-1 w-full rounded border-gray-300 font-mono text-xs font-bold text-indigo-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-indigo-400"
                                            required
                                        />
                                    </div>
                                </div>

                                {/* Math Reconciliation Status */}
                                {extraction.math_mismatch ? (
                                    <div className="flex items-center gap-1.5 rounded border border-rose-200 bg-rose-50 px-2 py-1 text-[11px] text-rose-800 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300">
                                        <span>⚠️</span>
                                        <strong>Math Check:</strong> AI detected
                                        that Subtotal + VAT does not equal
                                        Total. Please review the numbers
                                        manually.
                                    </div>
                                ) : (
                                    <div className="flex items-center gap-1.5 rounded border border-emerald-200 bg-emerald-50 px-2 py-1 text-[11px] text-emerald-800 dark:border-emerald-900/50 dark:bg-emerald-950/40 dark:text-emerald-300">
                                        <span>✓</span>
                                        <strong>Math Check:</strong> Subtotal +
                                        VAT matches Total successfully.
                                    </div>
                                )}
                            </div>

                            {/* Candidate Open POs for Vendor Bill */}
                            {targetType === 'vendor_bill' && (
                                <div className="rounded-lg border border-indigo-100 bg-indigo-50/50 p-3 dark:border-indigo-900/50 dark:bg-indigo-950/20">
                                    <div className="flex items-center justify-between mb-1.5">
                                        <label className="font-semibold text-indigo-950 dark:text-indigo-200 text-xs">
                                            Link Open Purchase Order (Optional)
                                        </label>
                                        {candidatePOs.length > 0 ? (
                                            <span className="rounded bg-indigo-100 px-1.5 py-0.5 text-[10px] text-indigo-800 font-semibold dark:bg-indigo-900 dark:text-indigo-200">
                                                {candidatePOs.length} open PO(s) found
                                            </span>
                                        ) : (
                                            <span className="text-[10px] text-gray-400">
                                                No open POs found for this supplier
                                            </span>
                                        )}
                                    </div>
                                    <select
                                        value={form.data.purchase_order_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'purchase_order_id',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="">-- No Linked Purchase Order --</option>
                                        {candidatePOs.map((po) => (
                                            <option key={po.id} value={po.id}>
                                                {po.po_no} (Total: ฿{Number(po.total).toLocaleString('en-US', { minimumFractionDigits: 2 })})
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            {/* Project & Notes */}
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div>
                                    <label className="block font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Charge to Project (Optional)
                                    </label>
                                    <select
                                        value={form.data.project_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'project_id',
                                                e.target.value,
                                            )
                                        }
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    >
                                        <option value="">
                                            -- No Project --
                                        </option>
                                        {projects.map((p) => (
                                            <option key={p.id} value={p.id}>
                                                {p.project_code} - {p.name}
                                            </option>
                                        ))}
                                    </select>
                                </div>

                                <div>
                                    <label className="block font-semibold text-gray-700 dark:text-gray-300 mb-1">
                                        Internal Notes
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.note}
                                        onChange={(e) =>
                                            form.setData('note', e.target.value)
                                        }
                                        className="w-full rounded-lg border-gray-300 text-xs shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100"
                                    />
                                </div>
                            </div>

                            {/* Actions */}
                            <div className="flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-4 dark:border-gray-800">
                                <button
                                    type="button"
                                    onClick={handleReject}
                                    disabled={
                                        form.processing ||
                                        extraction.status === 'rejected'
                                    }
                                    className="rounded-lg border border-rose-200 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-100 dark:border-rose-900/50 dark:bg-rose-950/40 dark:text-rose-300 disabled:opacity-50"
                                >
                                    ✕ Reject Document
                                </button>

                                <button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !canConvert ||
                                        extraction.status === 'converted'
                                    }
                                    className={`inline-flex items-center gap-2 rounded-lg px-5 py-2 text-xs font-bold text-white shadow-sm focus:outline-none focus:ring-2 disabled:opacity-50 ${
                                        targetType === 'vendor_bill'
                                            ? 'bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500'
                                            : 'bg-emerald-600 hover:bg-emerald-700 focus:ring-emerald-500'
                                    }`}
                                >
                                    {form.processing
                                        ? 'Converting...'
                                        : extraction.status === 'converted'
                                          ? `✓ Converted to ${extraction.target_type === 'vendor_bill' ? 'Vendor Bill' : 'Expense'}`
                                          : targetType === 'vendor_bill'
                                            ? '✓ Convert to Draft Vendor Bill'
                                            : '✓ Convert to Draft Expense'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
