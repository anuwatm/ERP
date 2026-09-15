import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import EditorAttachments from '@/Components/UI/EditorAttachments';
import { EditorChoice, EditorField } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type ReceiptFile = {
    id: string;
    file_name: string;
    mime_type?: string | null;
    size_bytes?: number | null;
};

type Expense = {
    id: string;
    expense_no: string;
    category: string;
    title: string;
    amount: string;
    balance_due?: string;
    currency?: string;
    tax_mode?: string;
    tax_invoice_no?: string | null;
    tax_amount?: string;
    withholding_tax_rate?: string;
    withholding_tax_amount?: string;
    withholding_tax_form?: string | null;
    expense_date: string;
    project_id?: string | null;
    supplier_id?: string | null;
    status: string;
    approved_at?: string | null;
    paid_at?: string | null;
    note?: string | null;
    receipt_file?: ReceiptFile | null;
};

type ExpenseForm = {
    category: string;
    title: string;
    amount: string;
    currency: string;
    tax_mode: string;
    tax_invoice_no: string;
    withholding_tax_rate: string;
    withholding_tax_form: string;
    expense_date: string;
    project_id: string;
    supplier_id: string;
    receipt: File | null;
    note: string;
};

const today = new Date().toISOString().slice(0, 10);

const emptyExpense: ExpenseForm = {
    category: 'misc',
    title: '',
    amount: '0.00',
    currency: 'THB',
    tax_mode: 'no_tax',
    tax_invoice_no: '',
    withholding_tax_rate: '0.00',
    withholding_tax_form: 'pnd53',
    expense_date: today,
    project_id: '',
    supplier_id: '',
    receipt: null,
    note: '',
};

export default function ExpenseEditorDialog({
    open,
    expense,
    categories,
    onClose,
}: {
    open: boolean;
    expense: Expense | null;
    categories: string[];
    onClose: () => void;
}) {
    const form = useForm<ExpenseForm>(emptyExpense);
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    useEffect(() => {
        if (!open) return;
        if (expense) {
            form.setData({
                category: expense.category,
                title: expense.title,
                amount: expense.amount,
                currency: expense.currency ?? 'THB',
                tax_mode: expense.tax_mode ?? 'no_tax',
                tax_invoice_no: expense.tax_invoice_no ?? '',
                withholding_tax_rate: expense.withholding_tax_rate ?? '0.00',
                withholding_tax_form: expense.withholding_tax_form ?? 'pnd53',
                expense_date: expense.expense_date?.slice(0, 10) ?? today,
                project_id: expense.project_id ?? '',
                supplier_id: expense.supplier_id ?? '',
                receipt: null,
                note: expense.note ?? '',
            });
        } else {
            form.setData(emptyExpense);
        }
        form.clearErrors();
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, expense?.id]);

    const isDirty =
        form.data.title !== (expense ? expense.title : '') ||
        form.data.amount !== (expense ? expense.amount : '0.00') ||
        form.data.category !== (expense ? expense.category : 'misc') ||
        form.data.currency !==
            (expense ? (expense.currency ?? 'THB') : 'THB') ||
        form.data.tax_mode !==
            (expense ? (expense.tax_mode ?? 'no_tax') : 'no_tax') ||
        form.data.tax_invoice_no !==
            (expense ? (expense.tax_invoice_no ?? '') : '') ||
        form.data.withholding_tax_rate !==
            (expense ? (expense.withholding_tax_rate ?? '0.00') : '0.00') ||
        form.data.withholding_tax_form !==
            (expense ? (expense.withholding_tax_form ?? 'pnd53') : 'pnd53') ||
        form.data.supplier_id !==
            (expense ? (expense.supplier_id ?? '') : '') ||
        form.data.note !== (expense ? (expense.note ?? '') : '') ||
        form.data.receipt !== null;

    const guard = useDiscardGuard(isDirty, form.processing);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.title || !form.data.amount) return;

        const options = {
            headers: headers(),
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                onClose();
            },
        };

        if (expense) {
            form.patch(route('expenses.update', expense.id), options);
        } else {
            form.post(route('expenses.store'), options);
        }
    }

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
                title={expense ? 'Edit Expense' : 'Create Expense'}
                breadcrumb="Finance / Expenses"
                entityLabel="Expense"
                draftLabel={
                    form.data.title ||
                    (expense ? expense.expense_no : 'New Expense')
                }
                storageKey="expense-editor-size"
                formId="expense-editor-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.title ||
                    !form.data.amount ||
                    Number(form.data.amount) <= 0
                }
                saveLabel={expense ? 'Save Changes' : 'Create Expense'}
                status={expense ? expense.status : 'Draft'}
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="expense-title"
                                label="Title"
                                value={form.data.title}
                                onChange={(val: string) =>
                                    form.setData('title', val)
                                }
                                error={form.errors.title}
                                required
                            />

                            <EditorChoice
                                id="expense-category"
                                label="Category"
                                value={form.data.category}
                                options={categories}
                                onChange={(val: string) =>
                                    form.setData('category', val)
                                }
                                error={form.errors.category}
                                required
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="expense-amount"
                                label="Amount"
                                type="number"
                                step="0.01"
                                min={0.01}
                                value={form.data.amount}
                                onChange={(val: string) =>
                                    form.setData('amount', val)
                                }
                                error={form.errors.amount}
                                required
                            />

                            <EditorField
                                id="expense-currency"
                                label="Currency"
                                value={form.data.currency}
                                onChange={(val: string) =>
                                    form.setData('currency', val.toUpperCase())
                                }
                                error={form.errors.currency}
                                maxLength={3}
                                required
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorChoice
                                id="expense-tax-mode"
                                label="VAT Mode"
                                value={form.data.tax_mode}
                                options={['no_tax', 'exclusive', 'inclusive']}
                                onChange={(val: string) =>
                                    form.setData('tax_mode', val)
                                }
                                error={form.errors.tax_mode}
                            />

                            <EditorField
                                id="expense-tax-invoice-no"
                                label="Tax Invoice No."
                                value={form.data.tax_invoice_no}
                                onChange={(val: string) =>
                                    form.setData('tax_invoice_no', val)
                                }
                                error={form.errors.tax_invoice_no}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="expense-wht-rate"
                                label="WHT Rate %"
                                type="number"
                                step="0.01"
                                min={0}
                                value={form.data.withholding_tax_rate}
                                onChange={(val: string) =>
                                    form.setData('withholding_tax_rate', val)
                                }
                                error={form.errors.withholding_tax_rate}
                            />

                            <EditorChoice
                                id="expense-wht-form"
                                label="WHT Form"
                                value={form.data.withholding_tax_form}
                                options={['pnd3', 'pnd53']}
                                onChange={(val: string) =>
                                    form.setData('withholding_tax_form', val)
                                }
                                error={form.errors.withholding_tax_form}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="expense-date"
                                label="Expense Date"
                                type="date"
                                value={form.data.expense_date}
                                onChange={(val: string) =>
                                    form.setData('expense_date', val)
                                }
                                error={form.errors.expense_date}
                                required
                            />

                            <EditorField
                                id="expense-supplier-id"
                                label="Supplier ID"
                                value={form.data.supplier_id}
                                onChange={(val: string) =>
                                    form.setData('supplier_id', val)
                                }
                                error={form.errors.supplier_id}
                            />
                        </div>

                        <EditorField
                            id="expense-note"
                            label="Note"
                            multiline
                            value={form.data.note}
                            onChange={(val: string) =>
                                form.setData('note', val)
                            }
                            error={form.errors.note}
                        />

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-slate-700 dark:text-slate-300">
                                Receipt / Proof
                            </label>
                            <EditorAttachments
                                selected={form.data.receipt}
                                onChange={(file) => {
                                    form.setData('receipt', file);
                                    form.clearErrors('receipt');
                                }}
                                existing={
                                    expense?.receipt_file
                                        ? [
                                              {
                                                  id: expense.receipt_file.id,
                                                  name: expense.receipt_file
                                                      .file_name,
                                                  url: route(
                                                      'files.download',
                                                      expense.receipt_file.id,
                                                  ),
                                                  status: 'Current receipt',
                                              },
                                          ]
                                        : []
                                }
                                accept=".pdf,.jpg,.jpeg,.png,.webp"
                            />
                            {form.errors.receipt && (
                                <p className="mt-1 text-sm text-red-700 dark:text-red-300">
                                    {form.errors.receipt}
                                </p>
                            )}
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
