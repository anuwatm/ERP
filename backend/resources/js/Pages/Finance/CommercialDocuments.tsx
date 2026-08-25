import SecondaryButton from '@/Components/SecondaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head } from '@inertiajs/react';

type Row = Record<string, string | number | null | undefined> & { id: string };

export default function CommercialDocuments({
    creditDebitNotes,
    billingNotes,
    deliveryOrders,
    purchaseRequests,
    vouchers,
}: {
    creditDebitNotes: Row[];
    billingNotes: Row[];
    deliveryOrders: Row[];
    purchaseRequests: Row[];
    vouchers: Row[];
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Commercial Documents" />
            <div className="space-y-6">
                <PageHeader
                    title="Commercial Documents"
                    description="CN/DN, billing notes, delivery orders, purchase requests, and vouchers."
                />
                <div className="grid gap-6 xl:grid-cols-2">
                    <SimpleTable
                        title="Credit / Debit Notes"
                        rows={creditDebitNotes}
                        numberKey="note_no"
                        amountKey="total"
                        statusKey="status"
                        printType="credit-debit-note"
                    />
                    <SimpleTable
                        title="Billing Notes"
                        rows={billingNotes}
                        numberKey="billing_no"
                        amountKey="total"
                        statusKey="status"
                        printType="billing-note"
                    />
                    <SimpleTable
                        title="Delivery Orders"
                        rows={deliveryOrders}
                        numberKey="do_no"
                        amountKey={null}
                        statusKey="status"
                        printType="delivery-order"
                    />
                    <SimpleTable
                        title="Purchase Requests"
                        rows={purchaseRequests}
                        numberKey="pr_no"
                        amountKey="total"
                        statusKey="status"
                        printType="purchase-request"
                    />
                    <SimpleTable
                        title="PV / RV Vouchers"
                        rows={vouchers}
                        numberKey="voucher_no"
                        amountKey="amount"
                        statusKey="status"
                        printType="voucher"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function SimpleTable({
    title,
    rows,
    numberKey,
    amountKey,
    statusKey,
    printType,
}: {
    title: string;
    rows: Row[];
    numberKey: string;
    amountKey: string | null;
    statusKey: string;
    printType: string;
}) {
    return (
        <Card title={title} description={`${rows.length} document(s)`}>
            <DataTable
                data={rows}
                keyExtractor={(row) => row.id}
                columns={[
                    {
                        header: 'No.',
                        accessor: (row) => (
                            <span className="font-mono font-semibold">
                                {String(row[numberKey] ?? '-')}
                            </span>
                        ),
                    },
                    {
                        header: 'Status',
                        accessor: (row) => String(row[statusKey] ?? '-'),
                    },
                    {
                        header: 'Amount',
                        accessor: (row) =>
                            amountKey ? money(row[amountKey] ?? 0) : '-',
                    },
                    {
                        header: 'Actions',
                        accessor: (row) => (
                            <div className="flex flex-wrap gap-2">
                                <SecondaryButton
                                    type="button"
                                    onClick={() =>
                                        window.open(
                                            route(
                                                'commercial-documents.print',
                                                [printType, row.id],
                                            ),
                                            '_blank',
                                            'noopener,noreferrer',
                                        )
                                    }
                                >
                                    Print
                                </SecondaryButton>
                                <SecondaryButton
                                    type="button"
                                    onClick={() =>
                                        window.open(
                                            route('commercial-documents.pdf', [
                                                printType,
                                                row.id,
                                            ]),
                                            '_blank',
                                            'noopener,noreferrer',
                                        )
                                    }
                                >
                                    PDF
                                </SecondaryButton>
                            </div>
                        ),
                    },
                ]}
            />
        </Card>
    );
}
