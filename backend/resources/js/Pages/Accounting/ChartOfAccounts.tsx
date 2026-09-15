import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorChoice } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import { useState, FormEvent } from 'react';
import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm, usePage } from '@inertiajs/react';

type Account = {
    id: string;
    code: string;
    name: string;
    account_type: string;
    normal_balance: string;
    is_postable: boolean;
    status: string;
};

export default function ChartOfAccounts({
    accounts,
    types,
}: {
    accounts: Account[];
    types: string[];
}) {
    const form = useForm({
        code: '',
        name: '',
        account_type: '',
        is_postable: true,
    });
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes(
        'accounting.chart_accounts.manage',
    );
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.reset();
        form.clearErrors();
        submission.reset();
    };
    const create = () => {
        if (canManage && !form.processing)
            guard.request(() => {
                close();
                setOpen(true);
            });
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canManage || form.processing) return;
        form.post(route('accounting.chart-of-accounts.store'), {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: close,
        });
    };
    return (
        <AuthenticatedLayout>
            <Head title="Chart of Accounts" />
            <div className="space-y-6">
                <PageHeader
                    actions={
                        canManage && (
                            <PrimaryButton onClick={create}>
                                + Create Account
                            </PrimaryButton>
                        )
                    }
                    title="Chart of Accounts"
                    description="Manage organization accounts used by immutable journal postings."
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
                        title="Create Account"
                        breadcrumb="Accounting / Chart of Accounts"
                        entityLabel="Ledger account"
                        draftLabel={form.data.name || 'New account'}
                        storageKey="coa-editor-size"
                        formId="coa-editor"
                        processing={form.processing}
                        saveDisabled={!canManage}
                        saveLabel="Create Account"
                        status="Normal balance is assigned from account type"
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <section
                                aria-label="Account details"
                                className={
                                    expanded
                                        ? 'grid gap-4 p-4 sm:grid-cols-2 lg:grid-cols-3'
                                        : 'grid gap-4 p-4 sm:grid-cols-2'
                                }
                            >
                                <EditorField
                                    id="coa-code"
                                    label="Account Code"
                                    value={form.data.code}
                                    error={form.errors.code}
                                    onChange={(v) => form.setData('code', v)}
                                    required
                                />
                                <EditorField
                                    id="coa-name"
                                    label="Account Name"
                                    value={form.data.name}
                                    error={form.errors.name}
                                    onChange={(v) => form.setData('name', v)}
                                    required
                                />
                                <EditorChoice
                                    id="coa-type"
                                    label="Account Type"
                                    value={form.data.account_type}
                                    error={form.errors.account_type}
                                    onChange={(v) =>
                                        form.setData('account_type', v)
                                    }
                                    options={['', ...types]}
                                    required
                                />
                            </section>
                        )}
                    </CreateEditDialog>
                    <Card title="Accounts">
                        <DataTable
                            data={accounts}
                            keyExtractor={(item) => item.id}
                            columns={[
                                {
                                    header: 'Code',
                                    accessor: (item) => item.code,
                                },
                                {
                                    header: 'Name',
                                    accessor: (item) => item.name,
                                },
                                {
                                    header: 'Type',
                                    accessor: (item) => item.account_type,
                                },
                                {
                                    header: 'Normal',
                                    accessor: (item) => item.normal_balance,
                                },
                                {
                                    header: 'Postable',
                                    accessor: (item) =>
                                        item.is_postable ? 'Yes' : 'No',
                                },
                                {
                                    header: 'Status',
                                    accessor: (item) => item.status,
                                },
                            ]}
                        />
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
