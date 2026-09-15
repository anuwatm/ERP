import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorField,
    EditorChoice,
    EditorReference,
} from '@/Components/UI/EditorFields';
import EditorLineItems from '@/Components/UI/EditorLineItems';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { PageProps } from '@/Types';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type Customer = { id: string; customer_code: string; company_name: string };
type Deal = { id: string; title: string; customer_id: string };
type Product = {
    id: string;
    sku: string;
    name: string;
    unit?: string | null;
    price: string;
};
type Quotation = {
    id: string;
    quotation_no: string;
    status: string;
    issue_date: string;
    valid_until?: string | null;
    total: string;
    currency: string;
    customer?: Customer | null;
    deal?: Deal | null;
    converted_invoice?: { id: string; invoice_no: string } | null;
};
type QuotationForm = {
    customer_id: string;
    deal_id: string;
    status: string;
    tax_mode: string;
    issue_date: string;
    valid_until: string;
    discount_amount: string;
    currency: string;
    notes: string;
    items: Array<{
        product_id: string;
        description: string;
        quantity: string;
        unit: string;
        unit_price: string;
        discount_amount: string;
        tax_rate: string;
    }>;
};

const today = new Date().toISOString().slice(0, 10);
const emptyItem = {
    product_id: '',
    description: '',
    quantity: '1',
    unit: '',
    unit_price: '0.00',
    discount_amount: '0.00',
    tax_rate: '7.00',
};
const emptyForm: QuotationForm = {
    customer_id: '',
    deal_id: '',
    status: 'draft',
    tax_mode: 'exclusive',
    issue_date: today,
    valid_until: '',
    discount_amount: '0.00',
    currency: 'THB',
    notes: '',
    items: [{ ...emptyItem }],
};

export default function Quotations({
    quotations,
    customers,
    deals,
    products,
    taxModes,
}: {
    quotations: Quotation[];
    customers: Customer[];
    deals: Deal[];
    products: Product[];
    statuses: string[];
    taxModes: string[];
}) {
    const form = useForm<QuotationForm>(emptyForm);

    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const [rowKeys, setRowKeys] = useState([crypto.randomUUID()]);
    const { auth } = usePage<PageProps>().props;
    const canCreate = auth.permissions.includes('quotations.create');
    const guard = useDiscardGuard(open && form.isDirty, form.processing);
    const submission = useSubmissionKey();
    const close = () => {
        setOpen(false);
        setMinimized(false);
        form.setData({ ...emptyForm, items: [{ ...emptyItem }] });
        form.clearErrors();
        setRowKeys([crypto.randomUUID()]);
        submission.reset();
    };
    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canCreate || form.processing) return;
        form.post(route('quotations.store'), {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: close,
        });
    };

    const setItem = (
        index: number,
        key: keyof QuotationForm['items'][number],
        value: string,
    ) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    };

    const selectProduct = (index: number, productId: string) => {
        const product = products.find((item) => item.id === productId);
        const items = [...form.data.items];
        items[index] = {
            ...items[index],
            product_id: productId,
            description: product?.name ?? items[index].description,
            unit: product?.unit ?? '',
            unit_price: product?.price ?? items[index].unit_price,
        };
        form.setData('items', items);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Quotations" />
            <div className="space-y-6">
                <PageHeader
                    title="Quotations"
                    actions={
                        canCreate && (
                            <PrimaryButton
                                disabled={open}
                                onClick={() => setOpen(true)}
                            >
                                + Create Quotation
                            </PrimaryButton>
                        )
                    }
                    description="Sales offers before invoice conversion."
                />
                <div className="space-y-6">
                    <Card
                        title="Quotation List"
                        description="Commercial documents"
                    >
                        <DataTable
                            data={quotations}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'Quotation',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-mono font-semibold text-slate-900 dark:text-white">
                                                {row.quotation_no}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {row.customer?.company_name}
                                            </div>
                                            {row.converted_invoice && (
                                                <div className="text-xs text-emerald-600">
                                                    Invoice:{' '}
                                                    {
                                                        row.converted_invoice
                                                            .invoice_no
                                                    }
                                                </div>
                                            )}
                                        </div>
                                    ),
                                },
                                {
                                    header: 'Status',
                                    accessor: (row) => (
                                        <Badge size="sm">{row.status}</Badge>
                                    ),
                                },
                                {
                                    header: 'Issue',
                                    accessor: (row) =>
                                        row.issue_date?.slice(0, 10),
                                },
                                {
                                    header: 'Total',
                                    accessor: (row) =>
                                        `${money(row.total)} ${row.currency}`,
                                },
                                {
                                    header: 'Actions',
                                    accessor: (row) => (
                                        <div className="flex flex-wrap gap-2">
                                            {['draft', 'sent'].includes(
                                                row.status,
                                            ) && (
                                                <>
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'quotations.approve',
                                                                    row.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Approve
                                                    </SecondaryButton>
                                                    <SecondaryButton
                                                        type="button"
                                                        onClick={() =>
                                                            router.post(
                                                                route(
                                                                    'quotations.reject',
                                                                    row.id,
                                                                ),
                                                                {},
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </SecondaryButton>
                                                </>
                                            )}
                                            {row.status === 'approved' && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'quotations.convert-to-invoice',
                                                                row.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Convert
                                                </SecondaryButton>
                                            )}
                                        </div>
                                    ),
                                },
                            ]}
                        />
                    </Card>
                    <DiscardChangesDialog
                        open={guard.confirming}
                        onKeep={guard.keep}
                        onDiscard={guard.discard}
                    />
                    <CreateEditDialog
                        open={open}
                        minimized={minimized}
                        onMinimize={setMinimized}
                        onClose={() => guard.request(close)}
                        title="Create Quotation"
                        breadcrumb="Finance / Quotation"
                        entityLabel="Quotation"
                        draftLabel="New Quotation"
                        storageKey="quotations-editor-size"
                        formId="quotations-editor"
                        processing={form.processing}
                        saveDisabled={!canCreate}
                        saveLabel="Save Quotation"
                        status="Save a draft; server calculates taxes and totals"
                        errors={form.errors}
                        onSubmit={submit}
                    >
                        {(expanded) => (
                            <div className="space-y-6 p-4 sm:p-6">
                                <section
                                    aria-label="Document details"
                                    className={
                                        expanded
                                            ? 'grid gap-4 sm:grid-cols-2 lg:grid-cols-3'
                                            : 'grid gap-4 sm:grid-cols-2'
                                    }
                                >
                                    <EditorReference
                                        id="quotation-customer"
                                        label="Customer"
                                        value={form.data.customer_id}
                                        error={form.errors.customer_id}
                                        onChange={(v) =>
                                            form.setData((data) => ({
                                                ...data,
                                                customer_id: v,
                                                deal_id: '',
                                            }))
                                        }
                                        options={customers.map((c) => ({
                                            id: c.id,
                                            label:
                                                c.customer_code +
                                                ' - ' +
                                                c.company_name,
                                        }))}
                                        required
                                    />
                                    <EditorReference
                                        id="quotation-deal"
                                        label="Deal"
                                        value={form.data.deal_id}
                                        error={form.errors.deal_id}
                                        onChange={(v) =>
                                            form.setData('deal_id', v)
                                        }
                                        options={deals
                                            .filter(
                                                (d) =>
                                                    !form.data.customer_id ||
                                                    d.customer_id ===
                                                        form.data.customer_id,
                                            )
                                            .map((d) => ({
                                                id: d.id,
                                                label: d.title,
                                            }))}
                                    />
                                    <EditorField
                                        id="quotations-date"
                                        label="Issue Date"
                                        type="date"
                                        value={form.data.issue_date}
                                        error={form.errors.issue_date}
                                        onChange={(v) =>
                                            form.setData('issue_date', v)
                                        }
                                        required
                                    />
                                    <EditorField
                                        id="quotations-until"
                                        label="Valid Until"
                                        type="date"
                                        value={form.data.valid_until}
                                        error={form.errors.valid_until}
                                        onChange={(v) =>
                                            form.setData('valid_until', v)
                                        }
                                    />
                                    <EditorChoice
                                        id="quotations-tax"
                                        label="Tax Mode"
                                        value={form.data.tax_mode}
                                        error={form.errors.tax_mode}
                                        onChange={(v) =>
                                            form.setData('tax_mode', v)
                                        }
                                        options={taxModes}
                                    />
                                    <EditorField
                                        id="quotations-discount"
                                        label="Document Discount"
                                        type="number"
                                        min={0}
                                        step="0.01"
                                        value={form.data.discount_amount}
                                        error={form.errors.discount_amount}
                                        onChange={(v) =>
                                            form.setData('discount_amount', v)
                                        }
                                    />
                                    <EditorField
                                        id="quotations-currency"
                                        label="Currency"
                                        value={form.data.currency}
                                        error={form.errors.currency}
                                        onChange={(v) =>
                                            form.setData('currency', v)
                                        }
                                        maxLength={3}
                                        required
                                    />
                                    <EditorField
                                        id="quotations-note"
                                        label="Notes"
                                        value={form.data.notes}
                                        error={form.errors.notes}
                                        onChange={(v) =>
                                            form.setData('notes', v)
                                        }
                                        multiline
                                    />
                                </section>
                                <EditorLineItems
                                    rows={form.data.items.map(
                                        (item, index) => ({
                                            ...item,
                                            key: rowKeys[index],
                                        }),
                                    )}
                                    rowKey={(item) => item.key}
                                    columns={[
                                        { key: 'product_id', label: 'Product' },
                                        {
                                            key: 'description',
                                            label: 'Description',
                                        },
                                        { key: 'quantity', label: 'Quantity' },
                                        { key: 'unit', label: 'Unit' },
                                        {
                                            key: 'unit_price',
                                            label: 'Unit Price',
                                        },
                                        {
                                            key: 'discount_amount',
                                            label: 'Discount',
                                        },
                                        { key: 'tax_rate', label: 'Tax %' },
                                    ]}
                                    errors={form.errors}
                                    disabled={form.processing}
                                    onAdd={() => {
                                        setRowKeys([
                                            ...rowKeys,
                                            crypto.randomUUID(),
                                        ]);
                                        form.setData('items', [
                                            ...form.data.items,
                                            { ...emptyItem },
                                        ]);
                                    }}
                                    onRemove={(index) => {
                                        setRowKeys(
                                            rowKeys.filter(
                                                (_, i) => i !== index,
                                            ),
                                        );
                                        form.setData(
                                            'items',
                                            form.data.items.filter(
                                                (_, i) => i !== index,
                                            ),
                                        );
                                        form.clearErrors();
                                    }}
                                    renderCell={(
                                        item,
                                        index,
                                        field,
                                        errorId,
                                    ) =>
                                        field === 'product_id' ? (
                                            <EditorReference
                                                id={
                                                    'quotation-product-' +
                                                    item.key
                                                }
                                                label={
                                                    'Line ' +
                                                    (index + 1) +
                                                    ' Product'
                                                }
                                                value={item.product_id}
                                                onChange={(v) =>
                                                    selectProduct(index, v)
                                                }
                                                options={products.map((p) => ({
                                                    id: p.id,
                                                    label:
                                                        p.sku + ' - ' + p.name,
                                                }))}
                                            />
                                        ) : (
                                            <input
                                                aria-label={
                                                    'Line ' +
                                                    (index + 1) +
                                                    ' ' +
                                                    field.replaceAll('_', ' ')
                                                }
                                                aria-invalid={!!errorId}
                                                aria-describedby={errorId}
                                                className="w-full min-w-24 rounded-md border-gray-300 bg-white text-gray-900 dark:bg-gray-900 dark:text-white"
                                                type={
                                                    [
                                                        'quantity',
                                                        'unit_price',
                                                        'discount_amount',
                                                        'tax_rate',
                                                    ].includes(field)
                                                        ? 'number'
                                                        : 'text'
                                                }
                                                min={
                                                    field === 'quantity'
                                                        ? 0.01
                                                        : 0
                                                }
                                                step="0.01"
                                                value={
                                                    item[
                                                        field as keyof typeof emptyItem
                                                    ]
                                                }
                                                onChange={(e) =>
                                                    setItem(
                                                        index,
                                                        field as keyof typeof emptyItem,
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        )
                                    }
                                />
                            </div>
                        )}
                    </CreateEditDialog>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
