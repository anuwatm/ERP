import Badge from '@/Components/UI/Badge';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head } from '@inertiajs/react';

type Payment = {
    id: string;
    entry_type: 'receipt' | 'reversal';
    amount: string;
    currency: string;
    payment_date: string;
    reference_no?: string | null;
    invoice?: {
        invoice_no: string;
        customer?: { company_name: string } | null;
    } | null;
};

export default function Payments({
    payments,
}: {
    payments: { data: Payment[] };
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Payments" />
            <div className="space-y-6">
                <PageHeader
                    title="Payments"
                    description="Receipts and reversals in document currency."
                />
                <DataTable
                    data={payments.data}
                    keyExtractor={(payment) => payment.id}
                    emptyMessage="No payments match this filter."
                    columns={[
                        {
                            header: 'Date',
                            accessor: (payment) => payment.payment_date,
                        },
                        {
                            header: 'Invoice',
                            accessor: (payment) =>
                                payment.invoice?.invoice_no ?? '—',
                        },
                        {
                            header: 'Customer',
                            accessor: (payment) =>
                                payment.invoice?.customer?.company_name ?? '—',
                        },
                        {
                            header: 'Type',
                            accessor: (payment) => (
                                <Badge
                                    variant={
                                        payment.entry_type === 'receipt'
                                            ? 'success'
                                            : 'danger'
                                    }
                                    size="sm"
                                >
                                    {payment.entry_type}
                                </Badge>
                            ),
                        },
                        {
                            header: 'Amount',
                            accessor: (payment) =>
                                `${payment.currency} ${money(payment.amount)}`,
                        },
                        {
                            header: 'Reference',
                            accessor: (payment) => payment.reference_no ?? '—',
                        },
                    ]}
                />
            </div>
        </AuthenticatedLayout>
    );
}
