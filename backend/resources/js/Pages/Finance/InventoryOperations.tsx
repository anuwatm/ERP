import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import Card from '@/Components/UI/Card';
import DataTable from '@/Components/UI/DataTable';
import PageHeader from '@/Components/UI/PageHeader';
import useEditorCoordinator from '@/Hooks/useEditorCoordinator';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    BinDialog,
    LotDialog,
    StockCountDialog,
    StockTransferDialog,
    WarehouseDialog,
} from './InventoryDialogs';

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
    const [scanMessage, setScanMessage] = useState<string | null>(null);
    const [matchedProduct, setMatchedProduct] = useState<Product | null>(null);

    type ActiveEditor =
        | { type: 'warehouse' }
        | { type: 'bin'; warehouseId?: string }
        | { type: 'lot'; productId?: string }
        | { type: 'transfer'; productId?: string }
        | { type: 'count'; productId?: string }
        | null;

    // Single active editor arbitration (one active editor per page)
    const { activeEditor, setActiveEditor, activeGuardRef, switchEditor } =
        useEditorCoordinator<ActiveEditor>(null);

    const lookup = () => {
        const product = products.find(
            (item) => item.barcode === scanCode || item.sku === scanCode,
        );
        if (!product) {
            setMatchedProduct(null);
            setScanMessage('Barcode / SKU not found in product catalog.');
            return;
        }
        setMatchedProduct(product);
        setScanMessage(
            `${product.name} (${product.unit}) · SKU: ${product.sku ?? '-'} · Barcode: ${product.barcode ?? '-'}`,
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Inventory Operations" />
            <div className="space-y-6">
                <PageHeader
                    title="Inventory Operations"
                    description="Warehouse, bin, lot, barcode, transfer, stock count and reorder control."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    switchEditor({ type: 'warehouse' })
                                }
                            >
                                + Warehouse
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    switchEditor({
                                        type: 'bin',
                                        warehouseId: '',
                                    })
                                }
                            >
                                + Bin
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    switchEditor({
                                        type: 'lot',
                                        productId: '',
                                    })
                                }
                            >
                                + Lot
                            </SecondaryButton>
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    switchEditor({
                                        type: 'count',
                                        productId: '',
                                    })
                                }
                            >
                                + Physical Count
                            </SecondaryButton>
                            <PrimaryButton
                                type="button"
                                onClick={() =>
                                    switchEditor({
                                        type: 'transfer',
                                        productId: '',
                                    })
                                }
                            >
                                + Transfer Stock
                            </PrimaryButton>
                        </div>
                    }
                />

                {/* Dialogs - exactly one active editor mounted */}
                {activeEditor?.type === 'warehouse' && (
                    <WarehouseDialog
                        open={true}
                        onRequestCloseRef={activeGuardRef}
                        onClose={() => {
                            activeGuardRef.current = null;
                            setActiveEditor(null);
                        }}
                    />
                )}

                {activeEditor?.type === 'bin' && (
                    <BinDialog
                        open={true}
                        warehouses={warehouses}
                        initialWarehouseId={activeEditor.warehouseId ?? ''}
                        onRequestCloseRef={activeGuardRef}
                        onClose={() => {
                            activeGuardRef.current = null;
                            setActiveEditor(null);
                        }}
                    />
                )}

                {activeEditor?.type === 'lot' && (
                    <LotDialog
                        open={true}
                        products={products}
                        initialProductId={activeEditor.productId ?? ''}
                        onRequestCloseRef={activeGuardRef}
                        onClose={() => {
                            activeGuardRef.current = null;
                            setActiveEditor(null);
                        }}
                    />
                )}

                {activeEditor?.type === 'transfer' && (
                    <StockTransferDialog
                        open={true}
                        products={products}
                        warehouses={warehouses}
                        lots={lots}
                        initialProductId={activeEditor.productId ?? ''}
                        onRequestCloseRef={activeGuardRef}
                        onClose={() => {
                            activeGuardRef.current = null;
                            setActiveEditor(null);
                        }}
                    />
                )}

                {activeEditor?.type === 'count' && (
                    <StockCountDialog
                        open={true}
                        products={products}
                        warehouses={warehouses}
                        initialProductId={activeEditor.productId ?? ''}
                        onRequestCloseRef={activeGuardRef}
                        onClose={() => {
                            activeGuardRef.current = null;
                            setActiveEditor(null);
                        }}
                    />
                )}

                {/* Barcode Scanner */}
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
                        <div className="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 dark:bg-slate-900">
                            <p className="text-sm font-medium text-slate-700 dark:text-slate-300">
                                {scanMessage}
                            </p>
                            {matchedProduct && (
                                <div className="flex flex-wrap gap-2">
                                    <SecondaryButton
                                        type="button"
                                        onClick={() =>
                                            switchEditor({
                                                type: 'transfer',
                                                productId: matchedProduct.id,
                                            })
                                        }
                                    >
                                        Transfer
                                    </SecondaryButton>
                                    <SecondaryButton
                                        type="button"
                                        onClick={() =>
                                            switchEditor({
                                                type: 'count',
                                                productId: matchedProduct.id,
                                            })
                                        }
                                    >
                                        Count
                                    </SecondaryButton>
                                    <SecondaryButton
                                        type="button"
                                        onClick={() =>
                                            switchEditor({
                                                type: 'lot',
                                                productId: matchedProduct.id,
                                            })
                                        }
                                    >
                                        New Lot
                                    </SecondaryButton>
                                </div>
                            )}
                        </div>
                    )}
                </Card>

                <div className="grid gap-6 xl:grid-cols-2">
                    {/* Warehouses & Bins Overview */}
                    <Card
                        title="Warehouses & Bins"
                        description={`${warehouses.length} warehouse locations configured`}
                    >
                        <div className="divide-y divide-slate-200 dark:divide-slate-800">
                            {warehouses.map((wh) => (
                                <div
                                    key={wh.id}
                                    className="py-4 first:pt-0 last:pb-0"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <span className="font-mono text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                                                {wh.code}
                                            </span>
                                            <h4 className="text-sm font-semibold text-slate-900 dark:text-white">
                                                {wh.name}
                                            </h4>
                                        </div>
                                        <SecondaryButton
                                            type="button"
                                            onClick={() =>
                                                switchEditor({
                                                    type: 'bin',
                                                    warehouseId: wh.id,
                                                })
                                            }
                                        >
                                            + Add Bin
                                        </SecondaryButton>
                                    </div>
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {wh.bins && wh.bins.length > 0 ? (
                                            wh.bins.map((b) => (
                                                <span
                                                    key={b.id}
                                                    className="inline-flex items-center rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 dark:bg-slate-800 dark:text-slate-300"
                                                    title={b.name ?? undefined}
                                                >
                                                    {b.code}
                                                    {b.name
                                                        ? ` (${b.name})`
                                                        : ''}
                                                </span>
                                            ))
                                        ) : (
                                            <span className="text-xs text-slate-400">
                                                No bins defined for this
                                                warehouse.
                                            </span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* Low Stock Alerts */}
                    <Card
                        title="Low Stock Alerts"
                        description="Products currently at or below their reorder threshold"
                    >
                        {lowStock.length > 0 ? (
                            <DataTable
                                data={lowStock}
                                keyExtractor={(row) => row.id}
                                columns={[
                                    {
                                        header: 'Product',
                                        accessor: (row) => (
                                            <div>
                                                <div className="font-semibold text-slate-900 dark:text-white">
                                                    {row.name}
                                                </div>
                                                <div className="font-mono text-xs text-slate-500">
                                                    {row.sku ?? '-'}
                                                </div>
                                            </div>
                                        ),
                                    },
                                    {
                                        header: 'Reorder Point',
                                        accessor: (row) => (
                                            <span className="font-semibold text-amber-600 dark:text-amber-400">
                                                {row.reorder_point} {row.unit}
                                            </span>
                                        ),
                                    },
                                    {
                                        header: 'Action',
                                        accessor: (row) => (
                                            <SecondaryButton
                                                type="button"
                                                onClick={() =>
                                                    switchEditor({
                                                        type: 'transfer',
                                                        productId: row.id,
                                                    })
                                                }
                                            >
                                                Transfer In
                                            </SecondaryButton>
                                        ),
                                    },
                                ]}
                            />
                        ) : (
                            <div className="py-8 text-center text-sm text-slate-500 dark:text-slate-400">
                                ✓ All product inventory levels are above reorder
                                thresholds.
                            </div>
                        )}
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
