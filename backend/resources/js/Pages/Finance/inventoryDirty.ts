export function isWarehouseDirty(
    data: { code: string; name: string },
    defaults: { code: string; name: string } = { code: '', name: '' },
): boolean {
    return data.code !== defaults.code || data.name !== defaults.name;
}

export function isBinDirty(
    data: { warehouse_id: string; code: string; name: string },
    defaults: { warehouse_id: string; code: string; name: string } = {
        warehouse_id: '',
        code: '',
        name: '',
    },
): boolean {
    return (
        data.warehouse_id !== defaults.warehouse_id ||
        data.code !== defaults.code ||
        data.name !== defaults.name
    );
}

export function isLotDirty(
    data: {
        product_id: string;
        lot_no: string;
        manufactured_at: string;
        expires_at: string;
        barcode: string;
    },
    defaults: {
        product_id: string;
        lot_no: string;
        manufactured_at: string;
        expires_at: string;
        barcode: string;
    } = {
        product_id: '',
        lot_no: '',
        manufactured_at: '',
        expires_at: '',
        barcode: '',
    },
): boolean {
    return (
        data.product_id !== defaults.product_id ||
        data.lot_no !== defaults.lot_no ||
        data.manufactured_at !== defaults.manufactured_at ||
        data.expires_at !== defaults.expires_at ||
        data.barcode !== defaults.barcode
    );
}

export function isStockTransferDirty(
    data: {
        product_id: string;
        source_warehouse_id: string;
        destination_warehouse_id: string;
        inventory_lot_id: string;
        quantity: string;
        transfer_date: string;
        note: string;
    },
    defaults: {
        product_id: string;
        source_warehouse_id: string;
        destination_warehouse_id: string;
        inventory_lot_id: string;
        quantity: string;
        transfer_date: string;
        note: string;
    } = {
        product_id: '',
        source_warehouse_id: '',
        destination_warehouse_id: '',
        inventory_lot_id: '',
        quantity: '',
        transfer_date: '',
        note: '',
    },
): boolean {
    return (
        data.product_id !== defaults.product_id ||
        data.source_warehouse_id !== defaults.source_warehouse_id ||
        data.destination_warehouse_id !== defaults.destination_warehouse_id ||
        data.inventory_lot_id !== defaults.inventory_lot_id ||
        data.quantity !== defaults.quantity ||
        data.transfer_date !== defaults.transfer_date ||
        data.note !== defaults.note
    );
}

export function isStockCountDirty(
    data: {
        warehouse_id: string;
        product_id: string;
        counted_quantity: string;
        count_date: string;
    },
    defaults: {
        warehouse_id: string;
        product_id: string;
        counted_quantity: string;
        count_date: string;
    } = {
        warehouse_id: '',
        product_id: '',
        counted_quantity: '',
        count_date: '',
    },
): boolean {
    return (
        data.warehouse_id !== defaults.warehouse_id ||
        data.product_id !== defaults.product_id ||
        data.counted_quantity !== defaults.counted_quantity ||
        data.count_date !== defaults.count_date
    );
}
