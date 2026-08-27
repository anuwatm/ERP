import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

type Fund = { id: string; fund_no: string; status: string };
type Account = { id: string; bank_name: string; account_name: string };
type User = { id: string; name: string };
type CashRequest = {
    id: string;
    request_no: string;
    amount: string;
    purpose: string;
    status: string;
};
type Reimbursement = {
    id: string;
    amount: string;
    reimbursed_at: string;
    petty_cash_fund_id: string;
};

function submit(event: FormEvent<HTMLFormElement>, url: string) {
    event.preventDefault();
    router.post(url, Object.fromEntries(new FormData(event.currentTarget)));
}

export default function PettyCash({
    funds,
    requests,
    reimbursements,
    accounts,
    users,
}: {
    funds: Fund[];
    requests: CashRequest[];
    reimbursements: Reimbursement[];
    accounts: Account[];
    users: User[];
}) {
    const activeFunds = funds.filter((fund) => fund.status === 'active');

    return (
        <AuthenticatedLayout>
            <Head title="Petty Cash" />
            <div className="space-y-6">
                <PageHeader
                    title="Petty Cash"
                    description="Manage funds, requests, approvals, and replenishments."
                />

                <div className="grid gap-6 xl:grid-cols-3">
                    <Card title="New Fund">
                        <form
                            className="space-y-3"
                            onSubmit={(event) =>
                                submit(event, route('petty-cash.funds.store'))
                            }
                        >
                            <select
                                name="custodian_user_id"
                                required
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Custodian</option>
                                {users.map((user) => (
                                    <option key={user.id} value={user.id}>
                                        {user.name}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="bank_account_id"
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Funding account</option>
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.bank_name} -{' '}
                                        {account.account_name}
                                    </option>
                                ))}
                            </select>
                            <input
                                name="imprest_amount"
                                required
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="Imprest amount"
                                className="w-full rounded-md border-slate-300"
                            />
                            <PrimaryButton>Create Fund</PrimaryButton>
                        </form>
                    </Card>

                    <Card title="New Request">
                        <form
                            className="space-y-3"
                            onSubmit={(event) =>
                                submit(
                                    event,
                                    route('petty-cash.requests.store'),
                                )
                            }
                        >
                            <select
                                name="petty_cash_fund_id"
                                required
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Fund</option>
                                {activeFunds.map((fund) => (
                                    <option key={fund.id} value={fund.id}>
                                        {fund.fund_no}
                                    </option>
                                ))}
                            </select>
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
                                name="expense_date"
                                required
                                type="date"
                                className="w-full rounded-md border-slate-300"
                            />
                            <textarea
                                name="purpose"
                                required
                                placeholder="Purpose"
                                className="w-full rounded-md border-slate-300"
                            />
                            <PrimaryButton>Submit Request</PrimaryButton>
                        </form>
                    </Card>

                    <Card title="Reimburse Fund">
                        <form
                            className="space-y-3"
                            onSubmit={(event) =>
                                submit(
                                    event,
                                    route('petty-cash.reimbursements.store'),
                                )
                            }
                        >
                            <select
                                name="petty_cash_fund_id"
                                required
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Fund</option>
                                {activeFunds.map((fund) => (
                                    <option key={fund.id} value={fund.id}>
                                        {fund.fund_no}
                                    </option>
                                ))}
                            </select>
                            <select
                                name="bank_account_id"
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Source account</option>
                                {accounts.map((account) => (
                                    <option key={account.id} value={account.id}>
                                        {account.bank_name} -{' '}
                                        {account.account_name}
                                    </option>
                                ))}
                            </select>
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
                                name="reimbursed_at"
                                required
                                type="date"
                                className="w-full rounded-md border-slate-300"
                            />
                            <textarea
                                name="note"
                                placeholder="Note"
                                className="w-full rounded-md border-slate-300"
                            />
                            <PrimaryButton>Record Reimbursement</PrimaryButton>
                        </form>
                    </Card>
                </div>

                <Card title="Requests">
                    <DataTable
                        data={requests}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'No.',
                                accessor: (item) => item.request_no,
                            },
                            {
                                header: 'Purpose',
                                accessor: (item) => item.purpose,
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
                                    <div className="flex gap-2">
                                        {item.status === 'submitted' && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'petty-cash.requests.approve',
                                                            item.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Approve
                                            </button>
                                        )}
                                        {item.status === 'submitted' && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'petty-cash.requests.reject',
                                                            item.id,
                                                        ),
                                                        {
                                                            reason: 'Rejected by approver',
                                                        },
                                                    )
                                                }
                                            >
                                                Reject
                                            </button>
                                        )}
                                        {item.status === 'approved' && (
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    router.post(
                                                        route(
                                                            'petty-cash.requests.pay',
                                                            item.id,
                                                        ),
                                                    )
                                                }
                                            >
                                                Pay
                                            </button>
                                        )}
                                    </div>
                                ),
                            },
                        ]}
                    />
                </Card>

                <Card title="Recent Reimbursements">
                    <DataTable
                        data={reimbursements}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'Fund',
                                accessor: (item) =>
                                    funds.find(
                                        (fund) =>
                                            fund.id === item.petty_cash_fund_id,
                                    )?.fund_no ?? '-',
                            },
                            {
                                header: 'Date',
                                accessor: (item) => item.reimbursed_at,
                            },
                            {
                                header: 'Amount',
                                accessor: (item) => item.amount,
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
