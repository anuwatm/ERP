import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import DeliveryOrderDialog from './DeliveryOrderDialog';
import VoucherAttachmentDialog from './VoucherAttachmentDialog';

type Row = Record<string, string | number | null | undefined> & { id: string };
type Invoice = {
    id: string;
    invoice_no: string;
    items: Array<{
        product_id: string | null;
        product?: {
            barcode?: string | null;
            sku?: string | null;
            name: string;
        } | null;
    }>;
};
type Warehouse = { id: string; code: string; name: string };

export default function CommercialDocuments({
    creditDebitNotes,
    billingNotes,
    deliveryOrders,
    purchaseRequests,
    vouchers,
    invoices,
    warehouses,
}: {
    creditDebitNotes: Row[];
    billingNotes: Row[];
    deliveryOrders: Row[];
    purchaseRequests: Row[];
    vouchers: Row[];
    invoices: Invoice[];
    warehouses: Warehouse[];
}) {
    const [createDoOpen, setCreateDoOpen] = useState(false);
    const [managingVoucher, setManagingVoucher] = useState<Row | null>(null);

    return (
        <AuthenticatedLayout>
            <Head title="Commercial Documents" />
            <div className="space-y-6">
                <PageHeader
                    title="Commercial Documents"
                    description="CN/DN, billing notes, delivery orders, purchase requests, and vouchers."
                    actions={
                        <PrimaryButton
                            type="button"
                            onClick={() => setCreateDoOpen(true)}
                        >
                            + Create Delivery Order
                        </PrimaryButton>
                    }
                />

                <DeliveryOrderDialog
                    open={createDoOpen}
                    invoices={invoices}
                    warehouses={warehouses}
                    onClose={() => setCreateDoOpen(false)}
                />

                {managingVoucher && (
                    <VoucherAttachmentDialog
                        voucher={managingVoucher}
                        onClose={() => setManagingVoucher(null)}
                    />
                )}

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
                        isPurchaseRequest
                    />
                    <SimpleTable
                        title="PV / RV Vouchers"
                        rows={vouchers}
                        numberKey="voucher_no"
                        amountKey="amount"
                        statusKey="status"
                        printType="voucher"
                        isVoucher
                        onManageProof={(voucher) => setManagingVoucher(voucher)}
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
    isVoucher = false,
    isPurchaseRequest = false,
    onManageProof,
}: {
    title: string;
    rows: Row[];
    numberKey: string;
    amountKey: string | null;
    statusKey: string;
    printType: string;
    isVoucher?: boolean;
    isPurchaseRequest?: boolean;
    onManageProof?: (row: Row) => void;
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
                                {isPurchaseRequest &&
                                    row.status === 'draft' && (
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                router.post(
                                                    route('workflows.submit', [
                                                        'purchase_request',
                                                        row.id,
                                                    ]),
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Submit Approval
                                        </SecondaryButton>
                                    )}
                                {isVoucher && onManageProof && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => onManageProof(row)}
                                    >
                                        {row.attachment
                                            ? 'Proof (1)'
                                            : 'Upload Proof'}
                                    </SecondaryButton>
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </Card>
    );
}
