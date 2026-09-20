import Badge from '@/Components/UI/Badge';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, Link } from '@inertiajs/react';

type VendorPayment = {
    id: string;
    entry_type: 'payment' | 'reversal';
    payment_date: string;
    amount: string;
    currency: string;
    base_amount: string;
    base_currency: string;
    expense?: { expense_no: string; title: string } | null;
};

export default function VendorPayments({
    payments,
    filters,
}: {
    payments: {
        data: VendorPayment[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    filters: { from?: string; to?: string };
}) {
    const pageLabel = (label: string) =>
        label.replace(/&laquo;/g, '«').replace(/&raquo;/g, '»');
    return (
        <AuthenticatedLayout>
            <Head title="Vendor Payments" />
            <div className="space-y-6">
                <PageHeader
                    title="Vendor Payments"
                    description={`AP payments and reversals${filters.from ? ` from ${filters.from}` : ''}${filters.to ? ` to ${filters.to}` : ''}. Reversals subtract from the dashboard net cash out.`}
                />
                <DataTable
                    data={payments.data}
                    keyExtractor={(item) => item.id}
                    emptyMessage="No vendor payments match this period."
                    columns={[
                        {
                            header: 'Date',
                            accessor: (item) => item.payment_date,
                        },
                        {
                            header: 'Expense',
                            accessor: (item) =>
                                `${item.expense?.expense_no ?? '—'} · ${item.expense?.title ?? ''}`,
                        },
                        {
                            header: 'Type',
                            accessor: (item) => (
                                <Badge
                                    variant={
                                        item.entry_type === 'payment'
                                            ? 'success'
                                            : 'danger'
                                    }
                                    size="sm"
                                >
                                    {item.entry_type}
                                </Badge>
                            ),
                        },
                        {
                            header: 'Document amount',
                            accessor: (item) =>
                                `${item.currency} ${money(item.amount)}`,
                        },
                        {
                            header: 'Base amount',
                            accessor: (item) =>
                                `${item.base_currency} ${money(item.base_amount)}`,
                        },
                    ]}
                />
                <nav
                    aria-label="Vendor payment pages"
                    className="flex flex-wrap gap-2"
                >
                    {payments.links.map((link, index) =>
                        link.url ? (
                            <Link
                                key={index}
                                href={link.url}
                                preserveScroll
                                className={`rounded px-3 py-1 text-sm ${link.active ? 'bg-indigo-600 text-white' : 'border border-slate-300 text-indigo-700 dark:text-indigo-300'}`}
                            >
                                {pageLabel(link.label)}
                            </Link>
                        ) : (
                            <span
                                key={index}
                                className="px-3 py-1 text-sm text-slate-400"
                            >
                                {pageLabel(link.label)}
                            </span>
                        ),
                    )}
                </nav>
            </div>
        </AuthenticatedLayout>
    );
}
