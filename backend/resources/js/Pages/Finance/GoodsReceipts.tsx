import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { money } from '@/Utils/format';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import ReceiveGoodsDialog from './ReceiveGoodsDialog';
import StockMovementDialog from './StockMovementDialog';

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
    barcode?: string | null;
    name: string;
    unit?: string | null;
    cost?: string | null;
};
type Warehouse = { id: string; code: string; name: string };
type InventoryLot = {
    id: string;
    product_id: string;
    lot_no: string;
    expires_at?: string | null;
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

export default function GoodsReceipts({
    goodsReceipts,
    purchaseOrders,
    products,
    stockSummary,
    stockMovements,
    warehouses,
    inventoryLots,
}: {
    goodsReceipts: GoodsReceipt[];
    purchaseOrders: PurchaseOrder[];
    products: Product[];
    stockSummary: StockSummary[];
    stockMovements: StockMovement[];
    warehouses: Warehouse[];
    inventoryLots: InventoryLot[];
}) {
    const [scanCode, setScanCode] = useState('');
    const [scanResult, setScanResult] = useState('');
    const [matchedProduct, setMatchedProduct] = useState<Product | null>(null);

    const [receiveGoodsOpen, setReceiveGoodsOpen] = useState(false);
    const [stockMovementOpen, setStockMovementOpen] = useState(false);
    const [movementProductId, setMovementProductId] = useState('');

    const handleScan = () => {
        const product = products.find(
            (item) => item.barcode === scanCode || item.sku === scanCode,
        );
        if (product) {
            setMatchedProduct(product);
            setScanResult(
                `Found: ${product.name} (SKU: ${product.sku ?? '-'})`,
            );
        } else {
            setMatchedProduct(null);
            setScanResult('Barcode / SKU not found.');
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Goods Receipts" />
            <div className="space-y-6">
                <PageHeader
                    title="Inventory / GRN"
                    description="Receive goods from approved purchase orders and post stock movements."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <SecondaryButton
                                type="button"
                                onClick={() => {
                                    setMovementProductId('');
                                    setStockMovementOpen(true);
                                }}
                            >
                                + Stock Adjustment
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                onClick={() => setReceiveGoodsOpen(true)}
                            >
                                + Receive Goods (GRN)
                            </PrimaryButton>
                        </div>
                    }
                />

                <ReceiveGoodsDialog
                    open={receiveGoodsOpen}
                    purchaseOrders={purchaseOrders}
                    warehouses={warehouses}
                    inventoryLots={inventoryLots}
                    onClose={() => setReceiveGoodsOpen(false)}
                />

                <StockMovementDialog
                    open={stockMovementOpen}
                    products={products}
                    warehouses={warehouses}
                    initialProductId={movementProductId}
                    onClose={() => {
                        setStockMovementOpen(false);
                        setMovementProductId('');
                    }}
                />

                <Card title="Barcode Scanner">
                    <div className="flex flex-col gap-2 sm:flex-row">
                        <TextInput
                            value={scanCode}
                            onChange={(event) =>
                                setScanCode(event.target.value)
                            }
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    event.preventDefault();
                                    handleScan();
                                }
                            }}
                            placeholder="Scan barcode or enter SKU"
                            className="w-full"
                        />
                        <SecondaryButton type="button" onClick={handleScan}>
                            Scan
                        </SecondaryButton>
                        {matchedProduct && (
                            <PrimaryButton
                                type="button"
                                onClick={() => {
                                    setMovementProductId(matchedProduct.id);
                                    setStockMovementOpen(true);
                                }}
                            >
                                Adjust Stock
                            </PrimaryButton>
                        )}
                    </div>
                    {scanResult && (
                        <p className="mt-2 text-sm text-slate-600 dark:text-slate-300">
                            {scanResult}
                        </p>
                    )}
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card title="Goods Receipts" description="Posted GRNs">
                        <DataTable
                            data={goodsReceipts}
                            keyExtractor={(row) => row.id}
                            columns={[
                                {
                                    header: 'GRN',
                                    accessor: (row) => (
                                        <span className="font-mono font-semibold">
                                            {row.grn_no}
                                        </span>
                                    ),
                                },
                                {
                                    header: 'PO',
                                    accessor: (row) =>
                                        row.purchase_order?.po_no ?? '-',
                                },
                                {
                                    header: 'Supplier',
                                    accessor: (row) =>
                                        row.purchase_order?.supplier?.name ??
                                        '-',
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
                            keyExtractor={(row) => row.product_id ?? 'service'}
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
                                    accessor: (row) => money(row.average_cost),
                                },
                                {
                                    header: 'Action',
                                    accessor: (row) =>
                                        row.product_id ? (
                                            <SecondaryButton
                                                type="button"
                                                onClick={() => {
                                                    setMovementProductId(
                                                        row.product_id!,
                                                    );
                                                    setStockMovementOpen(true);
                                                }}
                                            >
                                                Adjust
                                            </SecondaryButton>
                                        ) : null,
                                },
                            ]}
                        />
                    </Card>
                </div>

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
                                accessor: (row) => row.product?.name ?? '-',
                            },
                            {
                                header: 'Type',
                                accessor: (row) => (
                                    <span className="rounded bg-slate-100 px-2 py-0.5 font-mono text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                        {row.movement_type}
                                    </span>
                                ),
                            },
                            {
                                header: 'Qty',
                                accessor: (row) =>
                                    `${Number(row.quantity).toLocaleString()} ${
                                        row.product?.unit ?? ''
                                    }`,
                            },
                            {
                                header: 'Unit Cost',
                                accessor: (row) => money(row.unit_cost),
                            },
                            {
                                header: 'Total Value',
                                accessor: (row) => money(row.total_cost),
                            },
                            {
                                header: 'Note',
                                accessor: (row) => row.note ?? '-',
                            },
                        ]}
                    />
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
