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
import ExpenseEditorDialog from './ExpenseEditorDialog';

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
    project?: Project | null;
};

const today = new Date().toISOString().slice(0, 10);

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
    const [isCreateOpen, setIsCreateOpen] = useState(false);
    const [editingExpense, setEditingExpense] = useState<Expense | null>(null);
    const [payingExpense, setPayingExpense] = useState<Expense | null>(null);
    const [rejectingExpense, setRejectingExpense] = useState<Expense | null>(
        null,
    );

    const rejectForm = useForm({ note: '' });
    const payForm = useForm({ amount: '', payment_date: today, note: '' });

    const approveExpense = (expense: Expense) => {
        router.post(
            route('expenses.approve', expense.id),
            {},
            { preserveScroll: true },
        );
    };

    const startPayExpense = (expense: Expense) => {
        setPayingExpense(expense);
        payForm.setData({
            amount: expense.balance_due || expense.amount,
            payment_date: today,
            note: '',
        });
    };

    const submitPayment = (event: FormEvent) => {
        event.preventDefault();
        if (!payingExpense) return;

        payForm.post(
            route('expenses.vendor-payments.store', payingExpense.id),
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => {
                    payForm.setData({
                        amount: '',
                        payment_date: today,
                        note: '',
                    });
                    setPayingExpense(null);
                },
            },
        );
    };

    const rejectExpense = (event: FormEvent) => {
        event.preventDefault();
        if (!rejectingExpense) return;

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
                    actions={
                        canCreateExpenses && (
                            <PrimaryButton
                                type="button"
                                onClick={() => setIsCreateOpen(true)}
                            >
                                + New Expense
                            </PrimaryButton>
                        )
                    }
                />

                <ExpenseEditorDialog
                    open={isCreateOpen || editingExpense !== null}
                    expense={editingExpense}
                    categories={categories}
                    onClose={() => {
                        setIsCreateOpen(false);
                        setEditingExpense(null);
                    }}
                />

                {payingExpense && (
                    <Card
                        title={`Record Payment for ${payingExpense.expense_no}`}
                        description={`${payingExpense.title} — Outstanding: ${thbMoney(payingExpense.balance_due || payingExpense.amount)}`}
                    >
                        <form onSubmit={submitPayment} className="space-y-3">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <div>
                                    <InputLabel value="Amount" />
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        min="0.01"
                                        value={payForm.data.amount}
                                        onChange={(e) =>
                                            payForm.setData(
                                                'amount',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        className="mt-1 block w-full"
                                    />
                                    <InputError
                                        message={payForm.errors.amount}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Payment Date" />
                                    <TextInput
                                        type="date"
                                        value={payForm.data.payment_date}
                                        onChange={(e) =>
                                            payForm.setData(
                                                'payment_date',
                                                e.target.value,
                                            )
                                        }
                                        required
                                        className="mt-1 block w-full"
                                    />
                                    <InputError
                                        message={payForm.errors.payment_date}
                                        className="mt-1"
                                    />
                                </div>
                                <div>
                                    <InputLabel value="Payment Note" />
                                    <TextInput
                                        value={payForm.data.note}
                                        onChange={(e) =>
                                            payForm.setData(
                                                'note',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Optional check or transfer ref"
                                        className="mt-1 block w-full"
                                    />
                                    <InputError
                                        message={payForm.errors.note}
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <PrimaryButton disabled={payForm.processing}>
                                    Confirm Payment
                                </PrimaryButton>
                                <SecondaryButton
                                    type="button"
                                    onClick={() => setPayingExpense(null)}
                                >
                                    Cancel
                                </SecondaryButton>
                            </div>
                        </form>
                    </Card>
                )}

                {rejectingExpense && (
                    <Card
                        title={`Reject ${rejectingExpense.expense_no}`}
                        description={rejectingExpense.title}
                    >
                        <form onSubmit={rejectExpense} className="space-y-3">
                            <div>
                                <InputLabel value="Rejection Reason *" />
                                <TextInput
                                    value={rejectForm.data.note}
                                    onChange={(e) =>
                                        rejectForm.setData(
                                            'note',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    placeholder="Explain why this expense is rejected..."
                                    className="mt-1 block w-full"
                                />
                                <InputError
                                    message={rejectForm.errors.note}
                                    className="mt-1"
                                />
                            </div>
                            <div className="flex gap-2">
                                <PrimaryButton disabled={rejectForm.processing}>
                                    Confirm Rejection
                                </PrimaryButton>
                                <SecondaryButton
                                    type="button"
                                    onClick={() => setRejectingExpense(null)}
                                >
                                    Cancel
                                </SecondaryButton>
                            </div>
                        </form>
                    </Card>
                )}

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
                                        {Number(row.tax_amount ?? 0) > 0 && (
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
                                                        setEditingExpense(row)
                                                    }
                                                >
                                                    Edit
                                                </SecondaryButton>
                                            )}
                                        {row.status === 'draft' && (
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'workflows.submit',
                                                            ['expense', row.id],
                                                        ),
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                            >
                                                Submit Approval
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
                                            [
                                                'approved',
                                                'partially_paid',
                                            ].includes(row.status) && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        startPayExpense(row)
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
                                                        setRejectingExpense(row)
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
            </div>
        </AuthenticatedLayout>
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
