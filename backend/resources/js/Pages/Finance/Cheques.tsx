import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

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

function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    router.post(
        route('cheques.store'),
        Object.fromEntries(new FormData(event.currentTarget)),
    );
}

export default function Cheques({
    cheques,
    accounts,
    statementLines,
}: {
    cheques: Cheque[];
    accounts: Account[];
    statementLines: StatementLine[];
}) {
    function transition(
        cheque: Cheque,
        status: 'deposited' | 'cleared' | 'bounced' | 'cancelled',
    ) {
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
                    description="Register, deposit, clear, bounce, or cancel issued and received cheques."
                />
                <div className="grid gap-6 xl:grid-cols-[380px_minmax(0,1fr)]">
                    <Card title="Register Cheque">
                        <form className="space-y-3" onSubmit={submit}>
                            <select
                                name="bank_account_id"
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Bank account</option>
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.bank_name} -{' '}
                                        {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="direction"
                                required
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="received">Received</option>
                                <option value="issued">Issued</option>
                            </select>
                            <input
                                name="cheque_no"
                                required
                                placeholder="Cheque number"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="bank_name"
                                required
                                placeholder="Bank"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="drawer_or_payee"
                                required
                                placeholder="Drawer / payee"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="amount"
                                required
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="Amount"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="issue_date"
                                required
                                type="date"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="due_date"
                                required
                                type="date"
                                className="w-full rounded-md border-slate-300"
                            />
                            <PrimaryButton>Register</PrimaryButton>
                        </form>
                    </Card>

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
