import PrimaryButton from '@/Components/PrimaryButton';
import { Head, router } from '@inertiajs/react';
import { FormEvent } from 'react';

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
    payables,
}: {
    portalUser: { name?: string | null; email: string; party_type: string };
    qrEnabled: boolean;
    quotations: Quote[];
    invoices: Invoice[];
    submissions: Submission[];
    documents: PortalDocument[];
    purchaseOrders: PurchaseOrder[];
    payables: Payable[];
}) {
    const submitBill = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        router.post(
            route('portal.vendor-bills.store'),
            new FormData(event.currentTarget),
        );
    };
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
                    {portalUser.party_type === 'customer' && (
                        <>
                            <section>
                                <h2 className="mb-3 text-lg font-bold">
                                    Quotations
                                </h2>
                                <div className="space-y-2">
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
                            <section>
                                <h2 className="mb-3 text-lg font-bold">
                                    Invoices
                                </h2>
                                <div className="space-y-2">
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
                            <section>
                                <h2 className="mb-3 text-lg font-bold">
                                    Purchase orders
                                </h2>
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
                                <h2 className="mb-3 text-lg font-bold">
                                    Submit vendor bill
                                </h2>
                                <form
                                    onSubmit={submitBill}
                                    className="grid gap-3 border bg-white p-4 md:grid-cols-2"
                                >
                                    <input
                                        name="vendor_invoice_no"
                                        required
                                        placeholder="Vendor invoice number"
                                        className="border"
                                    />
                                    <input
                                        name="invoice_date"
                                        required
                                        type="date"
                                        className="border"
                                    />
                                    <input
                                        name="amount"
                                        required
                                        type="number"
                                        step="0.01"
                                        placeholder="Amount"
                                        className="border"
                                    />
                                    <input
                                        name="currency"
                                        defaultValue="THB"
                                        required
                                        maxLength={3}
                                        className="border"
                                    />
                                    <input
                                        name="file"
                                        required
                                        type="file"
                                        accept=".pdf,.jpg,.jpeg,.png,.webp"
                                        className="md:col-span-2"
                                    />
                                    <textarea
                                        name="note"
                                        placeholder="Note"
                                        className="border md:col-span-2"
                                    />
                                    <PrimaryButton>
                                        Submit for review
                                    </PrimaryButton>
                                </form>
                            </section>
                            <section>
                                <h2 className="mb-3 text-lg font-bold">
                                    Submitted bills
                                </h2>
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
                    <section>
                        <h2 className="mb-3 text-lg font-bold">Documents</h2>
                        <div className="space-y-2">
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
            </main>
        </>
    );
}
