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
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Branch = { id: string; code: string; name: string };
type Account = {
    id: string;
    branch_id: string | null;
    branch: Branch | null;
    chart_of_account_id: string | null;
    chart_of_account: { id: string; code: string; name: string } | null;
    bank_name: string;
    bank_code: string | null;
    branch_name: string | null;
    account_name: string;
    account_number_masked: string;
    account_type: string;
    currency: string;
    is_cash_account: boolean;
    status: string;
    opening_balance: string;
    opening_balance_date: string | null;
};

type AccountForm = {
    branch_id: string;
    chart_of_account_id: string;
    bank_name: string;
    bank_code: string;
    branch_name: string;
    account_name: string;
    account_number: string;
    account_type: string;
    currency: string;
    is_cash_account: boolean;
    status: string;
    opening_balance: string;
    opening_balance_date: string;
};

const emptyForm: AccountForm = {
    branch_id: '',
    chart_of_account_id: '',
    bank_name: '',
    bank_code: '',
    branch_name: '',
    account_name: '',
    account_number: '',
    account_type: 'savings',
    currency: 'THB',
    is_cash_account: false,
    status: 'active',
    opening_balance: '0.00',
    opening_balance_date: '',
};

export default function BankAccounts({
    accounts,
    branches,
    chartAccounts,
    accountTypes,
    statuses,
}: {
    accounts: Account[];
    branches: Branch[];
    chartAccounts: { id: string; code: string; name: string }[];
    accountTypes: string[];
    statuses: string[];
}) {
    const [editing, setEditing] = useState<Account | null>(null);
    const form = useForm<AccountForm>(emptyForm);
    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const { auth } = usePage<PageProps>().props;
    const canCreate = auth.permissions.includes('treasury.accounts.manage');
    const canEdit = auth.permissions.includes('treasury.accounts.manage');

    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        setEditing(null);
        form.setDefaults(emptyForm);
        form.setData(emptyForm);
        form.clearErrors();
        submission.reset();
    };
    const create = () => {
        if (!canCreate || form.processing) return;
        guard.request(() => {
            close();
            setOpen(true);
        });
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (form.processing || (editing ? !canEdit : !canCreate)) return;
        const options = {
            preserveScroll: true,
            onSuccess: close,
        };

        if (editing) {
            form.patch(route('bank-accounts.update', editing.id), options);
        } else {
            form.post(route('bank-accounts.store'), {
                ...options,
                headers: submission.headers(),
            });
        }
    };

    const edit = (account: Account) => {
        if (!canEdit || form.processing) return;
        guard.request(() => {
            setOpen(true);
            setMinimized(false);
            form.clearErrors();
            submission.reset();
            setEditing(account);
            const data = {
                branch_id: account.branch_id ?? '',
                chart_of_account_id: account.chart_of_account_id ?? '',
                bank_name: account.bank_name,
                bank_code: account.bank_code ?? '',
                branch_name: account.branch_name ?? '',
                account_name: account.account_name,
                account_number: '',
                account_type: account.account_type,
                currency: account.currency,
                is_cash_account: account.is_cash_account,
                status: account.status,
                opening_balance: account.opening_balance,
                opening_balance_date: account.opening_balance_date ?? '',
            };
            form.setDefaults(data);
            form.setData(data);
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bank Accounts" />
            <div className="space-y-6">
                <PageHeader
                    title="Bank Accounts"
                    description="Bank and cash account master data for treasury operations."
                    actions={
                        canCreate && (
                            <PrimaryButton onClick={create}>
                                + Create Account
                            </PrimaryButton>
                        )
                    }
                />
                <div className="space-y-6">
                    <Card
                        title="Accounts"
                        description={`${accounts.length} account(s)`}
                    >
                        <DataTable
                            data={accounts}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Account',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-semibold text-slate-900 dark:text-white">
                                                {row.account_name}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {row.bank_name} ·{' '}
                                                {row.account_number_masked}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Scope',
                                    accessor: (row) =>
                                        row.branch
                                            ? `${row.branch.code} ${row.branch.name}`
                                            : 'Organization',
                                },
                                {
                                    header: 'Opening',
                                    accessor: (row) =>
                                        money(row.opening_balance),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge
                                            variant={
                                                row.status === 'active'
                                                    ? 'success'
                                                    : 'neutral'
                                            }
                                            size="sm"
                                        >
                                            {row.status}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <SecondaryButton
                                            type="button"
                                            disabled={
                                                !canEdit || form.processing
                                            }
                                            onClick={() => edit(row)}
                                        >
                                            Edit
                                        </SecondaryButton>
                                    ),
                                },
                            ]}
                        />
                    </Card>
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
                        title={editing ? 'Edit Account' : 'Create Account'}
                        breadcrumb="BankAccounts / Editor"
                        entityLabel="Account"
                        draftLabel={form.data.account_name || 'New account'}
                        storageKey="bank-accounts-editor-size"
                        formId="bank-accounts-editor"
                        processing={form.processing}
                        saveDisabled={editing ? !canEdit : !canCreate}
                        saveLabel="Save Account"
                        status={
                            form.isDirty
                                ? 'Unsaved changes'
                                : editing
                                  ? 'No changes'
                                  : 'New account'
                        }
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <div className="space-y-6 p-4 sm:p-6">
                                <section
                                    aria-label="Scope and references"
                                    className="space-y-3"
                                >
                                    <h3 className="font-semibold">
                                        Scope and references
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorReference
                                            id="bank-branch"
                                            label="Branch Scope"
                                            value={form.data.branch_id}
                                            error={form.errors.branch_id}
                                            onChange={(value) =>
                                                form.setData('branch_id', value)
                                            }
                                            options={branches.map((b) => ({
                                                id: b.id,
                                                label: b.code + ' - ' + b.name,
                                            }))}
                                            currentLabel={
                                                editing?.branch
                                                    ? editing.branch.code +
                                                      ' - ' +
                                                      editing.branch.name
                                                    : undefined
                                            }
                                            emptyLabel="Organization"
                                        />
                                        <EditorReference
                                            id="bank-gl"
                                            label="GL Account"
                                            value={
                                                form.data.chart_of_account_id
                                            }
                                            error={
                                                form.errors.chart_of_account_id
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'chart_of_account_id',
                                                    value,
                                                )
                                            }
                                            options={chartAccounts.map((a) => ({
                                                id: a.id,
                                                label: a.code + ' - ' + a.name,
                                            }))}
                                            emptyLabel="Use default bank account"
                                            currentLabel={
                                                editing?.chart_of_account
                                                    ? `${editing.chart_of_account.code} - ${editing.chart_of_account.name}`
                                                    : undefined
                                            }
                                        />
                                    </div>
                                </section>
                                <section
                                    aria-label="Bank details"
                                    className="space-y-3"
                                >
                                    <h3 className="font-semibold">
                                        Bank details
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorField
                                            id="bank-accounts-bank_name"
                                            label="Bank Name"
                                            value={form.data.bank_name}
                                            error={form.errors.bank_name}
                                            onChange={(value) =>
                                                form.setData('bank_name', value)
                                            }
                                            required
                                        />
                                        <EditorField
                                            id="bank-accounts-bank_code"
                                            label="Bank Code"
                                            value={form.data.bank_code}
                                            error={form.errors.bank_code}
                                            onChange={(value) =>
                                                form.setData('bank_code', value)
                                            }
                                        />
                                        <EditorField
                                            id="bank-accounts-branch_name"
                                            label="Bank Branch"
                                            value={form.data.branch_name}
                                            error={form.errors.branch_name}
                                            onChange={(value) =>
                                                form.setData(
                                                    'branch_name',
                                                    value,
                                                )
                                            }
                                        />
                                        <EditorField
                                            id="bank-accounts-account_name"
                                            label="Account Name"
                                            value={form.data.account_name}
                                            error={form.errors.account_name}
                                            onChange={(value) =>
                                                form.setData(
                                                    'account_name',
                                                    value,
                                                )
                                            }
                                            required
                                        />
                                        <EditorField
                                            id="bank-accounts-account_number"
                                            label="Account Number"
                                            value={form.data.account_number}
                                            error={form.errors.account_number}
                                            onChange={(value) =>
                                                form.setData(
                                                    'account_number',
                                                    value,
                                                )
                                            }
                                            required={!editing}
                                        />
                                        <EditorChoice
                                            id="bank-accounts-account_type"
                                            label="Account Type"
                                            value={form.data.account_type}
                                            error={form.errors.account_type}
                                            onChange={(value) =>
                                                form.setData(
                                                    'account_type',
                                                    value,
                                                )
                                            }
                                            options={accountTypes}
                                        />
                                    </div>
                                </section>
                                <p className="text-sm">
                                    {editing
                                        ? 'Current number: ' +
                                          editing.account_number_masked +
                                          '. Leave Account Number blank to keep it.'
                                        : 'Account number is encrypted at rest.'}
                                </p>
                                <section
                                    aria-label="Opening balance and status"
                                    className="space-y-3"
                                >
                                    <h3 className="font-semibold">
                                        Opening balance and status
                                    </h3>
                                    <div
                                        className={
                                            expanded
                                                ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4'
                                                : 'grid gap-4 sm:grid-cols-2'
                                        }
                                    >
                                        <EditorField
                                            id="bank-accounts-currency"
                                            label="Currency"
                                            value={form.data.currency}
                                            error={form.errors.currency}
                                            onChange={(value) =>
                                                form.setData('currency', value)
                                            }
                                            required
                                        />
                                        <EditorField
                                            id="bank-accounts-opening_balance"
                                            label="Opening Balance"
                                            value={form.data.opening_balance}
                                            error={form.errors.opening_balance}
                                            onChange={(value) =>
                                                form.setData(
                                                    'opening_balance',
                                                    value,
                                                )
                                            }
                                            type="number"
                                            step="0.01"
                                            required
                                        />
                                        <EditorField
                                            id="bank-accounts-opening_balance_date"
                                            label="Opening Balance Date"
                                            value={
                                                form.data.opening_balance_date
                                            }
                                            error={
                                                form.errors.opening_balance_date
                                            }
                                            onChange={(value) =>
                                                form.setData(
                                                    'opening_balance_date',
                                                    value,
                                                )
                                            }
                                            type="date"
                                        />
                                        <EditorChoice
                                            id="bank-accounts-status"
                                            label="Status"
                                            value={form.data.status}
                                            error={form.errors.status}
                                            onChange={(value) =>
                                                form.setData('status', value)
                                            }
                                            options={statuses}
                                        />
                                        <label className="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                checked={
                                                    form.data.is_cash_account
                                                }
                                                onChange={(e) =>
                                                    form.setData(
                                                        'is_cash_account',
                                                        e.target.checked,
                                                    )
                                                }
                                            />
                                            Cash account
                                        </label>
                                    </div>
                                </section>
                            </div>
                        )}
                    </CreateEditDialog>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
