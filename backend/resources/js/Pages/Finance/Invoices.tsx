import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

type Customer = { id: string; customer_code: string; company_name: string };
type Deal = { id: string; title: string; customer_id: string };
type Product = {
    id: string;
    sku?: string | null;
    name: string;
    unit?: string | null;
    price: string;
};
type InvoiceItem = {
    id?: string;
    product_id: string;
    description: string;
    quantity: string;
    unit: string;
    unit_price: string;
    discount_amount: string;
    tax_rate: string;
    line_total?: string;
    product?: Product | null;
};
type Invoice = {
    id: string;
    invoice_no: string;
    customer_id: string;
    deal_id?: string | null;
    status: string;
    tax_mode: string;
    issue_date: string;
    due_date?: string | null;
    subtotal: string;
    discount_amount: string;
    tax_amount: string;
    total: string;
    paid_amount: string;
    balance_due: string;
    currency: string;
    notes?: string | null;
    customer?: Customer | null;
    deal?: Deal | null;
    items: InvoiceItem[];
};
type InvoiceForm = {
    customer_id: string;
    deal_id: string;
    status: string;
    tax_mode: string;
    issue_date: string;
    due_date: string;
    discount_amount: string;
    currency: string;
    notes: string;
    items: InvoiceItem[];
};

const emptyItem: InvoiceItem = {
    product_id: '',
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    discount_amount: '0.00',
    tax_rate: '7.00',
};

const today = new Date().toISOString().slice(0, 10);

const emptyInvoice: InvoiceForm = {
    customer_id: '',
    deal_id: '',
    status: 'draft',
    tax_mode: 'exclusive',
    issue_date: today,
    due_date: '',
    discount_amount: '0.00',
    currency: 'THB',
    notes: '',
    items: [{ ...emptyItem }],
};

export default function Invoices({
    invoices,
    customers,
    deals,
    products,
    taxModes,
}: {
    invoices: Invoice[];
    customers: Customer[];
    deals: Deal[];
    products: Product[];
    statuses: string[];
    taxModes: string[];
    filters: Record<string, string | null>;
}) {
    const [editingInvoice, setEditingInvoice] = useState<Invoice | null>(null);
    const form = useForm<InvoiceForm>(emptyInvoice);
    const availableDeals = useMemo(
        () =>
            deals.filter((deal) => deal.customer_id === form.data.customer_id),
        [deals, form.data.customer_id],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.setData(emptyInvoice);
                setEditingInvoice(null);
            },
        };

        if (editingInvoice) {
            form.patch(route('invoices.update', editingInvoice.id), options);
        } else {
            form.post(route('invoices.store'), options);
        }
    };

    const editInvoice = (invoice: Invoice) => {
        setEditingInvoice(invoice);
        form.setData({
            customer_id: invoice.customer_id,
            deal_id: invoice.deal_id ?? '',
            status: ['draft', 'sent'].includes(invoice.status)
                ? invoice.status
                : 'draft',
            tax_mode: invoice.tax_mode,
            issue_date: invoice.issue_date?.slice(0, 10) ?? today,
            due_date: invoice.due_date?.slice(0, 10) ?? '',
            discount_amount: invoice.discount_amount ?? '0.00',
            currency: invoice.currency ?? 'THB',
            notes: invoice.notes ?? '',
            items: invoice.items.map((item) => ({
                product_id: item.product_id ?? '',
                description: item.description ?? '',
                quantity: String(item.quantity ?? '1'),
                unit: item.unit ?? '',
                unit_price: String(item.unit_price ?? '0.00'),
                discount_amount: String(item.discount_amount ?? '0.00'),
                tax_rate: String(item.tax_rate ?? '0.00'),
            })),
        });
    };

    const setItem = (
        index: number,
        key: keyof InvoiceItem,
        value: string,
    ) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };

        if (key === 'product_id') {
            const product = products.find((item) => item.id === value);
            if (product) {
                items[index].description = product.name;
                items[index].unit = product.unit ?? '';
                items[index].unit_price = product.price ?? '0.00';
            }
        }

        form.setData('items', items);
    };

    const totals = previewTotals(form.data);

    return (
        <AuthenticatedLayout>
            <Head title="Invoices" />

            <div className="space-y-6">
                <PageHeader
                    title="Invoices"
                    description="Manual invoices with server-calculated totals."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_430px]">
                    <Card title="Invoice List" description="Manual billing documents">
                        <DataTable
                            data={invoices}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Invoice',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-mono font-semibold text-slate-900 dark:text-white">
                                                {row.invoice_no}
                                            </div>
                                            <div className="text-xs text-slate-500 dark:text-slate-300">
                                                {row.customer?.company_name}
                                            </div>
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge
                                            variant={
                                                row.status === 'void'
                                                    ? 'danger'
                                                    : row.status === 'sent'
                                                      ? 'info'
                                                      : 'neutral'
                                            }
                                            size="sm"
                                        >
                                            {row.status}
                                        </Badge>
                                    ),
                                },
                                {
                                    header: 'Issue',
                                    accessor: (row) =>
                                        row.issue_date?.slice(0, 10) ?? '-',
                                },
                                {
                                    header: 'Total',
                                    accessor: (row) =>
                                        `${money(row.total)} ${row.currency}`,
                                },
                                {
                                    header: 'Balance',
                                    accessor: (row) =>
                                        money(row.balance_due),
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            {row.status !== 'void' &&
                                                Number(row.paid_amount) <= 0 && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            editInvoice(row)
                                                        }
                                                    >
                                                        Edit
                                                    </SecondaryButton>
                                                )}
                                            {row.status !== 'void' &&
                                                Number(row.paid_amount) <= 0 && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            router.patch(
                                                                route(
                                                                    'invoices.void',
                                                                    row.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll:
                                                                        true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Void
                                                    </SecondaryButton>
                                                )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </Card>

                    <Card
                        title={editingInvoice ? 'Edit Invoice' : 'Create Invoice'}
                        description="Totals are recalculated on save"
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <SelectField
                                label="Customer"
                                value={form.data.customer_id}
                                onChange={(value) => {
                                    form.setData({
                                        ...form.data,
                                        customer_id: value,
                                        deal_id: '',
                                    });
                                }}
                                options={customers.map((customer) => customer.id)}
                                labels={Object.fromEntries(
                                    customers.map((customer) => [
                                        customer.id,
                                        customer.company_name,
                                    ]),
                                )}
                            />
                            <SelectField
                                label="Deal"
                                value={form.data.deal_id}
                                onChange={(value) => form.setData('deal_id', value)}
                                options={availableDeals.map((deal) => deal.id)}
                                labels={Object.fromEntries(
                                    availableDeals.map((deal) => [
                                        deal.id,
                                        deal.title,
                                    ]),
                                )}
                            />
                            <SelectField
                                label="Status"
                                value={form.data.status}
                                onChange={(value) => form.setData('status', value)}
                                options={['draft', 'sent']}
                            />
                            <SelectField
                                label="Tax Mode"
                                value={form.data.tax_mode}
                                onChange={(value) =>
                                    form.setData('tax_mode', value)
                                }
                                options={taxModes}
                            />
                            <Field
                                label="Issue Date"
                                type="date"
                                value={form.data.issue_date}
                                error={form.errors.issue_date}
                                onChange={(value) =>
                                    form.setData('issue_date', value)
                                }
                            />
                            <Field
                                label="Due Date"
                                type="date"
                                value={form.data.due_date}
                                error={form.errors.due_date}
                                onChange={(value) =>
                                    form.setData('due_date', value)
                                }
                            />
                            <Field
                                label="Invoice Discount"
                                type="number"
                                value={form.data.discount_amount}
                                error={form.errors.discount_amount}
                                onChange={(value) =>
                                    form.setData('discount_amount', value)
                                }
                            />

                            <div className="space-y-3 rounded-md border border-slate-200 p-3 dark:border-slate-800">
                                <div className="flex items-center justify-between">
                                    <div className="text-sm font-semibold text-slate-800 dark:text-white">
                                        Items
                                    </div>
                                    <SecondaryButton
                                        type="button"
                                        onClick={() =>
                                            form.setData('items', [
                                                ...form.data.items,
                                                { ...emptyItem },
                                            ])
                                        }
                                    >
                                        Add
                                    </SecondaryButton>
                                </div>
                                {form.data.items.map((item, index) => (
                                    <div
                                        key={index}
                                        className="space-y-2 rounded-md bg-slate-50 p-3 dark:bg-slate-900"
                                    >
                                        <SelectField
                                            label="Product"
                                            value={item.product_id}
                                            onChange={(value) =>
                                                setItem(index, 'product_id', value)
                                            }
                                            options={products.map(
                                                (product) => product.id,
                                            )}
                                            labels={Object.fromEntries(
                                                products.map((product) => [
                                                    product.id,
                                                    product.name,
                                                ]),
                                            )}
                                        />
                                        <Field
                                            label="Description"
                                            value={item.description}
                                            error={
                                                form.errors[
                                                    `items.${index}.description` as keyof typeof form.errors
                                                ] as string
                                            }
                                            onChange={(value) =>
                                                setItem(
                                                    index,
                                                    'description',
                                                    value,
                                                )
                                            }
                                        />
                                        <div className="grid grid-cols-2 gap-2">
                                            <Field
                                                label="Qty"
                                                type="number"
                                                value={item.quantity}
                                                onChange={(value) =>
                                                    setItem(
                                                        index,
                                                        'quantity',
                                                        value,
                                                    )
                                                }
                                            />
                                            <Field
                                                label="Unit Price"
                                                type="number"
                                                value={item.unit_price}
                                                onChange={(value) =>
                                                    setItem(
                                                        index,
                                                        'unit_price',
                                                        value,
                                                    )
                                                }
                                            />
                                            <Field
                                                label="Unit"
                                                value={item.unit}
                                                onChange={(value) =>
                                                    setItem(index, 'unit', value)
                                                }
                                            />
                                            <Field
                                                label="Tax Rate"
                                                type="number"
                                                value={item.tax_rate}
                                                onChange={(value) =>
                                                    setItem(
                                                        index,
                                                        'tax_rate',
                                                        value,
                                                    )
                                                }
                                            />
                                        </div>
                                        {form.data.items.length > 1 && (
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    form.setData(
                                                        'items',
                                                        form.data.items.filter(
                                                            (_, itemIndex) =>
                                                                itemIndex !== index,
                                                        ),
                                                    )
                                                }
                                            >
                                                Remove
                                            </SecondaryButton>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <div className="rounded-md bg-slate-100 p-3 text-sm font-semibold text-slate-800 dark:bg-slate-900 dark:text-white">
                                <div>Subtotal: {money(totals.subtotal)}</div>
                                <div>Tax: {money(totals.tax)}</div>
                                <div>Total: {money(totals.total)}</div>
                            </div>

                            <div className="flex justify-end gap-2">
                                {editingInvoice && (
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => {
                                            setEditingInvoice(null);
                                            form.setData(emptyInvoice);
                                        }}
                                    >
                                        Cancel
                                    </SecondaryButton>
                                )}
                                <PrimaryButton disabled={form.processing}>
                                    Save Invoice
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function previewTotals(form: InvoiceForm) {
    const subtotal = form.items.reduce(
        (sum, item) =>
            sum +
            Math.max(
                0,
                Number(item.quantity || 0) * Number(item.unit_price || 0) -
                    Number(item.discount_amount || 0),
            ),
        0,
    );
    const tax =
        form.tax_mode === 'exclusive'
            ? form.items.reduce((sum, item) => {
                  const line = Math.max(
                      0,
                      Number(item.quantity || 0) * Number(item.unit_price || 0) -
                          Number(item.discount_amount || 0),
                  );

                  return sum + line * (Number(item.tax_rate || 0) / 100);
              }, 0)
            : form.tax_mode === 'inclusive'
              ? form.items.reduce((sum, item) => {
                    const line = Math.max(
                        0,
                        Number(item.quantity || 0) * Number(item.unit_price || 0) -
                            Number(item.discount_amount || 0),
                    );
                    const rate = Number(item.tax_rate || 0);
                    return sum + (rate > 0 ? line - (line / (1 + rate / 100)) : 0);
                }, 0)
              : 0;
    const total = Math.max(
        0,
        form.tax_mode === 'exclusive'
            ? subtotal - Number(form.discount_amount || 0) + tax
            : subtotal - Number(form.discount_amount || 0),
    );

    return { subtotal, tax, total };
}

function money(value: string | number) {
    return Number(value || 0).toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
}

function Field({
    label,
    value,
    error,
    onChange,
    type = 'text',
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
}) {
    const id = label.toLowerCase().replaceAll(' ', '-');

    return (
        <div>
            <InputLabel
                htmlFor={id}
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700 dark:text-slate-200"
            />
            <TextInput
                id={id}
                type={type}
                step={type === 'number' ? '0.01' : undefined}
                min={type === 'number' ? '0' : undefined}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full text-sm"
            />
            <InputError message={error} className="mt-1" />
        </div>
    );
}

function SelectField({
    label,
    value,
    options,
    labels = {},
    onChange,
}: {
    label: string;
    value: string;
    options: string[];
    labels?: Record<string, string>;
    onChange: (value: string) => void;
}) {
    return (
        <div>
            <InputLabel
                value={label}
                className="mb-1 text-xs font-semibold uppercase text-slate-700 dark:text-slate-200"
            />
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="block w-full rounded-xl border-slate-300 bg-white text-sm font-medium text-slate-900 shadow-sm transition-colors duration-150 focus:border-indigo-500 focus:ring-indigo-500 dark:border-slate-800 dark:bg-slate-900 dark:text-white"
            >
                <option value="">Select</option>
                {options.map((option) => (
                    <option key={option} value={option}>
                        {labels[option] ?? option}
                    </option>
                ))}
            </select>
        </div>
    );
}
