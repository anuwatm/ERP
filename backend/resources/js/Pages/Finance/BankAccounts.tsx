import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, ReactNode, useState } from 'react';

type Branch = { id: string; code: string; name: string };
type Account = {
    id: string;
    branch_id: string | null;
    branch: Branch | null;
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
    accountTypes,
    statuses,
}: {
    accounts: Account[];
    branches: Branch[];
    accountTypes: string[];
    statuses: string[];
}) {
    const [editing, setEditing] = useState<Account | null>(null);
    const form = useForm<AccountForm>(emptyForm);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptyForm);
                setEditing(null);
            },
        };

        if (editing) {
            form.patch(route('bank-accounts.update', editing.id), options);
        } else {
            form.post(route('bank-accounts.store'), options);
        }
    };

    const edit = (account: Account) => {
        setEditing(account);
        form.setData({
            branch_id: account.branch_id ?? '',
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
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bank Accounts" />
            <div className="space-y-6">
                <PageHeader
                    title="Bank Accounts"
                    description="Bank and cash account master data for treasury operations."
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
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
                                            onClick={() => edit(row)}
                                        >
                                            Edit
                                        </SecondaryButton>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                    <Card
                        title={editing ? 'Edit Account' : 'New Account'}
                        description={
                            editing
                                ? `Current number: ${editing.account_number_masked}`
                                : 'Account number is encrypted at rest.'
                        }
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <SelectField
                                label="Branch Scope"
                                value={form.data.branch_id}
                                onChange={(value) =>
                                    form.setData('branch_id', value)
                                }
                            >
                                <option value="">Organization</option>
                                {branches.map((branch) => (
                                    <option key={branch.id} value={branch.id}>
                                        {branch.code} - {branch.name}
                                    </option>
                                ))}
                            </SelectField>
                            <Field
                                label="Bank Name"
                                value={form.data.bank_name}
                                onChange={(value) =>
                                    form.setData('bank_name', value)
                                }
                                required
                            />
                            <Field
                                label="Bank Code"
                                value={form.data.bank_code}
                                onChange={(value) =>
                                    form.setData('bank_code', value)
                                }
                            />
                            <Field
                                label="Bank Branch"
                                value={form.data.branch_name}
                                onChange={(value) =>
                                    form.setData('branch_name', value)
                                }
                            />
                            <Field
                                label="Account Name"
                                value={form.data.account_name}
                                onChange={(value) =>
                                    form.setData('account_name', value)
                                }
                                required
                            />
                            <Field
                                label="Account Number"
                                value={form.data.account_number}
                                onChange={(value) =>
                                    form.setData('account_number', value)
                                }
                                required={!editing}
                                placeholder={
                                    editing
                                        ? 'Leave blank to keep current number'
                                        : ''
                                }
                            />
                            <SelectField
                                label="Account Type"
                                value={form.data.account_type}
                                onChange={(value) =>
                                    form.setData('account_type', value)
                                }
                            >
                                {accountTypes.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </SelectField>
                            <Field
                                label="Currency"
                                value={form.data.currency}
                                onChange={(value) =>
                                    form.setData(
                                        'currency',
                                        value.toUpperCase(),
                                    )
                                }
                                required
                            />
                            <Field
                                label="Opening Balance"
                                type="number"
                                value={form.data.opening_balance}
                                onChange={(value) =>
                                    form.setData('opening_balance', value)
                                }
                                required
                            />
                            <Field
                                label="Opening Balance Date"
                                type="date"
                                value={form.data.opening_balance_date}
                                onChange={(value) =>
                                    form.setData('opening_balance_date', value)
                                }
                            />
                            <SelectField
                                label="Status"
                                value={form.data.status}
                                onChange={(value) =>
                                    form.setData('status', value)
                                }
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </SelectField>
                            <label className="flex items-center gap-2 text-sm font-medium text-slate-700 dark:text-slate-200">
                                <input
                                    type="checkbox"
                                    checked={form.data.is_cash_account}
                                    onChange={(event) =>
                                        form.setData(
                                            'is_cash_account',
                                            event.target.checked,
                                        )
                                    }
                                />
                                Cash account
                            </label>
                            <div className="flex justify-end gap-2">
                                {editing && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => {
                                            setEditing(null);
                                            form.setData(emptyForm);
                                        }}
                                    >
                                        Cancel
                                    </SecondaryButton>
                                )}
                                <PrimaryButton disabled={form.processing}>
                                    Save Account
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Field({
    label,
    value,
    onChange,
    type = 'text',
    required = false,
    placeholder = '',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    type?: string;
    required?: boolean;
    placeholder?: string;
}) {
    return (
        <div>
            <InputLabel value={label} />
            <TextInput
                className="mt-1 block w-full"
                type={type}
                value={value}
                required={required}
                placeholder={placeholder}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function SelectField({
    label,
    value,
    onChange,
    children,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    children: ReactNode;
}) {
    return (
        <div>
            <InputLabel value={label} />
            <select
                className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                value={value}
                onChange={(event) => onChange(event.target.value)}
            >
                {children}
            </select>
        </div>
    );
}
