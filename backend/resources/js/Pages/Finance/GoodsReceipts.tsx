import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useMemo } from 'react';

type Supplier = { id: string; name: string };
type PoItem = {
    id: string;
    description: string;
    product_id?: string | null;
    quantity: string;
    received_quantity: string;
    remaining_quantity: string;
    unit?: string | null;
    unit_price: string;
};
type PurchaseOrder = {
    id: string;
    po_no: string;
    status: string;
    supplier?: Supplier | null;
    items: PoItem[];
};
type GoodsReceipt = {
    id: string;
    grn_no: string;
    received_date: string;
    status: string;
    purchase_order?: PurchaseOrder | null;
    items: Array<{ id: string; description: string; quantity: string }>;
};
type StockSummary = {
    product_id?: string | null;
    on_hand: string;
    inventory_value: string;
    average_cost: string;
    product?: { sku?: string | null; name: string; unit?: string | null };
};
type Product = {
    id: string;
    sku?: string | null;
    name: string;
    unit?: string | null;
    cost?: string | null;
};
type StockMovement = {
    id: string;
    movement_type: string;
    movement_date: string;
    quantity: string;
    unit_cost: string;
    total_cost: string;
    note?: string | null;
    product?: { sku?: string | null; name: string; unit?: string | null };
};
type GrnForm = {
    purchase_order_id: string;
    received_date: string;
    note: string;
    items: Array<{ purchase_order_item_id: string; quantity: string }>;
};
type MovementForm = {
    product_id: string;
    movement_type: 'adjustment_in' | 'adjustment_out' | 'return_to_supplier';
    movement_date: string;
    quantity: string;
    unit_cost: string;
    note: string;
};

const today = new Date().toISOString().slice(0, 10);

export default function GoodsReceipts({
    goodsReceipts,
    purchaseOrders,
    products,
    stockSummary,
    stockMovements,
}: {
    goodsReceipts: GoodsReceipt[];
    purchaseOrders: PurchaseOrder[];
    products: Product[];
    stockSummary: StockSummary[];
    stockMovements: StockMovement[];
}) {
    const form = useForm<GrnForm>({
        purchase_order_id: '',
        received_date: today,
        note: '',
        items: [],
    });
    const movementForm = useForm<MovementForm>({
        product_id: '',
        movement_type: 'adjustment_in',
        movement_date: today,
        quantity: '',
        unit_cost: '',
        note: '',
    });
    const selectedPo = useMemo(
        () =>
            purchaseOrders.find((po) => po.id === form.data.purchase_order_id),
        [form.data.purchase_order_id, purchaseOrders],
    );

    const selectPo = (poId: string) => {
        const po = purchaseOrders.find((item) => item.id === poId);
        form.setData({
            ...form.data,
            purchase_order_id: poId,
            items:
                po?.items
                    .filter((item) => Number(item.remaining_quantity) > 0)
                    .map((item) => ({
                        purchase_order_item_id: item.id,
                        quantity: item.remaining_quantity,
                    })) ?? [],
        });
    };

    const setQty = (itemId: string, quantity: string) => {
        form.setData(
            'items',
            form.data.items.map((item) =>
                item.purchase_order_item_id === itemId
                    ? { ...item, quantity }
                    : item,
            ),
        );
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('goods-receipts.store'), {
            preserveScroll: true,
            onSuccess: () =>
                form.setData({
                    purchase_order_id: '',
                    received_date: today,
                    note: '',
                    items: [],
                }),
        });
    };

    const submitMovement = (event: FormEvent) => {
        event.preventDefault();
        movementForm.post(route('stock-movements.store'), {
            preserveScroll: true,
            onSuccess: () =>
                movementForm.setData({
                    product_id: '',
                    movement_type: 'adjustment_in',
                    movement_date: today,
                    quantity: '',
                    unit_cost: '',
                    note: '',
                }),
        });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Goods Receipts" />
            <div className="space-y-6">
                <PageHeader
                    title="Inventory / GRN"
                    description="Receive goods from approved purchase orders and post stock movements."
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_420px]">
                    <div className="space-y-6">
                        <Card title="Goods Receipts" description="Posted GRNs">
                            <DataTable
                                data={goodsReceipts}
                                keyExtractor={(row) => row.id}
                                columns={[
                                    {
                                        header: 'GRN',
                                        accessor: (row) => row.grn_no,
                                    },
                                    {
                                        header: 'PO',
                                        accessor: (row) =>
                                            row.purchase_order?.po_no ?? '-',
                                    },
                                    {
                                        header: 'Supplier',
                                        accessor: (row) =>
                                            row.purchase_order?.supplier
                                                ?.name ?? '-',
                                    },
                                    {
                                        header: 'Received',
                                        accessor: (row) =>
                                            row.received_date?.slice(0, 10),
                                    },
                                    {
                                        header: 'Items',
                                        accessor: (row) => row.items.length,
                                    },
                                ]}
                            />
                        </Card>

                        <Card
                            title="Stock Summary"
                            description="Movement-ledger on hand"
                        >
                            <DataTable
                                data={stockSummary}
                                keyExtractor={(row) =>
                                    row.product_id ?? 'service'
                                }
                                columns={[
                                    {
                                        header: 'Product',
                                        accessor: (row) => (
                                            <div>
                                                <div className="font-semibold">
                                                    {row.product?.name ??
                                                        'No product linked'}
                                                </div>
                                                <div className="text-xs text-slate-500">
                                                    {row.product?.sku ?? '-'}
                                                </div>
                                            </div>
                                        ),
                                    },
                                    {
                                        header: 'On Hand',
                                        accessor: (row) =>
                                            `${Number(row.on_hand).toLocaleString()} ${
                                                row.product?.unit ?? ''
                                            }`,
                                    },
                                    {
                                        header: 'Value',
                                        accessor: (row) =>
                                            money(row.inventory_value),
                                    },
                                    {
                                        header: 'Avg Cost',
                                        accessor: (row) =>
                                            money(row.average_cost),
                                    },
                                ]}
                            />
                        </Card>

                        <Card
                            title="Stock Movements"
                            description="Latest inventory ledger entries"
                        >
                            <DataTable
                                data={stockMovements}
                                keyExtractor={(row) => row.id}
                                columns={[
                                    {
                                        header: 'Date',
                                        accessor: (row) =>
                                            row.movement_date?.slice(0, 10),
                                    },
                                    {
                                        header: 'Product',
                                        accessor: (row) =>
                                            row.product?.name ?? '-',
                                    },
                                    {
                                        header: 'Type',
                                        accessor: (row) => row.movement_type,
                                    },
                                    {
                                        header: 'Qty',
                                        accessor: (row) =>
                                            `${Number(row.quantity).toLocaleString()} ${
                                                row.product?.unit ?? ''
                                            }`,
                                    },
                                    {
                                        header: 'Value',
                                        accessor: (row) =>
                                            money(row.total_cost),
                                    },
                                ]}
                            />
                        </Card>
                    </div>

                    <div className="space-y-6">
                        <Card
                            title="Receive Goods"
                            description="Approved PO only"
                        >
                            <form onSubmit={submit} className="space-y-4">
                                <select
                                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                    value={form.data.purchase_order_id}
                                    onChange={(event) =>
                                        selectPo(event.target.value)
                                    }
                                >
                                    <option value="">
                                        Select purchase order
                                    </option>
                                    {purchaseOrders.map((po) => (
                                        <option key={po.id} value={po.id}>
                                            {po.po_no} - {po.supplier?.name}
                                        </option>
                                    ))}
                                </select>
                                <TextInput
                                    type="date"
                                    value={form.data.received_date}
                                    onChange={(event) =>
                                        form.setData(
                                            'received_date',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                                {selectedPo && (
                                    <div className="space-y-3">
                                        {selectedPo.items.map((item) => {
                                            const selectedItem =
                                                form.data.items.find(
                                                    (row) =>
                                                        row.purchase_order_item_id ===
                                                        item.id,
                                                );

                                            return (
                                                <div
                                                    key={item.id}
                                                    className="rounded-lg border border-slate-200 p-3 text-sm dark:border-slate-800"
                                                >
                                                    <div className="font-semibold">
                                                        {item.description}
                                                    </div>
                                                    <div className="text-xs text-slate-500">
                                                        Ordered {item.quantity},
                                                        received{' '}
                                                        {item.received_quantity}
                                                        , remaining{' '}
                                                        {
                                                            item.remaining_quantity
                                                        }
                                                    </div>
                                                    <TextInput
                                                        type="number"
                                                        min="0"
                                                        step="0.0001"
                                                        max={
                                                            item.remaining_quantity
                                                        }
                                                        value={
                                                            selectedItem?.quantity ??
                                                            '0'
                                                        }
                                                        onChange={(event) =>
                                                            setQty(
                                                                item.id,
                                                                event.target
                                                                    .value,
                                                            )
                                                        }
                                                        className="mt-2 w-full"
                                                    />
                                                </div>
                                            );
                                        })}
                                    </div>
                                )}
                                <textarea
                                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                    placeholder="Note"
                                    value={form.data.note}
                                    onChange={(event) =>
                                        form.setData('note', event.target.value)
                                    }
                                />
                                <div className="flex justify-end gap-2">
                                    <SecondaryButton
                                        type="button"
                                        onClick={() => selectPo('')}
                                    >
                                        Clear
                                    </SecondaryButton>
                                    <PrimaryButton
                                        disabled={
                                            form.processing || !selectedPo
                                        }
                                    >
                                        Post GRN
                                    </PrimaryButton>
                                </div>
                            </form>
                        </Card>

                        <Card
                            title="Adjust Stock"
                            description="Manual adjustment or supplier return"
                        >
                            <form
                                onSubmit={submitMovement}
                                className="space-y-4"
                            >
                                <select
                                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                    value={movementForm.data.product_id}
                                    onChange={(event) =>
                                        movementForm.setData(
                                            'product_id',
                                            event.target.value,
                                        )
                                    }
                                >
                                    <option value="">Select product</option>
                                    {products.map((product) => (
                                        <option
                                            key={product.id}
                                            value={product.id}
                                        >
                                            {product.sku ?? '-'} -{' '}
                                            {product.name}
                                        </option>
                                    ))}
                                </select>
                                <select
                                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                    value={movementForm.data.movement_type}
                                    onChange={(event) =>
                                        movementForm.setData(
                                            'movement_type',
                                            event.target
                                                .value as MovementForm['movement_type'],
                                        )
                                    }
                                >
                                    <option value="adjustment_in">
                                        Adjustment In
                                    </option>
                                    <option value="adjustment_out">
                                        Adjustment Out
                                    </option>
                                    <option value="return_to_supplier">
                                        Return to Supplier
                                    </option>
                                </select>
                                <TextInput
                                    type="date"
                                    value={movementForm.data.movement_date}
                                    onChange={(event) =>
                                        movementForm.setData(
                                            'movement_date',
                                            event.target.value,
                                        )
                                    }
                                    className="w-full"
                                />
                                <div className="grid grid-cols-2 gap-2">
                                    <TextInput
                                        type="number"
                                        min="0.0001"
                                        step="0.0001"
                                        placeholder="Quantity"
                                        value={movementForm.data.quantity}
                                        onChange={(event) =>
                                            movementForm.setData(
                                                'quantity',
                                                event.target.value,
                                            )
                                        }
                                    />
                                    <TextInput
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="Unit cost"
                                        value={movementForm.data.unit_cost}
                                        onChange={(event) =>
                                            movementForm.setData(
                                                'unit_cost',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <textarea
                                    className="block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                    placeholder="Note"
                                    value={movementForm.data.note}
                                    onChange={(event) =>
                                        movementForm.setData(
                                            'note',
                                            event.target.value,
                                        )
                                    }
                                />
                                <div className="flex justify-end">
                                    <PrimaryButton
                                        disabled={
                                            movementForm.processing ||
                                            !movementForm.data.product_id
                                        }
                                    >
                                        Post Movement
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
