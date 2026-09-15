import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import {
    EditorChoice,
    EditorField,
    EditorReference,
} from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useState } from 'react';

type Product = {
    id: string;
    sku?: string | null;
    barcode?: string | null;
    name: string;
    unit?: string | null;
    cost?: string | null;
};

type Warehouse = { id: string; code: string; name: string };

type MovementForm = {
    product_id: string;
    movement_type: 'adjustment_in' | 'adjustment_out' | 'return_to_supplier';
    movement_date: string;
    quantity: string;
    unit_cost: string;
    warehouse_id: string;
    note: string;
};

const today = new Date().toISOString().slice(0, 10);

export default function StockMovementDialog({
    open,
    products,
    warehouses,
    initialProductId = '',
    onClose,
}: {
    open: boolean;
    products: Product[];
    warehouses: Warehouse[];
    initialProductId?: string;
    onClose: () => void;
}) {
    const form = useForm<MovementForm>({
        product_id: initialProductId,
        movement_type: 'adjustment_in',
        movement_date: today,
        quantity: '',
        unit_cost: '',
        warehouse_id: '',
        note: '',
    });

    const [minimized, setMinimized] = useState(false);
    const [barcode, setBarcode] = useState('');
    const [scanResult, setScanResult] = useState('');
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

    useEffect(() => {
        if (open) {
            if (initialProductId) {
                form.setData('product_id', initialProductId);
                const prod = products.find((p) => p.id === initialProductId);
                if (prod && prod.cost) {
                    form.setData('unit_cost', String(prod.cost));
                }
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, initialProductId]);

    const isDirty =
        form.data.product_id !== initialProductId ||
        form.data.movement_type !== 'adjustment_in' ||
        form.data.movement_date !== today ||
        form.data.quantity !== '' ||
        form.data.unit_cost !== '' ||
        form.data.warehouse_id !== '' ||
        form.data.note !== '';

    const guard = useDiscardGuard(isDirty, form.processing);

    const scan = () => {
        const product = products.find(
            (p) => p.barcode === barcode || p.sku === barcode,
        );
        if (product) {
            form.setData({
                ...form.data,
                product_id: product.id,
                unit_cost: product.cost
                    ? String(product.cost)
                    : form.data.unit_cost,
            });
            setScanResult(`✓ ${product.name} matched.`);
        } else {
            setScanResult('✕ Barcode / SKU not found.');
        }
    };

    const selectedProduct = products.find((p) => p.id === form.data.product_id);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.product_id || !form.data.quantity)
            return;

        form.post(route('stock-movements.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                resetSubmissionKey();
                form.reset();
                onClose();
            },
        });
    }

    if (!open) return null;

    return (
        <>
            <DiscardChangesDialog
                open={guard.confirming}
                onKeep={guard.keep}
                onDiscard={guard.discard}
            />
            <CreateEditDialog
                open={open}
                minimized={minimized}
                onMinimize={setMinimized}
                onClose={() => guard.request(onClose)}
                title="Post Stock Movement"
                breadcrumb="Finance / Inventory"
                entityLabel="Stock Adjustment"
                draftLabel={
                    selectedProduct ? selectedProduct.name : 'New Movement'
                }
                storageKey="stock-movement-editor-size"
                formId="stock-movement-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.product_id ||
                    !form.data.quantity ||
                    Number(form.data.quantity) <= 0
                }
                saveLabel="Post Movement"
                status="Direct Inventory Adjustment"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        {/* Barcode scanner panel */}
                        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900/50">
                            <label className="block text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                                Scan Barcode / SKU to select Product
                            </label>
                            <div className="mt-2 flex gap-2">
                                <TextInput
                                    value={barcode}
                                    onChange={(event) =>
                                        setBarcode(event.target.value)
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                            scan();
                                        }
                                    }}
                                    placeholder="Enter or scan barcode / SKU..."
                                    className="w-full"
                                />
                                <SecondaryButton type="button" onClick={scan}>
                                    Find
                                </SecondaryButton>
                            </div>
                            {scanResult && (
                                <p
                                    className={`mt-2 text-xs font-medium ${
                                        scanResult.startsWith('✓')
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-rose-600 dark:text-rose-400'
                                    }`}
                                >
                                    {scanResult}
                                </p>
                            )}
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="sm-product-id"
                                label="Product"
                                value={form.data.product_id}
                                options={products.map((p) => ({
                                    id: p.id,
                                    label: `${p.name} (${p.sku ?? 'No SKU'})`,
                                }))}
                                onChange={(val: string) => {
                                    form.setData('product_id', val);
                                    const prod = products.find(
                                        (p) => p.id === val,
                                    );
                                    if (prod && prod.cost) {
                                        form.setData(
                                            'unit_cost',
                                            String(prod.cost),
                                        );
                                    }
                                }}
                                error={form.errors.product_id}
                                required
                            />

                            <EditorChoice
                                id="sm-movement-type"
                                label="Movement Type"
                                value={form.data.movement_type}
                                options={[
                                    'adjustment_in',
                                    'adjustment_out',
                                    'return_to_supplier',
                                ]}
                                onChange={(val: string) =>
                                    form.setData(
                                        'movement_type',
                                        val as
                                            | 'adjustment_in'
                                            | 'adjustment_out'
                                            | 'return_to_supplier',
                                    )
                                }
                                error={form.errors.movement_type}
                                required
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-3">
                            <EditorField
                                id="sm-movement-date"
                                label="Movement Date"
                                type="date"
                                value={form.data.movement_date}
                                onChange={(val: string) =>
                                    form.setData('movement_date', val)
                                }
                                error={form.errors.movement_date}
                                required
                            />

                            <EditorField
                                id="sm-quantity"
                                label={`Quantity ${selectedProduct?.unit ? `(${selectedProduct.unit})` : ''}`}
                                type="number"
                                step="0.0001"
                                min={0.0001}
                                value={form.data.quantity}
                                onChange={(val: string) =>
                                    form.setData('quantity', val)
                                }
                                error={form.errors.quantity}
                                required
                            />

                            <EditorField
                                id="sm-unit-cost"
                                label="Unit Cost (THB)"
                                type="number"
                                step="0.01"
                                min={0}
                                value={form.data.unit_cost}
                                onChange={(val: string) =>
                                    form.setData('unit_cost', val)
                                }
                                error={form.errors.unit_cost}
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="sm-warehouse-id"
                                label="Warehouse"
                                emptyLabel="No warehouse location"
                                value={form.data.warehouse_id}
                                options={warehouses.map((wh) => ({
                                    id: wh.id,
                                    label: `${wh.code} - ${wh.name}`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData('warehouse_id', val)
                                }
                                error={form.errors.warehouse_id}
                            />

                            <EditorField
                                id="sm-note"
                                label="Reason / Note"
                                value={form.data.note}
                                onChange={(val: string) =>
                                    form.setData('note', val)
                                }
                                error={form.errors.note}
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
