import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorField,
    EditorChoice,
    EditorReference,
} from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Account = { id: string; bank_name: string; account_name: string };
type StatementLine = {
    id: string;
    bank_account_id: string;
    transaction_date: string;
    amount_signed: string;
    reference_no: string | null;
};
type Cheque = {
    id: string;
    bank_account_id: string | null;
    cheque_no: string;
    drawer_or_payee: string;
    due_date: string;
    amount: string;
    status: string;
};

export default function Cheques({
    cheques,
    accounts,
    statementLines,
}: {
    cheques: Cheque[];
    accounts: Account[];
    statementLines: StatementLine[];
}) {
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('cheques.manage');
    const form = useForm({
        bank_account_id: '',
        direction: 'received',
        cheque_no: '',
        bank_name: '',
        drawer_or_payee: '',
        amount: '',
        issue_date: '',
        due_date: '',
    });
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.reset();
        form.clearErrors();
        submission.reset();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canManage || form.processing) return;
        form.post(route('cheques.store'), {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: close,
        });
    };
    function transition(
        cheque: Cheque,
        status: 'deposited' | 'cleared' | 'bounced' | 'cancelled',
    ) {
        if (!canManage || open || form.processing) return;
        const payload: Record<string, string> = { status };
        if (status === 'cleared') {
            const line = statementLines.find(
                (item) =>
                    item.bank_account_id === cheque.bank_account_id &&
                    Math.abs(Number(item.amount_signed)) ===
                        Number(cheque.amount),
            );
            if (!line) return;
            payload.bank_statement_line_id = line.id;
        }
        if (status === 'bounced' || status === 'cancelled')
            payload.reason = `${status} by finance`;
        router.post(route('cheques.transition', cheque.id), payload);
    }

    return (
        <AuthenticatedLayout>
            <Head title="Cheques / PDC" />
            <div className="space-y-6">
                <PageHeader
                    title="Cheques / PDC"
                    actions={
                        canManage && (
                            <PrimaryButton
                                disabled={open}
                                onClick={() => setOpen(true)}
                            >
                                + Register Cheque
                            </PrimaryButton>
                        )
                    }
                    description="Register, deposit, clear, bounce, or cancel issued and received cheques."
                />
                <div className="space-y-6">
                    <DiscardChangesDialog
                        open={guard.confirming}
                        onKeep={guard.keep}
                        onDiscard={guard.discard}
                    />
                    <CreateEditDialog
                        open={open}
                        minimized={minimized}
                        onMinimize={setMinimized}
                        onClose={() => guard.request(close)}
                        title="Register Cheque"
                        breadcrumb="Treasury / Cheques"
                        entityLabel="Cheque"
                        draftLabel={form.data.cheque_no || 'New cheque'}
                        storageKey="cheque-editor-size"
                        formId="cheque-editor"
                        processing={form.processing}
                        saveDisabled={!canManage}
                        saveLabel="Register"
                        status="Register the cheque before deposit or clearance"
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <section
                                aria-label="Cheque details"
                                className={`grid gap-4 p-4 sm:grid-cols-2 sm:p-6 ${expanded ? 'lg:grid-cols-3' : ''}`}
                            >
                                <EditorReference
                                    id="cheque-bank-account"
                                    label="Bank Account"
                                    value={form.data.bank_account_id}
                                    error={form.errors.bank_account_id}
                                    onChange={(v) =>
                                        form.setData('bank_account_id', v)
                                    }
                                    options={accounts.map((a) => ({
                                        id: a.id,
                                        label:
                                            a.bank_name +
                                            ' - ' +
                                            a.account_name,
                                    }))}
                                />
                                <EditorChoice
                                    id="cheque-direction"
                                    label="Direction"
                                    value={form.data.direction}
                                    error={form.errors.direction}
                                    onChange={(v) =>
                                        form.setData('direction', v)
                                    }
                                    options={['received', 'issued']}
                                    required
                                />
                                {(
                                    [
                                        'cheque_no',
                                        'bank_name',
                                        'drawer_or_payee',
                                        'amount',
                                        'issue_date',
                                        'due_date',
                                    ] as const
                                ).map((field) => (
                                    <EditorField
                                        key={field}
                                        id={'cheque-' + field}
                                        label={
                                            {
                                                cheque_no: 'Cheque Number',
                                                bank_name: 'Bank',
                                                drawer_or_payee:
                                                    'Drawer / Payee',
                                                amount: 'Amount',
                                                issue_date: 'Issue Date',
                                                due_date: 'Due Date',
                                            }[field]
                                        }
                                        value={form.data[field]}
                                        error={form.errors[field]}
                                        onChange={(v) => form.setData(field, v)}
                                        type={
                                            field.includes('date')
                                                ? 'date'
                                                : field === 'amount'
                                                  ? 'number'
                                                  : 'text'
                                        }
                                        min={
                                            field === 'amount'
                                                ? 0.01
                                                : undefined
                                        }
                                        step={
                                            field === 'amount'
                                                ? '0.01'
                                                : undefined
                                        }
                                        required
                                    />
                                ))}
                            </section>
                        )}
                    </CreateEditDialog>
                    <Card title="Cheque Register">
                        <DataTable
                            data={cheques}
                            keyExtractor={(item) => item.id}
                            columns={[
                                {
                                    header: 'Cheque',
                                    accessor: (item) => item.cheque_no,
                                },
                                {
                                    header: 'Party',
                                    accessor: (item) => item.drawer_or_payee,
                                },
                                {
                                    header: 'Due',
                                    accessor: (item) => item.due_date,
                                },
                                {
                                    header: 'Amount',
                                    accessor: (item) => item.amount,
                                },
                                {
                                    header: 'Status',
                                    accessor: (item) => item.status,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (item) => (
                                        <div className="flex flex-wrap gap-2">
                                            {item.status === 'registered' && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        transition(
                                                            item,
                                                            'deposited',
                                                        )
                                                    }
                                                >
                                                    Deposit
                                                </button>
                                            )}
                                            {(item.status === 'registered' ||
                                                item.status ===
                                                    'deposited') && (
                                                <button
                                                    type="button"
                                                    disabled={
                                                        !statementLines.some(
                                                            (line) =>
                                                                line.bank_account_id ===
                                                                    item.bank_account_id &&
                                                                Math.abs(
                                                                    Number(
                                                                        line.amount_signed,
                                                                    ),
                                                                ) ===
                                                                    Number(
                                                                        item.amount,
                                                                    ),
                                                        )
                                                    }
                                                    onClick={() =>
                                                        transition(
                                                            item,
                                                            'cleared',
                                                        )
                                                    }
                                                >
                                                    Clear
                                                </button>
                                            )}
                                            {(item.status === 'registered' ||
                                                item.status ===
                                                    'deposited') && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        transition(
                                                            item,
                                                            'bounced',
                                                        )
                                                    }
                                                >
                                                    Bounce
                                                </button>
                                            )}
                                            {(item.status === 'registered' ||
                                                item.status ===
                                                    'deposited') && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        transition(
                                                            item,
                                                            'cancelled',
                                                        )
                                                    }
                                                >
                                                    Cancel
                                                </button>
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
