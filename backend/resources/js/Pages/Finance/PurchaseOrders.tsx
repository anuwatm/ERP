import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Badge from '@/Components/UI/Badge';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, router, useForm } from '@inertiajs/react';
import { FormEvent } from 'react';

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

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('purchase-orders.store'), {
            preserveScroll: true,
            onSuccess: () => form.setData(emptyForm),
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
                    description="Supplier purchase commitments with server-calculated totals."
                />
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_430px]">
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
                    <Card
                        title="New Purchase Order"
                        description="Draft or sent"
                    >
                        <form onSubmit={submit} className="space-y-3">
                            <select
                                className="block w-full rounded-md border-slate-300 text-sm shadow-sm"
                                value={form.data.supplier_id}
                                onChange={(event) =>
                                    form.setData(
                                        'supplier_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">Select supplier</option>
                                {suppliers.map((supplier) => (
                                    <option
                                        key={supplier.id}
                                        value={supplier.id}
                                    >
                                        {supplier.supplier_code} -{' '}
                                        {supplier.name}
                                    </option>
                                ))}
                            </select>
                            <div className="grid grid-cols-2 gap-2">
                                <TextInput
                                    type="date"
                                    value={form.data.order_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'order_date',
                                            event.target.value,
                                        )
                                    }
                                />
                                <select
                                    className="rounded-md border-slate-300 text-sm shadow-sm"
                                    value={form.data.tax_mode}
                                    onChange={(event) =>
                                        form.setData(
                                            'tax_mode',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {taxModes.map((mode) => (
                                        <option key={mode} value={mode}>
                                            {mode}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            {form.data.items.map((item, index) => (
                                <div
                                    key={index}
                                    className="space-y-2 rounded-md bg-slate-50 p-3 dark:bg-slate-900"
                                >
                                    <TextInput
                                        className="block w-full"
                                        placeholder="Description"
                                        value={item.description}
                                        onChange={(event) =>
                                            setItem(
                                                index,
                                                'description',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <div className="grid grid-cols-2 gap-2">
                                        <TextInput
                                            type="number"
                                            value={item.quantity}
                                            onChange={(event) =>
                                                setItem(
                                                    index,
                                                    'quantity',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                        <TextInput
                                            type="number"
                                            value={item.unit_price}
                                            onChange={(event) =>
                                                setItem(
                                                    index,
                                                    'unit_price',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            ))}
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    form.setData('items', [
                                        ...form.data.items,
                                        { ...emptyItem },
                                    ])
                                }
                            >
                                Add Line
                            </SecondaryButton>
                            <div className="flex justify-end">
                                <PrimaryButton disabled={form.processing}>
                                    Save PO
                                </PrimaryButton>
                            </div>
                        </form>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
