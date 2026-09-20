<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class LowStockLocations
{
    /**
     * Count only active, tracked products with a positive reorder point at
     * warehouses that have recorded stock movements. Legacy movements without
     * a warehouse cannot establish a stock-location balance.
     */
    public static function query(string $orgId): Builder
    {
        $balances = DB::table('stock_movements')
            ->where('org_id', $orgId)
            ->whereNotNull('product_id')
            ->whereNotNull('warehouse_id')
            ->select('product_id', 'warehouse_id')
            ->selectRaw('SUM(quantity) as on_hand')
            ->groupBy('product_id', 'warehouse_id');

        return DB::table('products')
            ->joinSub($balances, 'balance', fn ($join) => $join->on('products.id', '=', 'balance.product_id'))
            ->join('warehouses', 'warehouses.id', '=', 'balance.warehouse_id')
            ->where('products.org_id', $orgId)
            ->where('warehouses.org_id', $orgId)
            ->whereNull('products.deleted_at')
            ->where('products.type', 'product')
            ->where('products.is_active', true)
            ->where('products.track_inventory', true)
            ->where('products.reorder_point', '>', 0)
            ->where('warehouses.status', 'active')
            ->whereColumn('balance.on_hand', '<=', 'products.reorder_point')
            ->select([
                'products.id as product_id', 'products.sku', 'products.name', 'products.unit',
                'products.reorder_point', 'warehouses.id as warehouse_id', 'warehouses.name as warehouse_name',
                'balance.on_hand',
            ]);
    }
}
