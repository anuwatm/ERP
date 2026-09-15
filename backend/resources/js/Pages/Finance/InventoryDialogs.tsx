import CreateEditDialog from '@/Components/UI/CreateEditDialog';
import DiscardChangesDialog from '@/Components/UI/DiscardChangesDialog';
import { EditorField, EditorReference } from '@/Components/UI/EditorFields';
import useDiscardGuard from '@/Hooks/useDiscardGuard';
import useSubmissionKey from '@/Hooks/useSubmissionKey';
import { useForm } from '@inertiajs/react';
import { FormEvent, useEffect, useMemo, useState } from 'react';

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

const today = new Date().toISOString().slice(0, 10);

import {
    isBinDirty,
    isLotDirty,
    isStockCountDirty,
    isStockTransferDirty,
    isWarehouseDirty,
} from './inventoryDirty';

/* =========================================================================
   1. Warehouse Dialog
   ========================================================================= */
export function WarehouseDialog({
    open,
    onClose,
    onRequestCloseRef,
}: {
    open: boolean;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
}) {
    const defaults = { code: '', name: '' };
    const form = useForm({ ...defaults });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();
    const isDirty = open && isWarehouseDirty(form.data, defaults);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.code || !form.data.name) return;

        form.post(route('warehouses.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
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
                onClose={() => guard.request(handleClose)}
                title="Create Warehouse"
                breadcrumb="Finance / Inventory"
                entityLabel="Warehouse"
                draftLabel={form.data.name || form.data.code || 'New Warehouse'}
                storageKey="warehouse-editor-size"
                formId="warehouse-editor-form"
                processing={form.processing}
                saveDisabled={!form.data.code || !form.data.name}
                saveLabel="Create Warehouse"
                status="Active Location"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <EditorField
                            id="wh-code"
                            label="Warehouse Code"
                            value={form.data.code}
                            onChange={(val: string) =>
                                form.setData('code', val.toUpperCase())
                            }
                            error={form.errors.code}
                            required
                        />
                        <EditorField
                            id="wh-name"
                            label="Warehouse Name"
                            value={form.data.name}
                            onChange={(val: string) =>
                                form.setData('name', val)
                            }
                            error={form.errors.name}
                            required
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

/* =========================================================================
   2. Warehouse Bin Dialog
   ========================================================================= */
export function BinDialog({
    open,
    warehouses,
    initialWarehouseId = '',
    onClose,
    onRequestCloseRef,
}: {
    open: boolean;
    warehouses: Warehouse[];
    initialWarehouseId?: string;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
}) {
    const defaultWarehouseId = initialWarehouseId || (warehouses[0]?.id ?? '');
    const defaults = {
        warehouse_id: defaultWarehouseId,
        code: '',
        name: '',
    };
    const form = useForm({ ...defaults });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();
    const isDirty = open && isBinDirty(form.data, defaults);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.warehouse_id || !form.data.code)
            return;

        form.post(route('warehouses.bins.store', form.data.warehouse_id), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
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
                onClose={() => guard.request(handleClose)}
                title="Create Warehouse Bin / Shelf"
                breadcrumb="Finance / Inventory"
                entityLabel="Bin Location"
                draftLabel={form.data.code || 'New Bin'}
                storageKey="bin-editor-size"
                formId="bin-editor-form"
                processing={form.processing}
                saveDisabled={!form.data.warehouse_id || !form.data.code}
                saveLabel="Create Bin"
                status="Warehouse Sublocation"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <EditorReference
                            id="bin-warehouse-id"
                            label="Warehouse"
                            value={form.data.warehouse_id}
                            options={warehouses.map((wh) => ({
                                id: wh.id,
                                label: `${wh.code} - ${wh.name}`,
                            }))}
                            onChange={(val: string) =>
                                form.setData('warehouse_id', val)
                            }
                            error={form.errors.warehouse_id}
                            required
                        />
                        <EditorField
                            id="bin-code"
                            label="Bin Code"
                            value={form.data.code}
                            onChange={(val: string) =>
                                form.setData('code', val.toUpperCase())
                            }
                            error={form.errors.code}
                            required
                        />
                        <EditorField
                            id="bin-name"
                            label="Bin Description"
                            value={form.data.name}
                            onChange={(val: string) =>
                                form.setData('name', val)
                            }
                            error={form.errors.name}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

/* =========================================================================
   3. Lot / Batch Dialog
   ========================================================================= */
export function LotDialog({
    open,
    products,
    initialProductId = '',
    onClose,
    onRequestCloseRef,
}: {
    open: boolean;
    products: Product[];
    initialProductId?: string;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
}) {
    const defaultProductId = initialProductId || (products[0]?.id ?? '');
    const defaults = {
        product_id: defaultProductId,
        lot_no: '',
        manufactured_at: '',
        expires_at: '',
        barcode: '',
    };
    const form = useForm({ ...defaults });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();
    const isDirty = open && isLotDirty(form.data, defaults);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (form.processing || !form.data.product_id || !form.data.lot_no)
            return;

        form.post(route('inventory-lots.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
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
                onClose={() => guard.request(handleClose)}
                title="Create Lot / Batch"
                breadcrumb="Finance / Inventory"
                entityLabel="Inventory Lot"
                draftLabel={form.data.lot_no || 'New Lot'}
                storageKey="lot-editor-size"
                formId="lot-editor-form"
                processing={form.processing}
                saveDisabled={!form.data.product_id || !form.data.lot_no}
                saveLabel="Create Lot"
                status="Lot & Expiry Tracking"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <EditorReference
                            id="lot-product-id"
                            label="Product"
                            value={form.data.product_id}
                            options={products.map((p) => ({
                                id: p.id,
                                label: `${p.name} (${p.sku ?? 'No SKU'})`,
                            }))}
                            onChange={(val: string) =>
                                form.setData('product_id', val)
                            }
                            error={form.errors.product_id}
                            required
                        />
                        <EditorField
                            id="lot-no"
                            label="Lot / Batch Number"
                            value={form.data.lot_no}
                            onChange={(val: string) =>
                                form.setData('lot_no', val)
                            }
                            error={form.errors.lot_no}
                            required
                        />
                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="lot-mfg-date"
                                label="Manufactured Date"
                                type="date"
                                value={form.data.manufactured_at}
                                onChange={(val: string) =>
                                    form.setData('manufactured_at', val)
                                }
                                error={form.errors.manufactured_at}
                            />
                            <EditorField
                                id="lot-exp-date"
                                label="Expiry Date"
                                type="date"
                                value={form.data.expires_at}
                                onChange={(val: string) =>
                                    form.setData('expires_at', val)
                                }
                                error={form.errors.expires_at}
                            />
                        </div>
                        <EditorField
                            id="lot-barcode"
                            label="Lot Barcode"
                            value={form.data.barcode}
                            onChange={(val: string) =>
                                form.setData('barcode', val)
                            }
                            error={form.errors.barcode}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

/* =========================================================================
   4. Stock Transfer Dialog
   ========================================================================= */
export function StockTransferDialog({
    open,
    products,
    warehouses,
    lots,
    initialProductId = '',
    onClose,
    onRequestCloseRef,
}: {
    open: boolean;
    products: Product[];
    warehouses: Warehouse[];
    lots: Lot[];
    initialProductId?: string;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
}) {
    const defaults = {
        product_id: initialProductId || '',
        source_warehouse_id: '',
        destination_warehouse_id: '',
        inventory_lot_id: '',
        quantity: '',
        transfer_date: today,
        note: '',
    };
    const form = useForm({ ...defaults });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();

    const selectedLots = useMemo(
        () => lots.filter((item) => item.product_id === form.data.product_id),
        [lots, form.data.product_id],
    );

    const isDirty = open && isStockTransferDirty(form.data, defaults);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (
            form.processing ||
            !form.data.product_id ||
            !form.data.quantity ||
            !form.data.source_warehouse_id ||
            !form.data.destination_warehouse_id
        )
            return;

        form.post(route('stock-transfers.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
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
                onClose={() => guard.request(handleClose)}
                title="Transfer Stock"
                breadcrumb="Finance / Inventory"
                entityLabel="Stock Transfer"
                draftLabel="Warehouse Transfer"
                storageKey="transfer-editor-size"
                formId="transfer-editor-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.product_id ||
                    !form.data.quantity ||
                    !form.data.source_warehouse_id ||
                    !form.data.destination_warehouse_id ||
                    form.data.source_warehouse_id ===
                        form.data.destination_warehouse_id
                }
                saveLabel="Execute Transfer"
                status="Inter-Warehouse Movement"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <EditorReference
                            id="transfer-product-id"
                            label="Product"
                            value={form.data.product_id}
                            options={products.map((p) => ({
                                id: p.id,
                                label: `${p.name} (${p.sku ?? 'No SKU'})`,
                            }))}
                            onChange={(val: string) => {
                                form.setData('product_id', val);
                                form.setData('inventory_lot_id', '');
                            }}
                            error={form.errors.product_id}
                            required
                        />

                        {selectedLots.length > 0 && (
                            <EditorReference
                                id="transfer-lot-id"
                                label="Inventory Lot"
                                emptyLabel="No specific lot"
                                value={form.data.inventory_lot_id}
                                options={selectedLots.map((l) => ({
                                    id: l.id,
                                    label: `${l.lot_no} ${l.expires_at ? `(Exp: ${l.expires_at})` : ''}`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData('inventory_lot_id', val)
                                }
                                error={form.errors.inventory_lot_id}
                            />
                        )}

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorReference
                                id="transfer-source-wh"
                                label="Source Warehouse (From)"
                                value={form.data.source_warehouse_id}
                                options={warehouses.map((wh) => ({
                                    id: wh.id,
                                    label: `${wh.code} - ${wh.name}`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData('source_warehouse_id', val)
                                }
                                error={form.errors.source_warehouse_id}
                                required
                            />

                            <EditorReference
                                id="transfer-dest-wh"
                                label="Destination Warehouse (To)"
                                value={form.data.destination_warehouse_id}
                                options={warehouses.map((wh) => ({
                                    id: wh.id,
                                    label: `${wh.code} - ${wh.name}`,
                                }))}
                                onChange={(val: string) =>
                                    form.setData(
                                        'destination_warehouse_id',
                                        val,
                                    )
                                }
                                error={form.errors.destination_warehouse_id}
                                required
                            />
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="transfer-quantity"
                                label="Quantity"
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
                                id="transfer-date"
                                label="Transfer Date"
                                type="date"
                                value={form.data.transfer_date}
                                onChange={(val: string) =>
                                    form.setData('transfer_date', val)
                                }
                                error={form.errors.transfer_date}
                                required
                            />
                        </div>

                        <EditorField
                            id="transfer-note"
                            label="Note"
                            multiline
                            value={form.data.note}
                            onChange={(val: string) =>
                                form.setData('note', val)
                            }
                            error={form.errors.note}
                        />
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}

/* =========================================================================
   5. Stock Count Dialog
   ========================================================================= */
export function StockCountDialog({
    open,
    products,
    warehouses,
    initialProductId = '',
    onClose,
    onRequestCloseRef,
}: {
    open: boolean;
    products: Product[];
    warehouses: Warehouse[];
    initialProductId?: string;
    onClose: () => void;
    onRequestCloseRef?: React.MutableRefObject<
        ((action: () => void) => void) | null
    >;
}) {
    const defaultWarehouseId = warehouses[0]?.id ?? '';
    const defaultProductId = initialProductId || '';
    const defaults = {
        warehouse_id: defaultWarehouseId,
        product_id: defaultProductId,
        counted_quantity: '',
        count_date: today,
    };
    const form = useForm({ ...defaults });
    const [minimized, setMinimized] = useState(false);
    const { headers, reset: resetKey } = useSubmissionKey();

    const isDirty = open && isStockCountDirty(form.data, defaults);
    const guard = useDiscardGuard(isDirty, form.processing);

    const handleClose = (afterClose?: () => void) => {
        form.reset();
        form.clearErrors();
        resetKey();
        setMinimized(false);
        onClose();
        afterClose?.();
    };

    useEffect(() => {
        if (onRequestCloseRef) {
            onRequestCloseRef.current = (action: () => void) => {
                guard.request(() => handleClose(action));
            };
        }
        return () => {
            if (onRequestCloseRef) {
                onRequestCloseRef.current = null;
            }
        };
    });

    function submit(event: FormEvent) {
        event.preventDefault();
        if (
            form.processing ||
            !form.data.warehouse_id ||
            !form.data.product_id ||
            form.data.counted_quantity === ''
        )
            return;

        form.transform((data) => ({
            warehouse_id: data.warehouse_id,
            count_date: data.count_date,
            items: [
                {
                    product_id: data.product_id,
                    counted_quantity: data.counted_quantity,
                },
            ],
        }));

        form.post(route('stock-counts.store'), {
            headers: headers(),
            preserveScroll: true,
            onSuccess: () => {
                handleClose();
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
                onClose={() => guard.request(handleClose)}
                title="Record Stock Count"
                breadcrumb="Finance / Inventory"
                entityLabel="Physical Count"
                draftLabel="Stock Audit"
                storageKey="count-editor-size"
                formId="count-editor-form"
                processing={form.processing}
                saveDisabled={
                    !form.data.warehouse_id ||
                    !form.data.product_id ||
                    form.data.counted_quantity === '' ||
                    Number(form.data.counted_quantity) < 0
                }
                saveLabel="Submit Count"
                status="Cycle Count / Physical Audit"
                errors={form.errors}
                onSubmit={submit}
            >
                {() => (
                    <div className="space-y-4 p-4 sm:p-6">
                        <EditorReference
                            id="count-warehouse-id"
                            label="Warehouse"
                            value={form.data.warehouse_id}
                            options={warehouses.map((wh) => ({
                                id: wh.id,
                                label: `${wh.code} - ${wh.name}`,
                            }))}
                            onChange={(val: string) =>
                                form.setData('warehouse_id', val)
                            }
                            error={form.errors.warehouse_id}
                            required
                        />

                        <EditorReference
                            id="count-product-id"
                            label="Product"
                            value={form.data.product_id}
                            options={products.map((p) => ({
                                id: p.id,
                                label: `${p.name} (${p.sku ?? 'No SKU'})`,
                            }))}
                            onChange={(val: string) =>
                                form.setData('product_id', val)
                            }
                            error={form.errors.product_id}
                            required
                        />

                        <div className="grid gap-4 sm:grid-cols-2">
                            <EditorField
                                id="count-quantity"
                                label="Physical Counted Quantity"
                                type="number"
                                step="0.0001"
                                min={0}
                                value={form.data.counted_quantity}
                                onChange={(val: string) =>
                                    form.setData('counted_quantity', val)
                                }
                                error={form.errors.counted_quantity}
                                required
                            />

                            <EditorField
                                id="count-date"
                                label="Count Date"
                                type="date"
                                value={form.data.count_date}
                                onChange={(val: string) =>
                                    form.setData('count_date', val)
                                }
                                error={form.errors.count_date}
                                required
                            />
                        </div>
                    </div>
                )}
            </CreateEditDialog>
        </>
    );
}
