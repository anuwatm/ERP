import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { router } from '@inertiajs/react';
import { FormEvent, useRef, useState } from 'react';

type VendorBillForm = {
    vendor_invoice_no: string;
    invoice_date: string;
    amount: string;
    currency: string;
    note: string;
};

const initialForm: VendorBillForm = {
    vendor_invoice_no: '',
    invoice_date: new Date().toISOString().slice(0, 10),
    amount: '',
    currency: 'THB',
    note: '',
};

export default function VendorBillDialog({
    open,
    onClose,
}: {
    open: boolean;
    onClose: () => void;
}) {
    const [form, setForm] = useState<VendorBillForm>(initialForm);
    const [file, setFile] = useState<File | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const fileInputRef = useRef<HTMLInputElement>(null);

    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    const isDirty =
        open &&
        (form.vendor_invoice_no !== initialForm.vendor_invoice_no ||
            form.invoice_date !== initialForm.invoice_date ||
            form.amount !== initialForm.amount ||
            form.currency !== initialForm.currency ||
            form.note !== initialForm.note ||
            file !== null);

    const resetDialog = () => {
        setForm(initialForm);
        setFile(null);
        setErrors({});
        setMinimized(false);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
        resetSubmissionKey();
    };

    const { request, keep, discard, confirming } = useDiscardGuard(
        isDirty,
        processing,
    );

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        setProcessing(true);
        setErrors({});

        const formData = new FormData();
        formData.append('vendor_invoice_no', form.vendor_invoice_no);
        formData.append('invoice_date', form.invoice_date);
        formData.append('amount', form.amount);
        formData.append('currency', form.currency);
        if (file) {
            formData.append('file', file);
        }
        if (form.note) {
            formData.append('note', form.note);
        }

        router.post(route('portal.vendor-bills.store'), formData, {
            forceFormData: true,
            preserveScroll: true,
            headers: headers(),
            onSuccess: () => {
                setProcessing(false);
                resetDialog();
                onClose();
            },
            onError: (errs) => {
                setProcessing(false);
                setErrors(errs);
            },
        });
    };

    return (
        <>
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() =>
                    request(() => {
                        resetDialog();
                        onClose();
                    })
                }
                title="Submit Vendor Bill"
                breadcrumb="Supplier Portal / Bills"
                entityLabel="Vendor Bill"
                draftLabel={form.vendor_invoice_no || 'New Bill'}
                storageKey="portal-vendor-bill-dialog"
                formId="portal-vendor-bill-form"
                processing={processing}
                saveLabel="Submit for Review"
                status={null}
                errors={errors}
                onSubmit={handleSubmit}
                requiresPasswordConfirmation={false}
            >
                {(expanded) => (
                    <div
                        className={`grid gap-4 ${
                            expanded ? 'md:grid-cols-2' : 'grid-cols-1'
                        }`}
                    >
                        <EditorField
                            id="vendor_invoice_no"
                            label="Vendor Invoice Number"
                            value={form.vendor_invoice_no}
                            error={errors.vendor_invoice_no}
                            required
                            onChange={(val: string) =>
                                setForm((prev) => ({
                                    ...prev,
                                    vendor_invoice_no: val,
                                }))
                            }
                        />

                        <EditorField
                            id="invoice_date"
                            label="Invoice Date"
                            type="date"
                            value={form.invoice_date}
                            error={errors.invoice_date}
                            required
                            onChange={(val: string) =>
                                setForm((prev) => ({
                                    ...prev,
                                    invoice_date: val,
                                }))
                            }
                        />

                        <EditorField
                            id="amount"
                            label="Amount"
                            type="number"
                            step="0.01"
                            min={0.01}
                            value={form.amount}
                            error={errors.amount}
                            required
                            onChange={(val: string) =>
                                setForm((prev) => ({
                                    ...prev,
                                    amount: val,
                                }))
                            }
                        />

                        <EditorField
                            id="currency"
                            label="Currency"
                            value={form.currency}
                            error={errors.currency}
                            required
                            maxLength={3}
                            onChange={(val: string) =>
                                setForm((prev) => ({
                                    ...prev,
                                    currency: val.toUpperCase(),
                                }))
                            }
                        />

                        <div className={expanded ? 'md:col-span-2' : ''}>
                            <label
                                htmlFor="bill-file"
                                className="block text-xs font-semibold uppercase text-slate-700 dark:text-slate-300 mb-1"
                            >
                                Invoice Attachment (PDF, JPG, PNG, WEBP){' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
                                id="bill-file"
                                ref={fileInputRef}
                                type="file"
                                required
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                                className="block w-full text-sm text-slate-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100 dark:text-slate-300 dark:file:bg-slate-800 dark:file:text-indigo-400"
                                onChange={(e) =>
                                    setFile(e.target.files?.[0] ?? null)
                                }
                            />
                            {errors.file && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.file}
                                </p>
                            )}
                        </div>

                        <div className={expanded ? 'md:col-span-2' : ''}>
                            <EditorField
                                id="note"
                                label="Note / Memo"
                                multiline
                                value={form.note}
                                error={errors.note}
                                onChange={(val: string) =>
                                    setForm((prev) => ({
                                        ...prev,
                                        note: val,
                                    }))
                                }
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>

            <DiscardChangesDialog
                open={confirming}
                onKeep={keep}
                onDiscard={discard}
            />
        </>
    );
}
