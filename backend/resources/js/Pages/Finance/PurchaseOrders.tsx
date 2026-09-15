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

type Supplier = { id: string; supplier_code: string; name: string };
type PurchaseOrder = {
    id: string;
    po_no: string;
    status: string;
    order_date: string;
    total: string;
    currency: string;
    supplier?: Supplier | null;
};
type PurchaseOrderForm = {
    supplier_id: string;
    status: string;
    order_date: string;
    expected_date: string;
    tax_mode: string;
    discount_amount: string;
    currency: string;
    note: string;
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
const emptyForm: PurchaseOrderForm = {
    supplier_id: '',
    status: 'draft',
    order_date: today,
    expected_date: '',
    tax_mode: 'exclusive',
    discount_amount: '0.00',
    currency: 'THB',
    note: '',
    items: [{ ...emptyItem }],
};

export default function PurchaseOrders({
    purchaseOrders,
    suppliers,
    taxModes,
}: {
    purchaseOrders: PurchaseOrder[];
    suppliers: Supplier[];
    taxModes: string[];
}) {
    const form = useForm<PurchaseOrderForm>(emptyForm);

    const [open, setOpen] = useState(false);
    const [minimized, setMinimized] = useState(false);
    const [rowKeys, setRowKeys] = useState([crypto.randomUUID()]);
    const { auth } = usePage<PageProps>().props;
    const canCreate = auth.permissions.includes('purchase_orders.create');
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
        form.post(route('purchase-orders.store'), {
            preserveScroll: true,
            headers: submission.headers(),
            onSuccess: close,
        });
    };

    const setItem = (
        index: number,
        key: keyof PurchaseOrderForm['items'][number],
        value: string,
    ) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Purchase Orders" />
            <div className="space-y-6">
                <PageHeader
                    title="Purchase Orders"
                    actions={
                        canCreate && (
                            <PrimaryButton
                                disabled={open}
                                onClick={() => setOpen(true)}
                            >
                                + Create Purchase Order
                            </PrimaryButton>
                        )
                    }
                    description="Supplier purchase commitments with server-calculated totals."
                />
                <div className="space-y-6">
                    <Card title="PO List" description="Procurement documents">
                        <DataTable
                            data={purchaseOrders}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'PO',
                                    accessor: (row) => (
                                        <div>
                                            <div className="font-mono font-semibold text-slate-900 dark:text-white">
                                                {row.po_no}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {row.supplier?.name}
                                            </div>
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
                                    header: 'Order Date',
                                    accessor: (row) =>
                                        row.order_date?.slice(0, 10),
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
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    window.open(
                                                        route(
                                                            'purchase-orders.print',
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
                                                            'purchase-orders.pdf',
                                                            row.id,
                                                        ),
                                                        '_blank',
                                                        'noopener,noreferrer',
                                                    )
                                                }
                                            >
                                                PDF
                                            </SecondaryButton>
                                            {['draft', 'sent'].includes(
                                                row.status,
                                            ) && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'workflows.submit',
                                                                [
                                                                    'purchase_order',
                                                                    row.id,
                                                                ],
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Submit Approval
                                                </SecondaryButton>
                                            )}
                                            {['draft', 'sent'].includes(
                                                row.status,
                                            ) && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'purchase-orders.approve',
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
                                            )}
                                            {![
                                                'received',
                                                'closed',
                                                'cancelled',
                                            ].includes(row.status) && (
                                                <SecondaryButton
                                                    type="button"
                                                    onClick={() =>
                                                        router.post(
                                                            route(
                                                                'purchase-orders.cancel',
                                                                row.id,
                                                            ),
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    Cancel
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
                        title="Create Purchase Order"
                        breadcrumb="Finance / Purchase Order"
                        entityLabel="Purchase Order"
                        draftLabel="New Purchase Order"
                        storageKey="purchase-orders-editor-size"
                        formId="purchase-orders-editor"
                        processing={form.processing}
                        saveDisabled={!canCreate}
                        saveLabel="Save Purchase Order"
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
                                        id="po-supplier"
                                        label="Supplier"
                                        value={form.data.supplier_id}
                                        error={form.errors.supplier_id}
                                        onChange={(v) =>
                                            form.setData('supplier_id', v)
                                        }
                                        options={suppliers.map((s) => ({
                                            id: s.id,
                                            label:
                                                s.supplier_code +
                                                ' - ' +
                                                s.name,
                                        }))}
                                        required
                                    />
                                    <EditorField
                                        id="purchase-orders-date"
                                        label="Order Date"
                                        type="date"
                                        value={form.data.order_date}
                                        error={form.errors.order_date}
                                        onChange={(v) =>
                                            form.setData('order_date', v)
                                        }
                                        required
                                    />
                                    <EditorField
                                        id="purchase-orders-until"
                                        label="Expected Date"
                                        type="date"
                                        value={form.data.expected_date}
                                        error={form.errors.expected_date}
                                        onChange={(v) =>
                                            form.setData('expected_date', v)
                                        }
                                    />
                                    <EditorChoice
                                        id="purchase-orders-tax"
                                        label="Tax Mode"
                                        value={form.data.tax_mode}
                                        error={form.errors.tax_mode}
                                        onChange={(v) =>
                                            form.setData('tax_mode', v)
                                        }
                                        options={taxModes}
                                    />
                                    <EditorField
                                        id="purchase-orders-discount"
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
                                        id="purchase-orders-currency"
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
                                        id="purchase-orders-note"
                                        label="Notes"
                                        value={form.data.note}
                                        error={form.errors.note}
                                        onChange={(v) =>
                                            form.setData('note', v)
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
                                    ) => (
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
                                                field === 'quantity' ? 0.01 : 0
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
                                    )}
                                />
                            </div>
                        )}
                    </CreateEditDialog>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
