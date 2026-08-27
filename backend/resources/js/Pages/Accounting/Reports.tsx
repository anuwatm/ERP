import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type TrialBalance = {
    id: string;
    debit: string;
    credit: string;
    account: { code: string; name: string; account_type: string };
};
type LedgerLine = {
    id: string;
    debit: string;
    credit: string;
    account: { code: string; name: string };
    entry: { entry_no: string; posting_date: string; description: string };
};

export default function Reports({
    trialBalance,
    ledger,
}: {
    trialBalance: TrialBalance[];
    ledger: LedgerLine[];
    accounts: Array<{ id: string; code: string; name: string }>;
    filters: Record<string, string>;
}) {
    return (
        <AuthenticatedLayout>
            <Head title="General Ledger Reports" />
            <div className="space-y-6">
                <PageHeader
                    title="General Ledger Reports"
                    description="Trial balance and posted account ledger activity."
                />
                <Card title="Trial Balance">
                    <DataTable
                        data={trialBalance}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'Code',
                                accessor: (item) => item.account.code,
                            },
                            {
                                header: 'Account',
                                accessor: (item) => item.account.name,
                            },
                            { header: 'Debit', accessor: (item) => item.debit },
                            {
                                header: 'Credit',
                                accessor: (item) => item.credit,
                            },
                        ]}
                    />
                </Card>
                <Card title="Account Ledger">
                    <DataTable
                        data={ledger}
                        keyExtractor={(item) => item.id}
                        columns={[
                            {
                                header: 'Date',
                                accessor: (item) => item.entry.posting_date,
                            },
                            {
                                header: 'Entry',
                                accessor: (item) => item.entry.entry_no,
                            },
                            {
                                header: 'Account',
                                accessor: (item) =>
                                    `${item.account.code} ${item.account.name}`,
                            },
                            {
                                header: 'Description',
                                accessor: (item) => item.entry.description,
                            },
                            { header: 'Debit', accessor: (item) => item.debit },
                            {
                                header: 'Credit',
                                accessor: (item) => item.credit,
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
