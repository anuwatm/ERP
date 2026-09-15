import PettyCashDialog, { PettyCashMode } from './PettyCashDialog';
import { PageProps } from '@/Types';
import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Fund = { id: string; fund_no: string; status: string };
type Account = { id: string; bank_name: string; account_name: string };
type User = { id: string; name: string };
type CashRequest = {
    id: string;
    request_no: string;
    requester_id: string;
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
    const [editor, setEditor] = useState<PettyCashMode | null>(null);
    const { auth } = usePage<PageProps>().props;
    const canManage = auth.permissions.includes('petty_cash.manage');
    const canApprove = auth.permissions.includes('petty_cash.approve');

    return (
        <AuthenticatedLayout>
            <Head title="Petty Cash" />
            <div className="space-y-6">
                <PageHeader
                    title="Petty Cash"
                    actions={
                        canManage && (
                            <div className="flex flex-wrap gap-2">
                                <PrimaryButton
                                    disabled={editor !== null}
                                    onClick={() => setEditor('fund')}
                                >
                                    + New Fund
                                </PrimaryButton>
                                <PrimaryButton
                                    disabled={editor !== null}
                                    onClick={() => setEditor('request')}
                                >
                                    + New Request
                                </PrimaryButton>
                                <PrimaryButton
                                    disabled={editor !== null}
                                    onClick={() => setEditor('reimbursement')}
                                >
                                    Reimburse Fund
                                </PrimaryButton>
                            </div>
                        )
                    }
                    description="Manage funds, requests, approvals, and replenishments."
                />

                {editor && (
                    <PettyCashDialog
                        key={editor}
                        mode={editor}
                        funds={funds}
                        accounts={accounts}
                        users={users}
                        onClose={() => setEditor(null)}
                    />
                )}

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
                                        {canApprove &&
                                            item.requester_id !==
                                                auth.user.id &&
                                            item.status === 'submitted' && (
                                                <button
                                                    type="button"
                                                    disabled={editor !== null}
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
                                        {canApprove &&
                                            item.requester_id !==
                                                auth.user.id &&
                                            item.status === 'submitted' && (
                                                <button
                                                    type="button"
                                                    disabled={editor !== null}
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
                                        {canManage &&
                                            item.status === 'approved' && (
                                                <button
                                                    type="button"
                                                    disabled={editor !== null}
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
