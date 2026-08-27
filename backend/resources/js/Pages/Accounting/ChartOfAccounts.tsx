import PrimaryButton from '@/Components/PrimaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

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
    return (
        <AuthenticatedLayout>
            <Head title="Chart of Accounts" />
            <div className="space-y-6">
                <PageHeader
                    title="Chart of Accounts"
                    description="Manage organization accounts used by immutable journal postings."
                />
                <div className="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
                    <Card title="New Account">
                        <form
                            className="space-y-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                router.post(
                                    route('accounting.chart-of-accounts.store'),
                                    Object.fromEntries(
                                        new FormData(event.currentTarget),
                                    ),
                                );
                            }}
                        >
                            <input
                                name="code"
                                required
                                placeholder="Account code"
                                className="w-full rounded-md border-slate-300"
                            />
                            <input
                                name="name"
                                required
                                placeholder="Account name"
                                className="w-full rounded-md border-slate-300"
                            />
                            <select
                                name="account_type"
                                required
                                className="w-full rounded-md border-slate-300"
                            >
                                <option value="">Type</option>
                                {types.map((type) => (
                                    <option key={type} value={type}>
                                        {type}
                                    </option>
                                ))}
                            </select>
                            <input type="hidden" name="is_postable" value="1" />
                            <PrimaryButton>Create Account</PrimaryButton>
                        </form>
                    </Card>
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
