import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Branch = { id: string; code: string; name: string };
type Customer = { id: string; customer_code: string; company_name: string };
type Supplier = { id: string; supplier_code: string; name: string };
type TaxRow = {
    date: string;
    document_no: string;
    source?: string;
    partner: string;
    tax_id: string;
    branch?: string;
    tax_mode: string;
    taxable_base: string;
    tax_amount: string;
    total: string;
};
type AgingRow = {
    due_date: string;
    document_no: string;
    partner: string;
    days_overdue: string;
    bucket: string;
    amount: string;
};
type WithholdingRow = {
    date: string;
    document_no: string;
    partner: string;
    tax_id: string;
    form: string;
    base_amount: string;
    wht_rate: string;
    wht_amount: string;
};

type Filters = {
    date_from?: string | null;
    date_to?: string | null;
    branch_id?: string | null;
    status?: string | null;
    customer_id?: string | null;
    supplier_id?: string | null;
};

export default function TaxReports({
    filters,
    branches,
    customers,
    suppliers,
    salesRows,
    purchaseRows,
    withholdingRows,
    arAgingRows,
    apAgingRows,
}: {
    filters: Filters;
    branches: Branch[];
    customers: Customer[];
    suppliers: Supplier[];
    salesRows: TaxRow[];
    purchaseRows: TaxRow[];
    withholdingRows: WithholdingRow[];
    arAgingRows: AgingRow[];
    apAgingRows: AgingRow[];
}) {
    const [dateFrom, setDateFrom] = useState(filters.date_from ?? '');
    const [dateTo, setDateTo] = useState(filters.date_to ?? '');
    const [branchId, setBranchId] = useState(filters.branch_id ?? '');
    const [status, setStatus] = useState(filters.status ?? '');
    const [customerId, setCustomerId] = useState(filters.customer_id ?? '');
    const [supplierId, setSupplierId] = useState(filters.supplier_id ?? '');

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(
            route('tax-reports.index'),
            {
                date_from: dateFrom,
                date_to: dateTo,
                branch_id: branchId,
                status,
                customer_id: customerId,
                supplier_id: supplierId,
            },
            { preserveScroll: true },
        );
    };

    type ExportType =
        'sales' | 'purchase' | 'withholding' | 'ar-aging' | 'ap-aging';

    const reportUrl = (routeName: string, type: ExportType) =>
        route(routeName, type) +
        '?' +
        new URLSearchParams({
            date_from: dateFrom,
            date_to: dateTo,
            branch_id: branchId,
            status,
            customer_id: customerId,
            supplier_id: supplierId,
        }).toString();

    return (
        <AuthenticatedLayout>
            <Head title="Tax Reports" />
            <div className="space-y-6">
                <PageHeader
                    title="Tax Reports"
                    description="Sales tax and purchase tax summaries for accounting export."
                />

                <Card title="Filters" description="Report period and branch">
                    <form
                        onSubmit={submit}
                        className="grid gap-3 md:grid-cols-[160px_160px_repeat(4,minmax(0,1fr))_auto]"
                    >
                        <TextInput
                            type="date"
                            value={dateFrom}
                            onChange={(event) =>
                                setDateFrom(event.target.value)
                            }
                        />
                        <TextInput
                            type="date"
                            value={dateTo}
                            onChange={(event) => setDateTo(event.target.value)}
                        />
                        <select
                            className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                            value={branchId}
                            onChange={(event) =>
                                setBranchId(event.target.value)
                            }
                        >
                            <option value="">All branches</option>
                            {branches.map((branch) => (
                                <option key={branch.id} value={branch.id}>
                                    {branch.code} - {branch.name}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                            value={status}
                            onChange={(event) => setStatus(event.target.value)}
                        >
                            <option value="">All statuses</option>
                            {[
                                'sent',
                                'partially_paid',
                                'paid',
                                'overdue',
                                'approved',
                                'partially_received',
                                'received',
                                'closed',
                            ].map((item) => (
                                <option key={item} value={item}>
                                    {item}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                            value={customerId}
                            onChange={(event) =>
                                setCustomerId(event.target.value)
                            }
                        >
                            <option value="">All customers</option>
                            {customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.customer_code} -{' '}
                                    {customer.company_name}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                            value={supplierId}
                            onChange={(event) =>
                                setSupplierId(event.target.value)
                            }
                        >
                            <option value="">All suppliers</option>
                            {suppliers.map((supplier) => (
                                <option key={supplier.id} value={supplier.id}>
                                    {supplier.supplier_code} - {supplier.name}
                                </option>
                            ))}
                        </select>
                        <SecondaryButton type="submit">Apply</SecondaryButton>
                    </form>
                </Card>

                <TaxReportTable
                    title="Sales Tax Report"
                    description="Invoices excluding draft and void documents"
                    rows={salesRows}
                    csvHref={reportUrl('tax-reports.export', 'sales')}
                    excelHref={reportUrl('tax-reports.excel', 'sales')}
                />

                <TaxReportTable
                    title="Purchase Tax Report"
                    description="Purchase tax from approved POs, expenses, and posted GRNs"
                    rows={purchaseRows}
                    csvHref={reportUrl('tax-reports.export', 'purchase')}
                    excelHref={reportUrl('tax-reports.excel', 'purchase')}
                />

                <WithholdingReportTable
                    rows={withholdingRows}
                    csvHref={reportUrl('tax-reports.export', 'withholding')}
                    excelHref={reportUrl('tax-reports.excel', 'withholding')}
                />

                <div className="grid gap-6 xl:grid-cols-2">
                    <AgingReportTable
                        title="AR Aging"
                        description="Open invoice balances grouped by due date"
                        rows={arAgingRows}
                        excelHref={reportUrl('tax-reports.excel', 'ar-aging')}
                    />
                    <AgingReportTable
                        title="AP Aging"
                        description="Open purchase order commitments grouped by expected date"
                        rows={apAgingRows}
                        excelHref={reportUrl('tax-reports.excel', 'ap-aging')}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function TaxReportTable({
    title,
    description,
    rows,
    csvHref,
    excelHref,
}: {
    title: string;
    description: string;
    rows: TaxRow[];
    csvHref: string;
    excelHref: string;
}) {
    const totals = rows.reduce(
        (carry, row) => ({
            taxableBase: carry.taxableBase + Number(row.taxable_base),
            taxAmount: carry.taxAmount + Number(row.tax_amount),
            total: carry.total + Number(row.total),
        }),
        { taxableBase: 0, taxAmount: 0, total: 0 },
    );

    return (
        <Card
            title={title}
            description={description}
            action={
                <div className="flex gap-2">
                    <a href={csvHref}>
                        <SecondaryButton type="button">
                            Export CSV
                        </SecondaryButton>
                    </a>
                    <a href={excelHref}>
                        <SecondaryButton type="button">
                            Export Excel
                        </SecondaryButton>
                    </a>
                </div>
            }
        >
            <div className="mb-4 grid gap-3 md:grid-cols-3">
                <Summary label="Taxable Base" value={totals.taxableBase} />
                <Summary label="VAT" value={totals.taxAmount} />
                <Summary label="Total" value={totals.total} />
            </div>
            <DataTable
                data={rows}
                keyExtractor={(row) => `${row.document_no}-${row.date}`}
                columns={[
                    { header: 'Date', accessor: (row) => row.date },
                    {
                        header: 'Document',
                        accessor: (row) => (
                            <div>
                                <div className="font-mono font-semibold">
                                    {row.document_no}
                                </div>
                                {row.branch && (
                                    <div className="text-xs text-slate-500">
                                        {row.branch}
                                    </div>
                                )}
                                {row.source && (
                                    <div className="text-xs text-slate-500">
                                        {titleCase(row.source)}
                                    </div>
                                )}
                            </div>
                        ),
                    },
                    { header: 'Partner', accessor: (row) => row.partner },
                    { header: 'Tax ID', accessor: (row) => row.tax_id || '-' },
                    { header: 'Mode', accessor: (row) => row.tax_mode },
                    {
                        header: 'Taxable',
                        accessor: (row) => money(row.taxable_base),
                    },
                    { header: 'VAT', accessor: (row) => money(row.tax_amount) },
                    { header: 'Total', accessor: (row) => money(row.total) },
                ]}
            />
        </Card>
    );
}

function WithholdingReportTable({
    rows,
    csvHref,
    excelHref,
}: {
    rows: WithholdingRow[];
    csvHref: string;
    excelHref: string;
}) {
    const totals = rows.reduce(
        (carry, row) => ({
            base: carry.base + Number(row.base_amount),
            wht: carry.wht + Number(row.wht_amount),
        }),
        { base: 0, wht: 0 },
    );

    return (
        <Card
            title="Withholding Tax Report"
            description="ภ.ง.ด. 3 / ภ.ง.ด. 53 from approved or paid expenses"
            action={
                <div className="flex gap-2">
                    <a href={csvHref}>
                        <SecondaryButton type="button">
                            Export CSV
                        </SecondaryButton>
                    </a>
                    <a href={excelHref}>
                        <SecondaryButton type="button">
                            Export Excel
                        </SecondaryButton>
                    </a>
                </div>
            }
        >
            <div className="mb-4 grid gap-3 md:grid-cols-2">
                <Summary label="Base Amount" value={totals.base} />
                <Summary label="WHT" value={totals.wht} />
            </div>
            <DataTable
                data={rows}
                keyExtractor={(row) => `${row.document_no}-${row.date}`}
                columns={[
                    { header: 'Date', accessor: (row) => row.date },
                    {
                        header: 'Document',
                        accessor: (row) => (
                            <span className="font-mono font-semibold">
                                {row.document_no}
                            </span>
                        ),
                    },
                    { header: 'Supplier', accessor: (row) => row.partner },
                    { header: 'Tax ID', accessor: (row) => row.tax_id || '-' },
                    { header: 'Form', accessor: (row) => row.form },
                    {
                        header: 'Base',
                        accessor: (row) => money(row.base_amount),
                    },
                    { header: 'Rate', accessor: (row) => `${row.wht_rate}%` },
                    { header: 'WHT', accessor: (row) => money(row.wht_amount) },
                ]}
            />
        </Card>
    );
}

function Summary({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 dark:border-slate-800 dark:bg-slate-950">
            <div className="text-xs font-semibold uppercase text-slate-500">
                {label}
            </div>
            <div className="mt-1 text-lg font-bold">{money(value)}</div>
        </div>
    );
}

function titleCase(value: string) {
    return value
        .replace(/_/g, ' ')
        .replace(/\b\w/g, (char) => char.toUpperCase());
}

function AgingReportTable({
    title,
    description,
    rows,
    excelHref,
}: {
    title: string;
    description: string;
    rows: AgingRow[];
    excelHref: string;
}) {
    const buckets = ['0-30', '31-60', '61-90', '>90'];
    const totals = buckets.map((bucket) => ({
        bucket,
        amount: rows
            .filter((row) => row.bucket === bucket)
            .reduce((sum, row) => sum + Number(row.amount), 0),
    }));

    return (
        <Card
            title={title}
            description={description}
            action={
                <a href={excelHref}>
                    <SecondaryButton type="button">
                        Export Excel
                    </SecondaryButton>
                </a>
            }
        >
            <div className="mb-4 grid grid-cols-2 gap-3">
                {totals.map((item) => (
                    <Summary
                        key={item.bucket}
                        label={item.bucket}
                        value={item.amount}
                    />
                ))}
            </div>
            <DataTable
                data={rows}
                keyExtractor={(row) => `${row.document_no}-${row.due_date}`}
                columns={[
                    { header: 'Due', accessor: (row) => row.due_date },
                    {
                        header: 'Document',
                        accessor: (row) => (
                            <span className="font-mono font-semibold">
                                {row.document_no}
                            </span>
                        ),
                    },
                    { header: 'Partner', accessor: (row) => row.partner },
                    {
                        header: 'Days',
                        accessor: (row) => row.days_overdue,
                    },
                    { header: 'Bucket', accessor: (row) => row.bucket },
                    { header: 'Amount', accessor: (row) => money(row.amount) },
                ]}
            />
        </Card>
    );
}
