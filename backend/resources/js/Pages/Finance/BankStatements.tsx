import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

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
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('bank-statements.import'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => form.reset('statement'),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Bank Statements" />
            <div className="space-y-6">
                <PageHeader
                    title="Bank Statements"
                    description="Import CSV statements and prepare unreconciled lines."
                />
                <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                    <Card
                        title="Import Statement"
                        description="CSV columns: transaction_date, amount, description, reference_no, balance_after"
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <div>
                                <InputLabel value="Bank Account" />
                                <select
                                    required
                                    className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                    value={form.data.bank_account_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'bank_account_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Select account</option>
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.bank_name} -{' '}
                                            {account.account_name}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <InputLabel value="CSV File" />
                                <input
                                    required
                                    type="file"
                                    accept=".csv,text/csv"
                                    className="mt-1 block w-full text-sm"
                                    onChange={(event) =>
                                        form.setData(
                                            'statement',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                            </div>
                            <PrimaryButton disabled={form.processing}>
                                Import CSV
                            </PrimaryButton>
                        </form>
                    </Card>
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
