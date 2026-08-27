import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type Account = {
    id: string;
    name: string;
    currency: string;
    opening_balance: number;
    net_receipts: number;
    paid_expenses: number;
    expected_balance: number;
    unreconciled_count: number;
    unreconciled_amount: number;
    pending_cheque_count: number;
    pending_cheque_amount: number;
};

type Summary = {
    active_funds?: number;
    imprest_total?: number;
    paid_requests?: number;
    reimbursements?: number;
    pending_count?: number;
    pending_amount?: number;
};

const money = (amount: number, currency = 'THB') =>
    new Intl.NumberFormat('th-TH', { style: 'currency', currency }).format(
        amount,
    );

export default function TreasuryReports({
    accounts,
    pettyCash,
    cheques,
}: {
    accounts: Account[];
    pettyCash: Summary;
    cheques: Summary;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Treasury Reports" />
            <div className="space-y-6">
                <PageHeader
                    title="Treasury Reports"
                    description="Cash position, unreconciled statement activity, petty cash, and pending cheques."
                />
                <div className="grid gap-4 md:grid-cols-2">
                    <Card title="Petty Cash">
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <dt>Active funds</dt>
                            <dd>{pettyCash.active_funds ?? 0}</dd>
                            <dt>Imprest total</dt>
                            <dd>{money(pettyCash.imprest_total ?? 0)}</dd>
                            <dt>Paid requests</dt>
                            <dd>{money(pettyCash.paid_requests ?? 0)}</dd>
                            <dt>Reimbursements</dt>
                            <dd>{money(pettyCash.reimbursements ?? 0)}</dd>
                        </dl>
                    </Card>
                    <Card title="Pending Cheques">
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <dt>Items</dt>
                            <dd>{cheques.pending_count ?? 0}</dd>
                            <dt>Amount</dt>
                            <dd>{money(cheques.pending_amount ?? 0)}</dd>
                        </dl>
                    </Card>
                </div>
                <Card title="Bank Position">
                    <DataTable
                        data={accounts}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'Account',
                                accessor: (item) => item.name,
                            },
                            {
                                header: 'Opening',
                                accessor: (item) =>
                                    money(item.opening_balance, item.currency),
                            },
                            {
                                header: 'Net receipts',
                                accessor: (item) =>
                                    money(item.net_receipts, item.currency),
                            },
                            {
                                header: 'Paid expenses',
                                accessor: (item) =>
                                    money(item.paid_expenses, item.currency),
                            },
                            {
                                header: 'Expected',
                                accessor: (item) =>
                                    money(item.expected_balance, item.currency),
                            },
                            {
                                header: 'Unreconciled',
                                accessor: (item) =>
                                    `${item.unreconciled_count} / ${money(item.unreconciled_amount, item.currency)}`,
                            },
                            {
                                header: 'Pending cheques',
                                accessor: (item) =>
                                    `${item.pending_cheque_count} / ${money(item.pending_cheque_amount, item.currency)}`,
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
