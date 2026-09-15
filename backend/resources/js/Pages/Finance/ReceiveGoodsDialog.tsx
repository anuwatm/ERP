import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useMemo, useState } from 'react';

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

type Warehouse = { id: string; code: string; name: string };

type InventoryLot = {
    id: string;
    product_id: string;
    lot_no: string;
    expires_at?: string | null;
};

type GrnForm = {
    purchase_order_id: string;
    received_date: string;
    warehouse_id: string;
    note: string;
    items: Array<{
        purchase_order_item_id: string;
        quantity: string;
        inventory_lot_id: string;
    }>;
};

const today = new Date().toISOString().slice(0, 10);

export default function ReceiveGoodsDialog({
    open,
    purchaseOrders,
    warehouses,
    inventoryLots,
    onClose,
}: {
    open: boolean;
    purchaseOrders: PurchaseOrder[];
    warehouses: Warehouse[];
    inventoryLots: InventoryLot[];
    onClose: () => void;
}) {
    const form = useForm<GrnForm>({
        purchase_order_id: '',
        received_date: today,
        warehouse_id: '',
        note: '',
        items: [],
    });

    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetSubmissionKey } = useSubmissionKey();

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
                        inventory_lot_id: '',
                    })) ?? [],
        });
    };

    const setItemQty = (itemId: string, quantity: string) => {
        form.setData(
            'items',
            form.data.items.map((item) =>
                item.purchase_order_item_id === itemId
                    ? { ...item, quantity }
                    : item,
            ),
        );
    };

    const setItemLot = (itemId: string, inventoryLotId: string) => {
        form.setData(
            'items',
            form.data.items.map((item) =>
                item.purchase_order_item_id === itemId
                    ? { ...item, inventory_lot_id: inventoryLotId }
                    : item,
            ),
        );
    };

    const isDirty =
        form.data.purchase_order_id !== '' ||
        form.data.warehouse_id !== '' ||
        form.data.received_date !== today ||
        form.data.note !== '' ||
        form.data.items.length > 0;

    const guard = useDiscardGuard(isDirty, form.processing);

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.purchase_order_id) return;

        form.post(route('goods-receipts.store'), {
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
                title="Receive Goods (GRN)"
                breadcrumb="Finance / Inventory"
                entityLabel="Goods Receipt"
                draftLabel={selectedPo ? `PO ${selectedPo.po_no}` : 'New GRN'}
                storageKey="receive-goods-editor-size"
                formId="receive-goods-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.purchase_order_id ||
                    form.data.items.length === 0 ||
                    form.data.items.every((it) => Number(it.quantity) <= 0)
                }
                saveLabel="Post GRN"
                status="Draft / Inbound Receiving"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-6 p-4 sm:p-6">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="grn-po-id"
                                label="Purchase Order"
                                value={form.data.purchase_order_id}
                                options={purchaseOrders.map((po) => ({
                                    id: po.id,
                                    label: `${po.po_no} - ${po.supplier?.name ?? 'No Supplier'}`,
                                }))}
                                onChange={selectPo}
                                error={form.errors.purchase_order_id}
                                required
                            />

                            <EditorReference
                                id="grn-warehouse-id"
                                label="Receiving Warehouse"
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
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="grn-received-date"
                                label="Received Date"
                                type="date"
                                value={form.data.received_date}
                                onChange={(val: string) =>
                                    form.setData('received_date', val)
                                }
                                error={form.errors.received_date}
                                required
                            />

                            <EditorField
                                id="grn-note"
                                label="Note"
                                value={form.data.note}
                                onChange={(val: string) =>
                                    form.setData('note', val)
                                }
                                error={form.errors.note}
                            />
                        </div>

                        {selectedPo ? (
                            <div className="space-y-3">
                                <h4 className="text-sm font-semibold text-slate-900 dark:text-white">
                                    Line Items to Receive
                                </h4>
                                <div className="space-y-3">
                                    {selectedPo.items.map((item) => {
                                        const selectedItem =
                                            form.data.items.find(
                                                (row) =>
                                                    row.purchase_order_item_id ===
                                                    item.id,
                                            );
                                        const relevantLots =
                                            inventoryLots.filter(
                                                (lot) =>
                                                    lot.product_id ===
                                                    item.product_id,
                                            );

                                        return (
                                            <div
                                                key={item.id}
                                                className="rounded-xl border border-slate-200 bg-slate-50/50 p-4 dark:border-slate-800 dark:bg-slate-900/50"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-2">
                                                    <span className="font-semibold text-slate-900 dark:text-white">
                                                        {item.description}
                                                    </span>
                                                    <span className="text-xs text-slate-500 dark:text-slate-400">
                                                        Ordered {item.quantity}{' '}
                                                        · Received{' '}
                                                        {item.received_quantity}{' '}
                                                        · Remaining{' '}
                                                        <strong className="text-indigo-600 dark:text-indigo-400">
                                                            {
                                                                item.remaining_quantity
                                                            }
                                                        </strong>
                                                    </span>
                                                </div>

                                                <div className="mt-3 grid gap-3 sm:grid-cols-2">
                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400">
                                                            Receiving Quantity
                                                        </label>
                                                        <input
                                                            type="number"
                                                            step="0.0001"
                                                            min="0"
                                                            max={
                                                                item.remaining_quantity
                                                            }
                                                            value={
                                                                selectedItem?.quantity ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                setItemQty(
                                                                    item.id,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                                        />
                                                    </div>

                                                    <div>
                                                        <label className="block text-xs font-medium text-slate-600 dark:text-slate-400">
                                                            Assign Lot / Batch
                                                        </label>
                                                        <select
                                                            value={
                                                                selectedItem?.inventory_lot_id ??
                                                                ''
                                                            }
                                                            onChange={(e) =>
                                                                setItemLot(
                                                                    item.id,
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            className="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm dark:border-slate-700 dark:bg-slate-900"
                                                        >
                                                            <option value="">
                                                                No lot tracked
                                                            </option>
                                                            {relevantLots.map(
                                                                (lot) => (
                                                                    <option
                                                                        key={
                                                                            lot.id
                                                                        }
                                                                        value={
                                                                            lot.id
                                                                        }
                                                                    >
                                                                        {
                                                                            lot.lot_no
                                                                        }{' '}
                                                                        {lot.expires_at
                                                                            ? `(Exp: ${lot.expires_at})`
                                                                            : ''}
                                                                    </option>
                                                                ),
                                                            )}
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ) : (
                            <div className="rounded-xl border border-dashed border-slate-300 p-8 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                                Select an approved purchase order above to load
                                its receivable line items.
                            </div>
                        )}
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
