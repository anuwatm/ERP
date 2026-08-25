import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { thbMoney } from '@/Utils/format';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Project = { id: string; project_code: string; name: string };

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
    project?: Project | null;
};

type ExpenseForm = {
    category: string;
    title: string;
    amount: string;
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

export default function Expenses({
    expenses,
    categories,
    canCreateExpenses,
    canUpdateExpenses,
    canApproveExpenses,
    canPayExpenses,
    canRejectExpenses,
}: {
    expenses: Expense[];
    statuses: string[];
    categories: string[];
    filters: Record<string, string>;
    canCreateExpenses: boolean;
    canUpdateExpenses: boolean;
    canApproveExpenses: boolean;
    canPayExpenses: boolean;
    canRejectExpenses: boolean;
}) {
    const [editingExpense, setEditingExpense] = useState<Expense | null>(null);
    const [rejectingExpense, setRejectingExpense] = useState<Expense | null>(
        null,
    );
    const form = useForm<ExpenseForm>(emptyExpense);
    const rejectForm = useForm({ note: '' });
    const payForm = useForm({ paid_at: today, note: '' });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptyExpense);
                setEditingExpense(null);
            },
        };

        if (editingExpense) {
            form.patch(route('expenses.update', editingExpense.id), options);
        } else {
            form.post(route('expenses.store'), options);
        }
    };

    const editExpense = (expense: Expense) => {
        setEditingExpense(expense);
        form.setData({
            category: expense.category,
            title: expense.title,
            amount: expense.amount,
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
    };

    const resetForm = () => {
        setEditingExpense(null);
        form.setData(emptyExpense);
        form.clearErrors();
    };

    const approveExpense = (expense: Expense) => {
        router.post(
            route('expenses.approve', expense.id),
            {},
            {
                preserveScroll: true,
            },
        );
    };

    const payExpense = (expense: Expense) => {
        payForm.post(route('expenses.pay', expense.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => payForm.setData({ paid_at: today, note: '' }),
        });
    };

    const rejectExpense = (event: FormEvent) => {
        event.preventDefault();
        if (!rejectingExpense) {
            return;
        }

        rejectForm.post(route('expenses.reject', rejectingExpense.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.setData('note', '');
                setRejectingExpense(null);
            },
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Expenses" />

            <div className="space-y-6">
                <PageHeader
                    title="Expenses"
                    description="Draft, approve, reject, and mark company costs paid."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_380px]">
                    <Card title="Expense List" description="Company costs">
                        <DataTable
                            data={expenses}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Expense',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {row.title}
                                            </div>
                                            <div className="font-mono text-xs text-slate-500 dark:text-slate-300">
                                                {row.expense_no}
                                            </div>
                                            {row.project && (
                                                <div className="text-xs text-slate-500 dark:text-slate-300">
                                                    {row.project.project_code}{' '}
                                                    {row.project.name}
                                                </div>
                                            )}
                                            {row.receipt_file && (
                                                <a
                                                    href={route(
                                                        'files.download',
                                                        row.receipt_file.id,
                                                    )}
                                                    className="text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-300"
                                                >
                                                    {row.receipt_file.file_name}
                                                </a>
                                            )}
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Date',
                                    accessor: (row) =>
                                        row.expense_date?.slice(0, 10) ?? '-',
                                },
                                {
                                    header: 'Category',
                                    accessor: (row) => titleCase(row.category),
                                },
                                {
                                    header: 'Amount',
                                    accessor: (row) => (
                                        <div>
                                            <div>{thbMoney(row.amount)}</div>
                                            {Number(row.tax_amount ?? 0) >
                                                0 && (
                                                <div className="text-xs text-slate-500">
                                                    VAT:{' '}
                                                    {thbMoney(
                                                        row.tax_amount ?? '0',
                                                    )}
                                                </div>
                                            )}
                                            {Number(
                                                row.withholding_tax_amount ?? 0,
                                            ) > 0 && (
                                                <div className="text-xs text-slate-500">
                                                    WHT:{' '}
                                                    {thbMoney(
                                                        row.withholding_tax_amount ??
                                                            '0',
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge
                                            variant={statusVariant(row.status)}
                                            size="sm"
                                        >
                                            {row.status}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            {canUpdateExpenses &&
                                                row.status === 'draft' && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            editExpense(row)
                                                        }
                                                    >
                                                        Edit
                                                    </SecondaryButton>
                                                )}
                                            {Number(
                                                row.withholding_tax_amount ?? 0,
                                            ) > 0 && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        window.open(
                                                            route(
                                                                'expenses.withholding-certificate',
                                                                row.id,
                                                            ),
                                                            '_blank',
                                                            'noopener,noreferrer',
                                                        )
                                                    }
                                                >
                                                    50-Tawi
                                                </SecondaryButton>
                                            )}
                                            {canApproveExpenses &&
                                                row.status === 'draft' && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            approveExpense(row)
                                                        }
                                                    >
                                                        Approve
                                                    </SecondaryButton>
                                                )}
                                            {canPayExpenses &&
                                                row.status === 'approved' && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            payExpense(row)
                                                        }
                                                    >
                                                        Pay
                                                    </SecondaryButton>
                                                )}
                                            {canRejectExpenses &&
                                                ['draft', 'approved'].includes(
                                                    row.status,
                                                ) && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            setRejectingExpense(
                                                                row,
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </SecondaryButton>
                                                )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </Card>

                    <div className="space-y-6">
                        {(canCreateExpenses || editingExpense) && (
                            <Card
                                title={
                                    editingExpense
                                        ? 'Edit Expense'
                                        : 'Create Expense'
                                }
                                description="Draft cost entry"
                            >
                                <form onSubmit={submit} className="space-y-3">
                                    <SelectField
                                        label="Category"
                                        value={form.data.category}
                                        options={categories}
                                        onChange={(value) =>
                                            form.setData('category', value)
                                        }
                                    />
                                    <Field
                                        label="Title"
                                        value={form.data.title}
                                        error={form.errors.title}
                                        onChange={(value) =>
                                            form.setData('title', value)
                                        }
                                        required
                                    />
                                    <Field
                                        label="Amount"
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={form.data.amount}
                                        error={form.errors.amount}
                                        onChange={(value) =>
                                            form.setData('amount', value)
                                        }
                                        required
                                    />
                                    <div className="grid grid-cols-2 gap-2">
                                        <SelectField
                                            label="VAT Mode"
                                            value={form.data.tax_mode}
                                            options={[
                                                'no_tax',
                                                'exclusive',
                                                'inclusive',
                                            ]}
                                            onChange={(value) =>
                                                form.setData('tax_mode', value)
                                            }
                                        />
                                        <Field
                                            label="Tax Invoice No."
                                            value={form.data.tax_invoice_no}
                                            error={form.errors.tax_invoice_no}
                                            onChange={(value) =>
                                                form.setData(
                                                    'tax_invoice_no',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <Field
                                            label="WHT Rate %"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={
                                                form.data.withholding_tax_rate
                                            }
                                            error={
                                                form.errors.withholding_tax_rate
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'withholding_tax_rate',
                                                    value,
                                                )
                                            }
                                        />
                                        <SelectField
                                            label="WHT Form"
                                            value={
                                                form.data.withholding_tax_form
                                            }
                                            options={['pnd3', 'pnd53']}
                                            onChange={(value) =>
                                                form.setData(
                                                    'withholding_tax_form',
                                                    value,
                                                )
                                            }
                                        />
                                    </div>
                                    <Field
                                        label="Expense Date"
                                        type="date"
                                        value={form.data.expense_date}
                                        error={form.errors.expense_date}
                                        onChange={(value) =>
                                            form.setData('expense_date', value)
                                        }
                                        required
                                    />
                                    <Field
                                        label="Supplier ID"
                                        value={form.data.supplier_id}
                                        error={form.errors.supplier_id}
                                        onChange={(value) =>
                                            form.setData('supplier_id', value)
                                        }
                                    />
                                    <Field
                                        label="Note"
                                        value={form.data.note}
                                        error={form.errors.note}
                                        onChange={(value) =>
                                            form.setData('note', value)
                                        }
                                    />
                                    <div>
                                        <InputLabel value="Receipt" />
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            onChange={(event) =>
                                                form.setData(
                                                    'receipt',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        />
                                        <InputError
                                            message={form.errors.receipt}
                                            className="mt-1"
                                        />
                                    </div>
                                    <div className="flex flex-wrap gap-2 pt-2">
                                        <PrimaryButton
                                            disabled={form.processing}
                                        >
                                            {editingExpense ? 'Save' : 'Create'}
                                        </PrimaryButton>
                                        {editingExpense && (
                                            <SecondaryButton
                                                type="button"
                                                onClick={resetForm}
                                            >
                                                Cancel
                                            </SecondaryButton>
                                        )}
                                    </div>
                                </form>
                            </Card>
                        )}

                        {canPayExpenses && (
                            <Card title="Pay Details" description="Used by Pay">
                                <div className="space-y-3">
                                    <Field
                                        label="Paid At"
                                        type="date"
                                        value={payForm.data.paid_at}
                                        error={payForm.errors.paid_at}
                                        onChange={(value) =>
                                            payForm.setData('paid_at', value)
                                        }
                                    />
                                    <Field
                                        label="Payment Note"
                                        value={payForm.data.note}
                                        error={payForm.errors.note}
                                        onChange={(value) =>
                                            payForm.setData('note', value)
                                        }
                                    />
                                </div>
                            </Card>
                        )}

                        {rejectingExpense && (
                            <Card
                                title={`Reject ${rejectingExpense.expense_no}`}
                                description={rejectingExpense.title}
                            >
                                <form
                                    onSubmit={rejectExpense}
                                    className="space-y-3"
                                >
                                    <Field
                                        label="Reason"
                                        value={rejectForm.data.note}
                                        error={rejectForm.errors.note}
                                        onChange={(value) =>
                                            rejectForm.setData('note', value)
                                        }
                                        required
                                    />
                                    <div className="flex flex-wrap gap-2">
                                        <PrimaryButton
                                            disabled={rejectForm.processing}
                                        >
                                            Reject
                                        </PrimaryButton>
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                setRejectingExpense(null)
                                            }
                                        >
                                            Cancel
                                        </SecondaryButton>
                                    </div>
                                </form>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    onChange,
    error,
    required = false,
    type = 'text',
    step,
    min,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    type?: string;
    step?: string;
    min?: string;
}) {
    return (
        <div>
            <InputLabel value={label} />
            <TextInput
                type={type}
                value={value}
                step={step}
                min={min}
                required={required}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 block w-full"
            />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function SelectField({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: string[];
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <InputLabel value={label} />
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 block w-full rounded-md border-slate-300 bg-white text-slate-900 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-700 dark:bg-slate-950 dark:text-white"
            >
                {options.map((option) => (
                    <option key={option} value={option}>
                        {titleCase(option)}
                    </option>
                ))}
            </select>
        </div>
    );
}

function titleCase(value: string) {
    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function statusVariant(status: string) {
    if (status === 'paid') {
        return 'success';
    }

    if (status === 'approved') {
        return 'info';
    }

    if (status === 'rejected') {
        return 'danger';
    }

    return 'neutral';
}
