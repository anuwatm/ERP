import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import EditorAttachments from '@/Components/UI/EditorAttachments';
import { EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import { PageProps } from '@/Types';
import PrimaryButton from '@/Components/PrimaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Account = { id: string; bank_name: string; account_name: string };
type Statement = {
    id: string;
    statement_date_from: string;
    statement_date_to: string;
    line_count: number;
    status: string;
    bank_account: Account;
};
type Line = {
    id: string;
    transaction_date: string;
    amount_signed: string;
    description: string | null;
    reference_no: string | null;
    status: string;
};

export default function BankStatements({
    accounts,
    statements,
    lines,
}: {
    accounts: Account[];
    statements: Statement[];
    lines: Line[];
}) {
    const form = useForm<{ bank_account_id: string; statement: File | null }>({
        bank_account_id: '',
        statement: null,
    });
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canImport = auth.permissions.includes(
        'treasury.reconciliation.manage',
    );
    const guard = useDiscardGuard(
        open && (form.isDirty || !!form.data.statement),
        form.processing,
    );
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.reset();
        form.clearErrors();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canImport || form.processing || !form.data.statement) return;
        form.post(route('bank-statements.import'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: close,
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bank Statements" />
            <div className="space-y-6">
                <PageHeader
                    title="Bank Statements"
                    actions={
                        canImport && (
                            <PrimaryButton
                                disabled={open}
                                onClick={() => setOpen(true)}
                            >
                                Import Statement
                            </PrimaryButton>
                        )
                    }
                    description="Import CSV statements and prepare unreconciled lines."
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
                        title="Import Statement"
                        breadcrumb="Treasury / Bank Statements"
                        entityLabel="Bank statement"
                        draftLabel="CSV statement import"
                        storageKey="statement-editor-size"
                        formId="statement-editor"
                        processing={form.processing}
                        saveDisabled={!canImport || !form.data.statement}
                        saveLabel="Import CSV"
                        status="Import statement lines for reconciliation"
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {() => (
                            <div className="space-y-4 p-4 sm:p-6">
                                <p className="break-words text-sm">
                                    CSV columns: transaction_date, amount,
                                    description, reference_no, balance_after
                                </p>
                                <EditorReference
                                    id="statement-bank"
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
                                    required
                                />
                                <EditorAttachments
                                    selected={form.data.statement}
                                    onChange={(file) =>
                                        form.setData('statement', file)
                                    }
                                    existing={[]}
                                    error={form.errors.statement}
                                    disabled={form.processing}
                                    accept=".csv,text/csv"
                                />
                            </div>
                        )}
                    </CreateEditDialog>
                    <Card
                        title="Imported Statements"
                        description={`${statements.length} statement(s)`}
                    >
                        <DataTable
                            data={statements}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Account',
                                    accessor: (row) =>
                                        `${row.bank_account.bank_name} - ${row.bank_account.account_name}`,
                                },
                                {
                                    header: 'Period',
                                    accessor: (row) =>
                                        `${row.statement_date_from} to ${row.statement_date_to}`,
                                },
                                {
                                    header: 'Lines',
                                    accessor: (row) => row.line_count,
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge variant="neutral" size="sm">
                                            {row.status}
                                        </Badge>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                </div>
                <Card
                    title="Unreconciled Lines"
                    description="Latest 250 lines. Matching is the next step."
                >
                    <DataTable
                        data={lines}
                        keyExtractor={(row) => row.id}
                        columns={[
                            {
                                header: 'Date',
                                accessor: (row) => row.transaction_date,
                            },
                            {
                                header: 'Description',
                                accessor: (row) => row.description ?? '-',
                            },
                            {
                                header: 'Reference',
                                accessor: (row) => row.reference_no ?? '-',
                            },
                            {
                                header: 'Amount',
                                accessor: (row) => money(row.amount_signed),
                            },
                            {
                                header: 'Status',
                                accessor: (row) => (
                                    <Badge variant="neutral" size="sm">
                                        {row.status}
                                    </Badge>
                                ),
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
