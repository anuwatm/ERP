import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/UI/Card';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

type Bin = { id: string; code: string; name: string | null };
type Warehouse = { id: string; code: string; name: string; bins: Bin[] };
type Product = {
    id: string;
    sku: string | null;
    barcode: string | null;
    name: string;
    unit: string;
    reorder_point: string;
};
type Lot = {
    id: string;
    product_id: string;
    lot_no: string;
    expires_at: string | null;
};

const fieldClass =
    'block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900';
const today = new Date().toISOString().slice(0, 10);

export default function InventoryOperations({
    warehouses,
    products,
    lots,
    lowStock,
}: {
    warehouses: Warehouse[];
    products: Product[];
    lots: Lot[];
    lowStock: Product[];
}) {
    const [scanCode, setScanCode] = useState('');
    const [scanMessage, setScanMessage] = useState('');
    const warehouse = useForm({ code: '', name: '' });
    const bin = useForm({ warehouse_id: '', code: '', name: '' });
    const lot = useForm({
        product_id: '',
        lot_no: '',
        manufactured_at: '',
        expires_at: '',
        barcode: '',
    });
    const transfer = useForm({
        product_id: '',
        source_warehouse_id: '',
        destination_warehouse_id: '',
        inventory_lot_id: '',
        quantity: '',
        transfer_date: today,
        note: '',
    });
    const count = useForm({
        warehouse_id: '',
        product_id: '',
        counted_quantity: '',
        count_date: today,
    });
    const selectedLots = useMemo(
        () =>
            lots.filter((item) => item.product_id === transfer.data.product_id),
        [lots, transfer.data.product_id],
    );

    const lookup = () => {
        const product = products.find(
            (item) => item.barcode === scanCode || item.sku === scanCode,
        );
        if (!product) {
            setScanMessage('ไม่พบ Barcode / SKU');
            return;
        }
        transfer.setData('product_id', product.id);
        count.setData('product_id', product.id);
        lot.setData('product_id', product.id);
        setScanMessage(`${product.name} (${product.unit})`);
    };

    return (
        <AuthenticatedLayout>
            <Head title="Inventory Operations" />
            <div className="space-y-6">
                <PageHeader
                    title="Inventory Operations"
                    description="Warehouse, bin, lot, barcode, transfer, stock count and reorder control."
                />
                <Card title="Barcode Scanner">
                    <div className="flex flex-col gap-3 sm:flex-row">
                        <TextInput
                            value={scanCode}
                            onChange={(event) =>
                                setScanCode(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    lookup();
                                }
                            }}
                            placeholder="Scan barcode or enter SKU"
                            className="w-full"
                        />
                        <PrimaryButton type="button" onClick={lookup}>
                            Lookup
                        </PrimaryButton>
                    </div>
                    {scanMessage && (
                        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            {scanMessage}
                        </p>
                    )}
                </Card>
                <div className="grid gap-6 xl:grid-cols-3">
                    <Card title="Warehouse">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                warehouse.post(route('warehouses.store'), {
                                    preserveScroll: true,
                                    onSuccess: () => warehouse.reset(),
                                });
                            }}
                            className="space-y-3"
                        >
                            <TextInput
                                value={warehouse.data.code}
                                onChange={(event) =>
                                    warehouse.setData(
                                        'code',
                                        event.target.value,
                                    )
                                }
                                placeholder="WH-01"
                                required
                            />
                            <TextInput
                                value={warehouse.data.name}
                                onChange={(event) =>
                                    warehouse.setData(
                                        'name',
                                        event.target.value,
                                    )
                                }
                                placeholder="Warehouse name"
                                required
                            />
                            <PrimaryButton disabled={warehouse.processing}>
                                Create
                            </PrimaryButton>
                        </form>
                    </Card>
                    <Card title="Warehouse Bin">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                if (bin.data.warehouse_id)
                                    bin.post(
                                        route(
                                            'warehouses.bins.store',
                                            bin.data.warehouse_id,
                                        ),
                                        {
                                            preserveScroll: true,
                                            onSuccess: () =>
                                                bin.reset('code', 'name'),
                                        },
                                    );
                            }}
                            className="space-y-3"
                        >
                            <WarehouseSelect
                                warehouses={warehouses}
                                value={bin.data.warehouse_id}
                                onChange={(value) =>
                                    bin.setData('warehouse_id', value)
                                }
                                label="Warehouse"
                            />
                            <TextInput
                                value={bin.data.code}
                                onChange={(event) =>
                                    bin.setData('code', event.target.value)
                                }
                                placeholder="A-01"
                                required
                            />
                            <TextInput
                                value={bin.data.name}
                                onChange={(event) =>
                                    bin.setData('name', event.target.value)
                                }
                                placeholder="Bin name"
                            />
                            <PrimaryButton disabled={bin.processing}>
                                Create bin
                            </PrimaryButton>
                        </form>
                    </Card>
                    <Card title="Lot / Expiry">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                lot.post(route('inventory-lots.store'), {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        lot.reset(
                                            'lot_no',
                                            'manufactured_at',
                                            'expires_at',
                                            'barcode',
                                        ),
                                });
                            }}
                            className="space-y-3"
                        >
                            <ProductSelect
                                products={products}
                                value={lot.data.product_id}
                                onChange={(value) =>
                                    lot.setData('product_id', value)
                                }
                            />
                            <TextInput
                                value={lot.data.lot_no}
                                onChange={(event) =>
                                    lot.setData('lot_no', event.target.value)
                                }
                                placeholder="Lot no."
                                required
                            />
                            <TextInput
                                type="date"
                                value={lot.data.manufactured_at}
                                onChange={(event) =>
                                    lot.setData(
                                        'manufactured_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <TextInput
                                type="date"
                                value={lot.data.expires_at}
                                onChange={(event) =>
                                    lot.setData(
                                        'expires_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <TextInput
                                value={lot.data.barcode}
                                onChange={(event) =>
                                    lot.setData('barcode', event.target.value)
                                }
                                placeholder="Lot barcode"
                            />
                            <PrimaryButton disabled={lot.processing}>
                                Create lot
                            </PrimaryButton>
                        </form>
                    </Card>
                </div>
                <div className="grid gap-6 xl:grid-cols-2">
                    <Card title="Stock Transfer">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                transfer.post(route('stock-transfers.store'), {
                                    preserveScroll: true,
                                    onSuccess: () =>
                                        transfer.reset(
                                            'product_id',
                                            'source_warehouse_id',
                                            'destination_warehouse_id',
                                            'inventory_lot_id',
                                            'quantity',
                                            'note',
                                        ),
                                });
                            }}
                            className="space-y-3"
                        >
                            <ProductSelect
                                products={products}
                                value={transfer.data.product_id}
                                onChange={(value) =>
                                    transfer.setData('product_id', value)
                                }
                            />
                            <select
                                className={fieldClass}
                                value={transfer.data.inventory_lot_id}
                                onChange={(event) =>
                                    transfer.setData(
                                        'inventory_lot_id',
                                        event.target.value,
                                    )
                                }
                            >
                                <option value="">No lot</option>
                                {selectedLots.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.lot_no}
                                        {item.expires_at
                                            ? ` · expires ${item.expires_at}`
                                            : ''}
                                    </option>
                                ))}
                            </select>
                            <WarehouseSelect
                                warehouses={warehouses}
                                value={transfer.data.source_warehouse_id}
                                onChange={(value) =>
                                    transfer.setData(
                                        'source_warehouse_id',
                                        value,
                                    )
                                }
                                label="From warehouse"
                            />
                            <WarehouseSelect
                                warehouses={warehouses}
                                value={transfer.data.destination_warehouse_id}
                                onChange={(value) =>
                                    transfer.setData(
                                        'destination_warehouse_id',
                                        value,
                                    )
                                }
                                label="To warehouse"
                            />
                            <TextInput
                                type="number"
                                min="0.0001"
                                step="0.0001"
                                value={transfer.data.quantity}
                                onChange={(event) =>
                                    transfer.setData(
                                        'quantity',
                                        event.target.value,
                                    )
                                }
                                placeholder="Quantity"
                                required
                            />
                            <TextInput
                                type="date"
                                value={transfer.data.transfer_date}
                                onChange={(event) =>
                                    transfer.setData(
                                        'transfer_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <PrimaryButton disabled={transfer.processing}>
                                Transfer stock
                            </PrimaryButton>
                        </form>
                    </Card>
                    <Card title="Post Stock Count">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                count.transform((data) => ({
                                    warehouse_id: data.warehouse_id,
                                    count_date: data.count_date,
                                    items: [
                                        {
                                            product_id: data.product_id,
                                            counted_quantity:
                                                data.counted_quantity,
                                        },
                                    ],
                                }));
                                count.post(route('stock-counts.store'), {
                                    preserveScroll: true,
                                });
                            }}
                            className="space-y-3"
                        >
                            <WarehouseSelect
                                warehouses={warehouses}
                                value={count.data.warehouse_id}
                                onChange={(value) =>
                                    count.setData('warehouse_id', value)
                                }
                                label="Warehouse"
                            />
                            <ProductSelect
                                products={products}
                                value={count.data.product_id}
                                onChange={(value) =>
                                    count.setData('product_id', value)
                                }
                            />
                            <TextInput
                                type="number"
                                min="0"
                                step="0.0001"
                                value={count.data.counted_quantity}
                                onChange={(event) =>
                                    count.setData(
                                        'counted_quantity',
                                        event.target.value,
                                    )
                                }
                                placeholder="Counted quantity"
                                required
                            />
                            <TextInput
                                type="date"
                                value={count.data.count_date}
                                onChange={(event) =>
                                    count.setData(
                                        'count_date',
                                        event.target.value,
                                    )
                                }
                                required
                            />
                            <PrimaryButton disabled={count.processing}>
                                Post count
                            </PrimaryButton>
                        </form>
                    </Card>
                </div>
                <Card title="Low Stock">
                    <div className="divide-y divide-slate-200 text-sm dark:divide-slate-700">
                        {lowStock.length ? (
                            lowStock.map((product) => (
                                <div
                                    key={product.id}
                                    className="flex justify-between py-3"
                                >
                                    <span>{product.name}</span>
                                    <span>
                                        Reorder point {product.reorder_point}{' '}
                                        {product.unit}
                                    </span>
                                </div>
                            ))
                        ) : (
                            <div className="py-3">No low-stock items.</div>
                        )}
                    </div>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}

function ProductSelect({
    products,
    value,
    onChange,
}: {
    products: Product[];
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <select
            className={fieldClass}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            required
        >
            <option value="">Product</option>
            {products.map((product) => (
                <option key={product.id} value={product.id}>
                    {product.barcode || product.sku || '-'} · {product.name}
                </option>
            ))}
        </select>
    );
}
function WarehouseSelect({
    warehouses,
    value,
    onChange,
    label,
}: {
    warehouses: Warehouse[];
    value: string;
    onChange: (value: string) => void;
    label: string;
}) {
    return (
        <select
            className={fieldClass}
            value={value}
            onChange={(event) => onChange(event.target.value)}
            required
        >
            <option value="">{label}</option>
            {warehouses.map((warehouse) => (
                <option key={warehouse.id} value={warehouse.id}>
                    {warehouse.code} · {warehouse.name}
                </option>
            ))}
        </select>
    );
}
