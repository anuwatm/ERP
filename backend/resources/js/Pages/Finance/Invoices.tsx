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
import { FormEvent, useEffect, useMemo, useState } from 'react';
import { money } from '@/Utils/format';

type Customer = { id: string; customer_code: string; company_name: string };
type Deal = { id: string; title: string; customer_id: string };
type Project = {
    id: string;
    project_code: string;
    name: string;
    customer_id: string;
};
type SourceDeal = { id: string; title: string; customer_id: string };

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
type AttachmentFile = {
    id: string;
    file_name: string;
    mime_type?: string | null;
    size_bytes?: number | null;
};

type Payment = {
    id: string;
    entry_type: string;
    amount: string;
    payment_date: string;
    payment_method: string;
    reference_no?: string | null;
    reversal_of_payment_id?: string | null;
    attachment_file_id?: string | null;
    attachment?: AttachmentFile | null;
};
type MoneyValue = string | number;
type TaxSummaryLine = {
    gross_total: MoneyValue;
    allocated_header_discount: MoneyValue;
    gross_after_discount: MoneyValue;
    taxable_base: MoneyValue;
    tax_amount: MoneyValue;
    tax_rate: MoneyValue;
};
type TaxSummary = {
    mode: string;
    gross_subtotal: MoneyValue;
    header_discount: MoneyValue;
    gross_after_discount: MoneyValue;
    net_subtotal: MoneyValue;
    taxable_base: MoneyValue;
    tax_amount: MoneyValue;
    total: MoneyValue;
    wording: string;
    lines: TaxSummaryLine[];
};
type Invoice = {
    id: string;
    invoice_no: string;
    customer_id: string;
    deal_id?: string | null;
    project_id?: string | null;
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
    project?: Project | null;
    items: InvoiceItem[];
    tax_summary?: TaxSummary;
    payments?: Payment[];
    needs_sales_review?: boolean;
};
type InvoiceForm = {
    customer_id: string;
    deal_id: string;
    project_id: string;
    status: string;
    tax_mode: string;
    issue_date: string;
    due_date: string;
    discount_amount: string;
    currency: string;
    notes: string;
    items: InvoiceItem[];
};
type PaymentForm = {
    amount: string;
    payment_date: string;
    payment_method: string;
    bank_account_id: string;
    reference_no: string;
    note: string;
    idempotency_key: string;
    attachment: File | null;
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
    project_id: '',
    status: 'draft',
    tax_mode: 'exclusive',
    issue_date: today,
    due_date: '',
    discount_amount: '0.00',
    currency: 'THB',
    notes: '',
    items: [{ ...emptyItem }],
};

const paymentKey = () =>
    globalThis.crypto?.randomUUID?.() ??
    Date.now() + '-' + Math.random().toString(36).slice(2);

const emptyPayment = (amount = '0.00'): PaymentForm => ({
    amount,
    payment_date: today,
    payment_method: 'bank_transfer',
    bank_account_id: '',
    reference_no: '',
    note: '',
    idempotency_key: paymentKey(),
    attachment: null,
});

export default function Invoices({
    invoices,
    customers,
    deals,
    projects,
    products,
    taxModes,
    canRecordPayments,
    canReversePayments,
    bankAccounts,
    sourceDeal,
}: {
    invoices: Invoice[];
    customers: Customer[];
    deals: Deal[];
    projects: Project[];
    products: Product[];
    statuses: string[];
    taxModes: string[];
    filters: Record<string, string | null>;
    canRecordPayments: boolean;
    canReversePayments: boolean;
    bankAccounts: { id: string; bank_name: string; account_name: string }[];
    sourceDeal?: SourceDeal | null;
}) {
    const [editingInvoice, setEditingInvoice] = useState<Invoice | null>(null);
    const [payingInvoice, setPayingInvoice] = useState<Invoice | null>(null);
    const form = useForm<InvoiceForm>(emptyInvoice);
    const paymentForm = useForm<PaymentForm>(emptyPayment());
    const availableDeals = useMemo(
        () =>
            deals.filter((deal) => deal.customer_id === form.data.customer_id),
        [deals, form.data.customer_id],
    );
    const availableProjects = useMemo(
        () =>
            projects.filter(
                (project) => project.customer_id === form.data.customer_id,
            ),
        [projects, form.data.customer_id],
    );

    useEffect(() => {
        if (!sourceDeal || editingInvoice) {
            return;
        }

        form.setData((current) => ({
            ...current,
            customer_id: sourceDeal.customer_id,
            deal_id: sourceDeal.id,
            project_id: '',
            status: 'sent',
            notes: current.notes || `From deal: ${sourceDeal.title}`,
        }));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sourceDeal?.id]);

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

    const submitPayment = (event: FormEvent) => {
        event.preventDefault();

        if (!payingInvoice) {
            return;
        }

        paymentForm.post(route('invoices.payments.store', payingInvoice.id), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                setPayingInvoice(null);
                paymentForm.setData(emptyPayment());
            },
        });
    };

    const reversePayment = (payment: Payment) => {
        router.post(
            route('payments.reverse', payment.id),
            { idempotency_key: paymentKey() },
            { preserveScroll: true },
        );
    };

    const editInvoice = (invoice: Invoice) => {
        setEditingInvoice(invoice);
        form.setData({
            customer_id: invoice.customer_id,
            deal_id: invoice.deal_id ?? '',
            project_id: invoice.project_id ?? '',
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

    const openPayment = (invoice: Invoice) => {
        setPayingInvoice(invoice);
        paymentForm.setData(emptyPayment(invoice.balance_due ?? '0.00'));
    };

    const setItem = (index: number, key: keyof InvoiceItem, value: string) => {
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
                    <Card
                        title="Invoice List"
                        description="Manual billing documents"
                    >
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
                                            {row.project && (
                                                <div className="text-xs text-slate-500 dark:text-slate-300">
                                                    {row.project.project_code} -{' '}
                                                    {row.project.name}
                                                </div>
                                            )}
                                            {row.needs_sales_review && (
                                                <div className="mt-1">
                                                    <Badge
                                                        variant="warning"
                                                        size="sm"
                                                    >
                                                        needs sales review
                                                    </Badge>
                                                </div>
                                            )}
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
                                    accessor: (row) => (
                                        <div>
                                            <div>
                                                {money(row.total)}{' '}
                                                {row.currency}
                                            </div>
                                            {row.tax_mode === 'inclusive' && (
                                                <div className="text-xs text-slate-500 dark:text-slate-300">
                                                    VAT included:{' '}
                                                    {money(
                                                        row.tax_summary
                                                            ?.tax_amount ??
                                                            row.tax_amount,
                                                    )}
                                                </div>
                                            )}
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Paid',
                                    accessor: (row) => money(row.paid_amount),
                                },
                                {
                                    header: 'Balance',
                                    accessor: (row) => money(row.balance_due),
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
                                                            'invoices.print',
                                                            row.id,
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
                                                        route(
                                                            'invoices.pdf',
                                                            row.id,
                                                        ),
                                                        '_blank',
                                                        'noopener,noreferrer',
                                                    )
                                                }
                                            >
                                                PDF
                                            </SecondaryButton>
                                            {canRecordPayments &&
                                                row.status !== 'void' &&
                                                Number(row.balance_due) > 0 && (
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            openPayment(row)
                                                        }
                                                    >
                                                        Record Payment
                                                    </SecondaryButton>
                                                )}
                                            {row.status !== 'void' &&
                                                Number(row.paid_amount) <=
                                                    0 && (
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
                                                Number(row.paid_amount) <=
                                                    0 && (
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
                                                                    preserveScroll: true,
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

                    <div className="space-y-6">
                        {payingInvoice && (
                            <Card
                                title="Record Payment"
                                description={
                                    'Invoice ' + payingInvoice.invoice_no
                                }
                            >
                                <form
                                    onSubmit={submitPayment}
                                    className="space-y-3"
                                >
                                    <div className="rounded-md bg-slate-100 p-3 text-sm font-semibold text-slate-800 dark:bg-slate-900 dark:text-white">
                                        <div>
                                            Total: {money(payingInvoice.total)}{' '}
                                            {payingInvoice.currency}
                                        </div>
                                        <div>
                                            Paid:{' '}
                                            {money(payingInvoice.paid_amount)}
                                        </div>
                                        <div>
                                            Balance:{' '}
                                            {money(payingInvoice.balance_due)}
                                        </div>
                                    </div>
                                    <Field
                                        label="Amount"
                                        type="number"
                                        value={paymentForm.data.amount}
                                        error={paymentForm.errors.amount}
                                        onChange={(value) =>
                                            paymentForm.setData('amount', value)
                                        }
                                    />
                                    <Field
                                        label="Payment Date"
                                        type="date"
                                        value={paymentForm.data.payment_date}
                                        error={paymentForm.errors.payment_date}
                                        onChange={(value) =>
                                            paymentForm.setData(
                                                'payment_date',
                                                value,
                                            )
                                        }
                                    />
                                    <SelectField
                                        label="Payment Method"
                                        value={paymentForm.data.payment_method}
                                        onChange={(value) =>
                                            paymentForm.setData(
                                                'payment_method',
                                                value,
                                            )
                                        }
                                        options={[
                                            'bank_transfer',
                                            'cash',
                                            'credit_card',
                                            'promptpay',
                                            'other',
                                        ]}
                                    />
                                    <div>
                                        <InputLabel value="Bank / Cash Account" />
                                        <select
                                            className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                            value={
                                                paymentForm.data.bank_account_id
                                            }
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'bank_account_id',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="">Not linked</option>
                                            {bankAccounts.map((account) => (
                                                <option
                                                    key={account.id}
                                                    value={account.id}
                                                >
                                                    {account.bank_name} -{' '}
                                                    {account.account_name}
                                                </option>
                                            ))}
                                        </select>
                                        <InputError
                                            message={
                                                paymentForm.errors
                                                    .bank_account_id
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                    <Field
                                        label="Reference No"
                                        value={paymentForm.data.reference_no}
                                        error={paymentForm.errors.reference_no}
                                        onChange={(value) =>
                                            paymentForm.setData(
                                                'reference_no',
                                                value,
                                            )
                                        }
                                    />
                                    <Field
                                        label="Note"
                                        value={paymentForm.data.note}
                                        error={paymentForm.errors.note}
                                        onChange={(value) =>
                                            paymentForm.setData('note', value)
                                        }
                                    />
                                    <div>
                                        <InputLabel value="Attachment" />
                                        <input
                                            type="file"
                                            accept=".pdf,.jpg,.jpeg,.png,.webp"
                                            onChange={(event) =>
                                                paymentForm.setData(
                                                    'attachment',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                            className="mt-1 block w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm dark:border-slate-700 dark:bg-slate-950 dark:text-white"
                                        />
                                        <InputError
                                            message={
                                                paymentForm.errors.attachment
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                    {payingInvoice.payments &&
                                        payingInvoice.payments.length > 0 && (
                                            <PaymentHistory
                                                payments={
                                                    payingInvoice.payments
                                                }
                                                canReversePayments={
                                                    canReversePayments
                                                }
                                                onReverse={reversePayment}
                                            />
                                        )}
                                    <div className="flex justify-end gap-2">
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                setPayingInvoice(null)
                                            }
                                        >
                                            Cancel
                                        </SecondaryButton>
                                        <PrimaryButton
                                            disabled={paymentForm.processing}
                                        >
                                            Save Payment
                                        </PrimaryButton>
                                    </div>
                                </form>
                            </Card>
                        )}

                        <Card
                            title={
                                editingInvoice
                                    ? 'Edit Invoice'
                                    : 'Create Invoice'
                            }
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
                                            project_id: '',
                                        });
                                    }}
                                    options={customers.map(
                                        (customer) => customer.id,
                                    )}
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
                                    onChange={(value) =>
                                        form.setData('deal_id', value)
                                    }
                                    options={availableDeals.map(
                                        (deal) => deal.id,
                                    )}
                                    labels={Object.fromEntries(
                                        availableDeals.map((deal) => [
                                            deal.id,
                                            deal.title,
                                        ]),
                                    )}
                                />
                                <SelectField
                                    label="Project"
                                    value={form.data.project_id}
                                    onChange={(value) =>
                                        form.setData('project_id', value)
                                    }
                                    options={availableProjects.map(
                                        (project) => project.id,
                                    )}
                                    labels={Object.fromEntries(
                                        availableProjects.map((project) => [
                                            project.id,
                                            `${project.project_code} - ${project.name}`,
                                        ]),
                                    )}
                                />
                                <SelectField
                                    label="Status"
                                    value={form.data.status}
                                    onChange={(value) =>
                                        form.setData('status', value)
                                    }
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
                                                    setItem(
                                                        index,
                                                        'product_id',
                                                        value,
                                                    )
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
                                                        setItem(
                                                            index,
                                                            'unit',
                                                            value,
                                                        )
                                                    }
                                                />
                                                <Field
                                                    label="Line Discount"
                                                    type="number"
                                                    value={item.discount_amount}
                                                    onChange={(value) =>
                                                        setItem(
                                                            index,
                                                            'discount_amount',
                                                            value,
                                                        )
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
                                                                (
                                                                    _,
                                                                    itemIndex,
                                                                ) =>
                                                                    itemIndex !==
                                                                    index,
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

                                <TaxSummaryCard summary={totals} />

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
            </div>
        </AuthenticatedLayout>
    );
}

function previewTotals(form: InvoiceForm): TaxSummary {
    const lines = form.items.map((item) => ({
        grossTotal: Math.max(
            0,
            roundMoney(
                Number(item.quantity || 0) * Number(item.unit_price || 0) -
                    Number(item.discount_amount || 0),
            ),
        ),
        taxRate: Number(item.tax_rate || 0),
    }));
    const subtotal = roundMoney(
        lines.reduce((sum, line) => sum + line.grossTotal, 0),
    );
    const discount = Math.min(
        roundMoney(Number(form.discount_amount || 0)),
        roundMoney(subtotal),
    );
    let allocatedDiscount = 0;
    let tax = 0;
    const summaryLines = lines.map((line, index) => {
        const lineDiscount =
            discount > 0 && subtotal > 0
                ? index === lines.length - 1
                    ? roundMoney(discount - allocatedDiscount)
                    : roundMoney(discount * (line.grossTotal / subtotal))
                : 0;
        allocatedDiscount = roundMoney(allocatedDiscount + lineDiscount);
        const grossAfterDiscount = Math.max(
            0,
            roundMoney(line.grossTotal - lineDiscount),
        );
        let taxableBase = grossAfterDiscount;
        let lineTax = 0;

        if (form.tax_mode === 'exclusive') {
            lineTax = roundMoney(grossAfterDiscount * (line.taxRate / 100));
        } else if (form.tax_mode === 'inclusive' && line.taxRate > 0) {
            lineTax = roundMoney(
                grossAfterDiscount -
                    grossAfterDiscount / (1 + line.taxRate / 100),
            );
            taxableBase = roundMoney(grossAfterDiscount - lineTax);
        }

        if (form.tax_mode !== 'no_tax') {
            tax = roundMoney(tax + lineTax);
        }

        return {
            gross_total: line.grossTotal,
            allocated_header_discount: lineDiscount,
            gross_after_discount: grossAfterDiscount,
            taxable_base:
                form.tax_mode === 'no_tax' ? grossAfterDiscount : taxableBase,
            tax_amount: form.tax_mode === 'no_tax' ? 0 : lineTax,
            tax_rate: line.taxRate,
        };
    });
    const grossAfterDiscount = Math.max(0, roundMoney(subtotal - discount));
    const netSubtotal =
        form.tax_mode === 'inclusive'
            ? Math.max(0, roundMoney(grossAfterDiscount - tax))
            : grossAfterDiscount;
    const total = Math.max(
        0,
        form.tax_mode === 'exclusive'
            ? roundMoney(grossAfterDiscount + tax)
            : grossAfterDiscount,
    );

    return {
        mode: form.tax_mode,
        gross_subtotal: roundMoney(subtotal),
        header_discount: roundMoney(discount),
        gross_after_discount: grossAfterDiscount,
        net_subtotal: netSubtotal,
        taxable_base: netSubtotal,
        tax_amount: roundMoney(tax),
        total: roundMoney(total),
        wording:
            form.tax_mode === 'inclusive'
                ? 'Prices include VAT. VAT amount is shown for reporting.'
                : form.tax_mode === 'exclusive'
                  ? 'VAT is added on top of taxable base.'
                  : 'No VAT applied.',
        lines: summaryLines,
    };
}

function roundMoney(value: number) {
    return Math.round(value * 100) / 100;
}

function TaxSummaryCard({ summary }: { summary: TaxSummary }) {
    const rows =
        summary.mode === 'inclusive'
            ? [
                  ['Gross subtotal', summary.gross_subtotal],
                  ['Invoice discount', summary.header_discount],
                  ['Gross after discount', summary.gross_after_discount],
                  ['Net before VAT', summary.net_subtotal],
                  ['VAT included', summary.tax_amount],
                  ['Total', summary.total],
              ]
            : summary.mode === 'exclusive'
              ? [
                    ['Subtotal before tax', summary.gross_subtotal],
                    ['Invoice discount', summary.header_discount],
                    ['Taxable base', summary.taxable_base],
                    ['VAT', summary.tax_amount],
                    ['Total', summary.total],
                ]
              : [
                    ['Subtotal', summary.gross_subtotal],
                    ['Invoice discount', summary.header_discount],
                    ['Total', summary.total],
                ];

    return (
        <div className="space-y-2 rounded-md bg-slate-100 p-3 text-sm text-slate-800 dark:bg-slate-900 dark:text-white">
            <div className="flex items-center justify-between font-semibold">
                <span>Tax Summary</span>
                <span className="uppercase text-slate-500 dark:text-slate-300">
                    {summary.mode}
                </span>
            </div>
            <div className="text-xs text-slate-500 dark:text-slate-300">
                {summary.wording}
            </div>
            <div className="space-y-1">
                {rows.map(([label, value]) => (
                    <div
                        key={label}
                        className="flex items-center justify-between gap-3"
                    >
                        <span className="text-slate-500 dark:text-slate-300">
                            {label}
                        </span>
                        <span className="font-semibold">{money(value)}</span>
                    </div>
                ))}
            </div>
            {summary.lines.length > 0 && (
                <div className="border-t border-slate-200 pt-2 text-xs dark:border-slate-700">
                    {summary.lines.map((line, index) => (
                        <div
                            key={index}
                            className="flex flex-wrap justify-between gap-x-3 gap-y-1"
                        >
                            <span>Line {index + 1}</span>
                            <span>
                                Discount {money(line.allocated_header_discount)}
                            </span>
                            <span>Base {money(line.taxable_base)}</span>
                            <span>VAT {money(line.tax_amount)}</span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function PaymentHistory({
    payments,
    canReversePayments,
    onReverse,
}: {
    payments: Payment[];
    canReversePayments: boolean;
    onReverse: (payment: Payment) => void;
}) {
    const reversedIds = new Set(
        payments
            .filter((payment) => payment.entry_type === 'reversal')
            .map((payment) => payment.reversal_of_payment_id),
    );

    return (
        <div className="space-y-2 rounded-md border border-slate-200 p-3 text-sm dark:border-slate-800">
            <div className="font-semibold text-slate-800 dark:text-white">
                Payment history
            </div>
            {payments.map((payment) => {
                const isReversal = payment.entry_type === 'reversal';
                const isReversed = reversedIds.has(payment.id);

                return (
                    <div
                        key={payment.id}
                        className="flex items-center justify-between gap-3 text-slate-600 dark:text-slate-300"
                    >
                        <div>
                            <div className="font-medium text-slate-800 dark:text-white">
                                {isReversal ? 'Reversal' : 'Receipt'}{' '}
                                {money(payment.amount)}
                            </div>
                            <div className="text-xs text-slate-500 dark:text-slate-400">
                                {payment.payment_date?.slice(0, 10)}{' '}
                                {payment.payment_method}
                            </div>
                            {payment.attachment && (
                                <a
                                    href={route(
                                        'files.download',
                                        payment.attachment.id,
                                    )}
                                    className="text-xs font-semibold text-indigo-700 hover:underline dark:text-indigo-300"
                                >
                                    {payment.attachment.file_name}
                                </a>
                            )}
                        </div>
                        {!isReversal && canReversePayments && (
                            <SecondaryButton
                                type="button"
                                disabled={isReversed}
                                onClick={() => onReverse(payment)}
                            >
                                {isReversed ? 'Reversed' : 'Reverse'}
                            </SecondaryButton>
                        )}
                    </div>
                );
            })}
        </div>
    );
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
