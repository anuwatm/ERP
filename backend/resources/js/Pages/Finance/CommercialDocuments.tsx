import SecondaryButton from '@/Components/SecondaryButton';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

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
    return (
        <AuthenticatedLayout>
            <Head title="Commercial Documents" />
            <div className="space-y-6">
                <PageHeader
                    title="Commercial Documents"
                    description="CN/DN, billing notes, delivery orders, purchase requests, and vouchers."
                />
                <DeliveryOrderForm
                    invoices={invoices}
                    warehouses={warehouses}
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
                        isVoucher
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function DeliveryOrderForm({
    invoices,
    warehouses,
}: {
    invoices: Invoice[];
    warehouses: Warehouse[];
}) {
    const form = useForm({
        invoice_id: '',
        warehouse_id: '',
        delivery_date: new Date().toISOString().slice(0, 10),
        status: 'draft',
        receiver_name: '',
        note: '',
    });
    const [barcode, setBarcode] = useState('');
    const [scanResult, setScanResult] = useState('');
    const selectedInvoice = invoices.find(
        (invoice) => invoice.id === form.data.invoice_id,
    );
    const scan = () => {
        const product = selectedInvoice?.items.find(
            (item) =>
                item.product?.barcode === barcode ||
                item.product?.sku === barcode,
        )?.product;
        setScanResult(
            product
                ? `${product.name} matches the selected invoice.`
                : 'Barcode / SKU does not match the selected invoice.',
        );
    };
    return (
        <Card title="Create Delivery Order">
            <form
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post(route('delivery-orders.store'), {
                        preserveScroll: true,
                        onSuccess: () =>
                            form.reset(
                                'invoice_id',
                                'warehouse_id',
                                'receiver_name',
                                'note',
                            ),
                    });
                }}
                className="grid gap-3 lg:grid-cols-3"
            >
                <select
                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    value={form.data.invoice_id}
                    onChange={(event) =>
                        form.setData('invoice_id', event.target.value)
                    }
                    required
                >
                    <option value="">Invoice</option>
                    {invoices.map((invoice) => (
                        <option key={invoice.id} value={invoice.id}>
                            {invoice.invoice_no}
                        </option>
                    ))}
                </select>
                <select
                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    value={form.data.warehouse_id}
                    onChange={(event) =>
                        form.setData('warehouse_id', event.target.value)
                    }
                >
                    <option value="">All warehouse stock</option>
                    {warehouses.map((warehouse) => (
                        <option key={warehouse.id} value={warehouse.id}>
                            {warehouse.code} - {warehouse.name}
                        </option>
                    ))}
                </select>
                <select
                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                    value={form.data.status}
                    onChange={(event) =>
                        form.setData(
                            'status',
                            event.target.value as 'draft' | 'delivered',
                        )
                    }
                >
                    <option value="draft">Draft</option>
                    <option value="delivered">Delivered</option>
                </select>
                <TextInput
                    type="date"
                    value={form.data.delivery_date}
                    onChange={(event) =>
                        form.setData('delivery_date', event.target.value)
                    }
                    required
                />
                <TextInput
                    value={form.data.receiver_name}
                    onChange={(event) =>
                        form.setData('receiver_name', event.target.value)
                    }
                    placeholder="Receiver name"
                />
                <div className="flex gap-2">
                    <TextInput
                        value={barcode}
                        onChange={(event) => setBarcode(event.target.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                scan();
                            }
                        }}
                        placeholder="Scan barcode / SKU"
                        className="w-full"
                    />
                    <SecondaryButton type="button" onClick={scan}>
                        Scan
                    </SecondaryButton>
                </div>
                <textarea
                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900 lg:col-span-2"
                    value={form.data.note}
                    onChange={(event) =>
                        form.setData('note', event.target.value)
                    }
                    placeholder="Note"
                />
                <div>
                    <PrimaryButton
                        disabled={form.processing || !form.data.invoice_id}
                    >
                        Create DO
                    </PrimaryButton>
                </div>
                {scanResult && (
                    <p className="text-sm text-slate-600 dark:text-slate-300 lg:col-span-3">
                        {scanResult}
                    </p>
                )}
            </form>
        </Card>
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
}: {
    title: string;
    rows: Row[];
    numberKey: string;
    amountKey: string | null;
    statusKey: string;
    printType: string;
    isVoucher?: boolean;
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
                                {isVoucher && (
                                    <VoucherAttachmentActions row={row} />
                                )}
                            </div>
                        ),
                    },
                ]}
            />
        </Card>
    );
}

function VoucherAttachmentActions({ row }: { row: Row }) {
    const form = useForm<{ attachment: File | null }>({ attachment: null });
    const attachment = row.attachment as
        { id: string; file_name: string } | null | undefined;

    return (
        <div className="flex flex-wrap items-center gap-2">
            <label className="cursor-pointer rounded-xl border border-slate-300 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-700 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                {form.processing
                    ? 'Uploading'
                    : attachment
                      ? 'Replace Proof'
                      : 'Upload Proof'}
                <input
                    className="sr-only"
                    type="file"
                    accept=".pdf,.jpg,.jpeg,.png,.webp"
                    disabled={form.processing}
                    onChange={(event) => {
                        const file = event.target.files?.[0];
                        if (!file) return;

                        form.setData('attachment', file);
                        form.post(route('vouchers.attachment.store', row.id), {
                            forceFormData: true,
                            preserveScroll: true,
                            onFinish: () => form.reset('attachment'),
                        });
                    }}
                />
            </label>
            {attachment && (
                <a
                    className="text-xs font-semibold text-indigo-600 hover:text-indigo-500 dark:text-indigo-300"
                    href={route('files.download', attachment.id)}
                >
                    {attachment.file_name}
                </a>
            )}
        </div>
    );
}
