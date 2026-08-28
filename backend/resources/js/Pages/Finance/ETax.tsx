import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';

type Config = { mode: string; provider_code?: string | null; certificate_reference?: string | null; certificate_expires_at?: string | null; signature_mode: string };
type Source = { id: string; invoice_no?: string; note_no?: string; type?: string; amount?: string; total?: string; payment_date?: string; issue_date?: string; status?: string };
type Document = { id: string; document_no: string; document_type: string; status: string; xml_sha256: string; last_error?: string | null; created_at: string; attempts: { id: string; status: string; response_message?: string | null; created_at: string }[] };

export default function ETax({ config, documents, invoices, payments, notes }: { config: Config; documents: Document[]; invoices: Source[]; payments: Source[]; notes: Source[] }) {
    const settings = useForm({ _method: 'patch', mode: config.mode, provider_code: config.provider_code ?? '', certificate_reference: config.certificate_reference ?? '', certificate_expires_at: config.certificate_expires_at?.slice(0, 10) ?? '', signature_mode: config.signature_mode });
    const generation = useForm({ source_type: 'invoice', source_id: invoices[0]?.id ?? '', document_type: 'tax_invoice' });
    const sources = generation.data.source_type === 'invoice' ? invoices : generation.data.source_type === 'payment' ? payments : notes;

    return <AuthenticatedLayout><Head title="e-Tax & RD Prep" /><div className="space-y-6">
        <PageHeader title="e-Tax & RD Prep" description="Generate provider-mapping XML, maintain submission history, and export withholding staging text." actions={<Badge variant={config.mode === 'provider' ? 'warning' : 'neutral'}>{config.mode}</Badge>} />
        <Card title="Submission Configuration" description="Certificate material stays outside the application. Store only a vault/KMS reference here.">
            <form className="grid gap-3 md:grid-cols-2" onSubmit={(event) => { event.preventDefault(); settings.patch(route('e-tax.config.update'), { preserveScroll: true }); }}>
                <select value={settings.data.mode} onChange={(event) => settings.setData('mode', event.target.value)} className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="disabled">Disabled</option><option value="manual_export">Manual XML Export</option><option value="provider">Certified Provider</option></select>
                <input value={settings.data.provider_code} onChange={(event) => settings.setData('provider_code', event.target.value)} placeholder="Provider code" className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" />
                <input value={settings.data.certificate_reference} onChange={(event) => settings.setData('certificate_reference', event.target.value)} placeholder="Certificate vault reference" className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" />
                <input type="date" value={settings.data.certificate_expires_at} onChange={(event) => settings.setData('certificate_expires_at', event.target.value)} className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900" />
                <div className="md:col-span-2"><PrimaryButton disabled={settings.processing}>Save Configuration</PrimaryButton></div>
            </form>
        </Card>
        <Card title="Generate e-Tax XML" description="XML uses the internal provider-mapping profile. Validate against the certified provider's current ETDA/RD schema before production submission.">
            <form className="grid gap-3 md:grid-cols-4" onSubmit={(event) => { event.preventDefault(); generation.post(route('e-tax.documents.generate'), { preserveScroll: true }); }}>
                <select value={generation.data.source_type} onChange={(event) => { const sourceType = event.target.value; const list = sourceType === 'invoice' ? invoices : sourceType === 'payment' ? payments : notes; generation.setData({ ...generation.data, source_type: sourceType, source_id: list[0]?.id ?? '', document_type: sourceType === 'invoice' ? 'tax_invoice' : sourceType === 'payment' ? 'receipt' : 'credit_note' }); }} className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="invoice">Invoice</option><option value="payment">Receipt Payment</option><option value="credit_debit_note">Credit / Debit Note</option></select>
                <select value={generation.data.source_id} onChange={(event) => generation.setData('source_id', event.target.value)} className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900">{sources.map((source) => <option key={source.id} value={source.id}>{source.invoice_no ?? source.note_no ?? `Receipt ${source.id.slice(0, 8)}`}</option>)}</select>
                <select value={generation.data.document_type} onChange={(event) => generation.setData('document_type', event.target.value)} className="rounded-md border-slate-300 text-sm dark:border-slate-700 dark:bg-slate-900"><option value="tax_invoice">Tax Invoice</option><option value="receipt">Receipt</option><option value="credit_note">Credit Note</option><option value="debit_note">Debit Note</option></select>
                <PrimaryButton disabled={generation.processing || !generation.data.source_id}>Generate XML</PrimaryButton>
            </form>
        </Card>
        <Card title="RD Prep Staging Export" description="Pipe-delimited draft for ภ.ง.ด. 3/53. Verify against the current official RD format before upload."><div className="flex gap-2"><a href={route('e-tax.rd-prep', { form: 'pnd3' })}><SecondaryButton type="button">Export ภ.ง.ด. 3</SecondaryButton></a><a href={route('e-tax.rd-prep', { form: 'pnd53' })}><SecondaryButton type="button">Export ภ.ง.ด. 53</SecondaryButton></a></div></Card>
        <Card title="e-Tax Documents" description="Submission is fail-closed until a certified provider adapter is installed."><DataTable data={documents} keyExtractor={(document) => document.id} columns={[
            { header: 'Document', accessor: (document) => <div><div className="font-mono font-semibold">{document.document_no}</div><div className="text-xs text-slate-500">{document.document_type}</div></div> },
            { header: 'Status', accessor: (document) => <Badge variant={document.status === 'accepted' ? 'success' : document.status === 'rejected' ? 'danger' : 'neutral'}>{document.status}</Badge> },
            { header: 'Attempts', accessor: (document) => document.attempts.length },
            { header: 'Action', accessor: (document) => <div className="flex gap-2"><a href={route('e-tax.documents.download', document.id)}><SecondaryButton type="button">XML</SecondaryButton></a>{!['submitted', 'accepted'].includes(document.status) && <SecondaryButton type="button" onClick={() => router.post(route('e-tax.documents.submit', document.id))}>Queue</SecondaryButton>}</div> },
        ]} /></Card>
    </div></AuthenticatedLayout>;
}
