import PrimaryButton from '@/Components/PrimaryButton';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import VendorBillDialog from './VendorBillDialog';

type Quote = {
    id: string;
    quotation_no: string;
    status: string;
    total: string;
    currency: string;
    valid_until?: string | null;
};
type Invoice = {
    id: string;
    invoice_no: string;
    status: string;
    total: string;
    balance_due: string;
    currency: string;
    due_date?: string | null;
};
type Submission = {
    id: string;
    vendor_invoice_no: string;
    status: string;
    amount: string;
    currency: string;
    invoice_date: string;
};
type PortalDocument = {
    id: string;
    document_no: string;
    title: string;
    current_version?: {
        id: string;
        original_name: string;
        scan_status: string;
    } | null;
};
type PurchaseOrder = {
    id: string;
    po_no: string;
    status: string;
    total: string;
    currency: string;
};
type Payable = {
    id: string;
    expense_no: string;
    status: string;
    balance_due: string;
    currency: string;
};

export default function PortalDashboard({
    qrEnabled,
    portalUser,
    quotations,
    invoices,
    submissions,
    documents,
    purchaseOrders,
    payables = [],
    pages,
    openInvoiceCount,
}: {
    portalUser: { name?: string | null; email: string; party_type: string };
    qrEnabled: boolean;
    quotations: Quote[];
    invoices: Invoice[];
    submissions: Submission[];
    documents: PortalDocument[];
    purchaseOrders: PurchaseOrder[];
    payables?: Payable[];
    pages: Record<string, { total: number; current: number; last: number; previous: string | null; next: string | null }>;
    openInvoiceCount: number;
}) {
    const [vendorBillDialogOpen, setVendorBillDialogOpen] = useState(false);
    const isCustomer = portalUser.party_type === 'customer';
    const summaries = isCustomer
        ? [
              {
                  label: 'Quotations',
                  count: pages.quotations?.total ?? 0,
                  target: 'quotations',
              },
              {
                  label: 'Open invoices',
                  count: openInvoiceCount,
                  target: 'invoices',
              },
              {
                  label: 'Documents',
                  count: pages.documents?.total ?? 0,
                  target: 'documents',
              },
          ]
        : [
              {
                  label: 'Purchase orders',
                  count: pages.purchaseOrders?.total ?? 0,
                  target: 'purchase-orders',
              },
              {
                  label: 'Submitted bills',
                  count: pages.submissions?.total ?? 0,
                  target: 'submitted-bills',
              },
              {
                  label: 'Documents',
                  count: pages.documents?.total ?? 0,
                  target: 'documents',
              },
          ];
    return (
        <>
            <Head title="ERP Portal" />
            <main className="min-h-screen bg-slate-50 px-4 py-8 text-slate-900">
                <div className="mx-auto max-w-5xl space-y-8">
                    <header>
                        <p className="text-sm font-medium text-indigo-700">
                            ERP Portal
                        </p>
                        <h1 className="text-2xl font-bold">
                            {portalUser.name || portalUser.email}
                        </h1>
                        <p className="text-sm text-slate-600 capitalize">
                            {portalUser.party_type} account
                        </p>
                        <button
                            type="button"
                            onClick={() => router.post(route('portal.logout'))}
                            className="mt-3 text-sm text-slate-600 underline"
                        >
                            Sign out
                        </button>
                    </header>
                    <nav aria-label="Document pagination" className="flex flex-wrap gap-4 text-sm">
                        {Object.entries(pages).filter(([, page]) => page.last > 1).map(([key, page]) => {
                            const label = ({ quotations: 'Quotations', invoices: 'Invoices', submissions: 'Submitted bills', purchaseOrders: 'Purchase orders', payables: 'Payables', documents: 'Documents' } as Record<string, string>)[key];
                            return <div key={key} className="rounded-lg border border-slate-200 bg-white p-3">
                                <p>{label}: page {page.current} of {page.last} · {page.total} records</p>
                                <div className="mt-2 flex gap-4">
                                    {page.previous && <Link href={page.previous} aria-label={`Previous ${label} page`} className="text-indigo-700 underline">Previous</Link>}
                                    {page.next && <Link href={page.next} aria-label={`Next ${label} page`} className="text-indigo-700 underline">Next</Link>}
                                </div>
                            </div>;
                        })}
                    </nav>
                    <nav
                        aria-label="Portal summary"
                        className="grid gap-3 sm:grid-cols-3"
                    >
                        {summaries.map((summary) => (
                            <a
                                key={summary.label}
                                href={`#${summary.target}`}
                                className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                <span className="block text-sm text-slate-600">
                                    {summary.label}
                                </span>
                                <span className="mt-2 block text-2xl font-bold">
                                    {summary.count}
                                </span>
                            </a>
                        ))}
                    </nav>
                    {portalUser.party_type === 'customer' && (
                        <>
                            <section id="quotations">
                                <h2 className="mb-3 text-lg font-bold">
                                    Quotations
                                </h2>
                                <div className="space-y-2">
                                    {quotations.length === 0 && (
                                        <p className="text-sm text-slate-600">
                                            No quotations available.
                                        </p>
                                    )}
                                    {quotations.map((quote) => (
                                        <div
                                            key={quote.id}
                                            className="flex items-center justify-between border bg-white p-4"
                                        >
                                            <span>
                                                {quote.quotation_no} ·{' '}
                                                {quote.currency} {quote.total}
                                            </span>
                                            {quote.status === 'sent' ? (
                                                <PrimaryButton
                                                    onClick={() => {
                                                        const name =
                                                            window.prompt(
                                                                'Confirm your name',
                                                            );
                                                        if (name)
                                                            router.post(
                                                                route(
                                                                    'portal.quotations.accept',
                                                                    quote.id,
                                                                ),
                                                                {
                                                                    accepted_by_name:
                                                                        name,
                                                                },
                                                            );
                                                    }}
                                                >
                                                    Accept
                                                </PrimaryButton>
                                            ) : (
                                                <span>{quote.status}</span>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                            <section id="invoices">
                                <h2 className="mb-3 text-lg font-bold">
                                    Invoices
                                </h2>
                                <div className="space-y-2">
                                    {invoices.length === 0 && (
                                        <p className="text-sm text-slate-600">
                                            No invoices available.
                                        </p>
                                    )}
                                    {invoices.map((invoice) => (
                                        <div
                                            key={invoice.id}
                                            className="border bg-white p-4"
                                        >
                                            {invoice.invoice_no} ·{' '}
                                            {invoice.status} ·{' '}
                                            {invoice.currency}{' '}
                                            {invoice.balance_due}
                                            {qrEnabled &&
                                                invoice.currency === 'THB' &&
                                                Number(invoice.balance_due) >
                                                    0 && (
                                                    <a
                                                        className="ml-3 underline"
                                                        href={route(
                                                            'gateway.qr',
                                                            invoice.id,
                                                        )}
                                                    >
                                                        Payment QR
                                                    </a>
                                                )}
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </>
                    )}
                    {portalUser.party_type === 'supplier' && (
                        <>
                            <section id="purchase-orders">
                                <h2 className="mb-3 text-lg font-bold">
                                    Purchase orders
                                </h2>
                                {purchaseOrders.length === 0 && (
                                    <p className="text-sm text-slate-600">
                                        No purchase orders available.
                                    </p>
                                )}
                                {purchaseOrders.map((item) => (
                                    <div
                                        key={item.id}
                                        className="border bg-white p-4"
                                    >
                                        {item.po_no} · {item.currency}{' '}
                                        {item.total} · {item.status}
                                    </div>
                                ))}
                            </section>
                            <section>
                                <h2 className="mb-3 text-lg font-bold">
                                    Payment status
                                </h2>
                                {payables.length === 0 && (
                                    <p className="text-sm text-slate-600">
                                        No payment status available.
                                    </p>
                                )}
                                {payables.map((item) => (
                                    <div
                                        key={item.id}
                                        className="border bg-white p-4"
                                    >
                                        {item.expense_no} · {item.currency}{' '}
                                        {item.balance_due} · {item.status}
                                    </div>
                                ))}
                            </section>
                            <section>
                                <div className="flex items-center justify-between border bg-white p-4">
                                    <div>
                                        <h2 className="text-lg font-bold">
                                            Submit vendor bill
                                        </h2>
                                        <p className="text-sm text-slate-600">
                                            Upload and submit your invoice for
                                            review and payment.
                                        </p>
                                    </div>
                                    <PrimaryButton
                                        onClick={() =>
                                            setVendorBillDialogOpen(true)
                                        }
                                    >
                                        Submit Vendor Bill
                                    </PrimaryButton>
                                </div>
                            </section>
                            <section id="submitted-bills">
                                <h2 className="mb-3 text-lg font-bold">
                                    Submitted bills
                                </h2>
                                {submissions.length === 0 && (
                                    <p className="text-sm text-slate-600">
                                        No submitted bills.
                                    </p>
                                )}
                                {submissions.map((item) => (
                                    <div
                                        key={item.id}
                                        className="border bg-white p-4"
                                    >
                                        {item.vendor_invoice_no} ·{' '}
                                        {item.currency} {item.amount} ·{' '}
                                        {item.status}
                                    </div>
                                ))}
                            </section>
                        </>
                    )}
                    <section id="documents">
                        <h2 className="mb-3 text-lg font-bold">Documents</h2>
                        <div className="space-y-2">
                            {documents.length === 0 && (
                                <p className="text-sm text-slate-600">
                                    No documents available.
                                </p>
                            )}
                            {documents.map((document) => (
                                <div
                                    key={document.id}
                                    className="border bg-white p-4"
                                >
                                    <div className="font-medium">
                                        {document.document_no} ·{' '}
                                        {document.title}
                                    </div>
                                    {document.current_version?.scan_status ===
                                    'clean' ? (
                                        <a
                                            className="text-sm text-indigo-700 underline"
                                            href={route(
                                                'portal.documents.download',
                                                document.current_version.id,
                                            )}
                                        >
                                            {
                                                document.current_version
                                                    .original_name
                                            }
                                        </a>
                                    ) : (
                                        <p className="text-sm text-slate-600">
                                            {document.current_version
                                                ?.scan_status ??
                                                'No file available'}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </section>
                </div>
                <VendorBillDialog
                    open={vendorBillDialogOpen}
                    onClose={() => setVendorBillDialogOpen(false)}
                />
            </main>
        </>
    );
}
