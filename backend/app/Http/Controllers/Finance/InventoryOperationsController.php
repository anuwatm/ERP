<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\{InventoryLot, Organization, Product, StockMovement, StockTransfer, Warehouse, WarehouseBin};
use Illuminate\Http\{RedirectResponse, Request};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\{Inertia, Response};

class InventoryOperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->org_id;
        $stockByProduct = StockMovement::where('org_id', $orgId)->selectRaw('product_id, SUM(quantity) quantity')->groupBy('product_id');

        return Inertia::render('Finance/InventoryOperations', [
            'warehouses' => Warehouse::where('org_id', $orgId)->with('bins')->orderBy('code')->get(),
            'lots' => InventoryLot::where('org_id', $orgId)->where(fn ($query) => $query->whereNull('expires_at')->orWhereDate('expires_at', '>=', today()))->orderBy('lot_no')->get(),
            'products' => Product::where('org_id', $orgId)->where('type', 'product')->orderBy('name')->get(['id', 'sku', 'barcode', 'name', 'unit', 'reorder_point']),
            'lowStock' => Product::where('org_id', $orgId)->where('reorder_point', '>', 0)->get()->filter(fn (Product $product) => (float) $stockByProduct->clone()->where('product_id', $product->id)->sum('quantity') <= (float) $product->reorder_point)->values(),
            'transfers' => StockTransfer::where('org_id', $orgId)->latest('transfer_date')->limit(50)->get(),
        ]);
    }

    public function storeWarehouse(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'max:30'], 'name' => ['required', 'max:150']]);
        Warehouse::create($data + ['org_id' => $request->user()->org_id]);
        return back();
    }

    public function storeBin(Request $request, Warehouse $warehouse): RedirectResponse
    {
        abort_unless($warehouse->org_id === $request->user()->org_id, 404);
        $data = $request->validate(['code' => ['required', 'max:50'], 'name' => ['nullable', 'max:150']]);
        WarehouseBin::create($data + ['org_id' => $warehouse->org_id, 'warehouse_id' => $warehouse->id]);
        return back();
    }

    public function storeLot(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('org_id', $orgId)],
            'lot_no' => ['required', 'max:100'], 'manufactured_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:manufactured_at'], 'barcode' => ['nullable', 'max:100'],
        ]);
        InventoryLot::create($data + ['org_id' => $orgId]);
        return back();
    }

    public function transfer(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'product_id' => ['required', 'uuid', Rule::exists('products', 'id')->where('org_id', $orgId)],
            'source_warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('org_id', $orgId)],
            'destination_warehouse_id' => ['required', 'uuid', 'different:source_warehouse_id', Rule::exists('warehouses', 'id')->where('org_id', $orgId)],
            'quantity' => ['required', 'numeric', 'gt:0'], 'transfer_date' => ['required', 'date'],
            'inventory_lot_id' => ['nullable', 'uuid', Rule::exists('inventory_lots', 'id')->where('org_id', $orgId)], 'note' => ['nullable', 'max:2000'],
        ]);
        if (! empty($data['inventory_lot_id'])) abort_unless(InventoryLot::where('org_id', $orgId)->where('product_id', $data['product_id'])->whereKey($data['inventory_lot_id'])->exists(), 422, 'Lot does not belong to product.');

        DB::transaction(function () use ($data, $request, $orgId): void {
            $sourceStock = StockMovement::where('org_id', $orgId)->where('product_id', $data['product_id'])->where('warehouse_id', $data['source_warehouse_id'])->when($data['inventory_lot_id'] ?? null, fn ($query, $lotId) => $query->where('inventory_lot_id', $lotId))->lockForUpdate();
            $available = (float) $sourceStock->sum('quantity');
            abort_if($available < (float) $data['quantity'], 422, 'Insufficient source stock.');
            $baseUnitCost = round((float) $sourceStock->sum('base_total_cost') / max($available, 0.0001), 2);
            $currency = Organization::findOrFail($orgId)->currency;
            $transfer = StockTransfer::create($data + ['org_id' => $orgId, 'base_unit_cost' => $baseUnitCost, 'idempotency_key' => (string) Str::uuid(), 'created_by' => $request->user()->id]);
            foreach ([[$data['source_warehouse_id'], -1, 'transfer_out'], [$data['destination_warehouse_id'], 1, 'transfer_in']] as [$warehouseId, $sign, $type]) {
                StockMovement::create(['org_id' => $orgId, 'product_id' => $data['product_id'], 'warehouse_id' => $warehouseId, 'inventory_lot_id' => $data['inventory_lot_id'] ?? null, 'stock_transfer_id' => $transfer->id, 'movement_type' => $type, 'movement_date' => $data['transfer_date'], 'quantity' => $sign * (float) $data['quantity'], 'unit_cost' => $baseUnitCost, 'total_cost' => $sign * $baseUnitCost * (float) $data['quantity'], 'currency' => $currency, 'base_currency' => $currency, 'exchange_rate' => 1, 'base_unit_cost' => $baseUnitCost, 'base_total_cost' => $sign * $baseUnitCost * (float) $data['quantity'], 'note' => $data['note'] ?? null, 'created_by' => $request->user()->id]);
            }
        });
        return back();
    }

    public function scan(Request $request): Product
    {
        $code = trim((string) $request->query('code'));
        abort_if($code === '', 422, 'Barcode or SKU is required.');
        return Product::where('org_id', $request->user()->org_id)->where(fn ($query) => $query->where('barcode', $code)->orWhere('sku', $code))->firstOrFail(['id', 'sku', 'barcode', 'name', 'unit', 'reorder_point']);
    }

    public function stockCount(Request $request): RedirectResponse
    {
        $orgId = $request->user()->org_id;
        $data = $request->validate([
            'warehouse_id' => ['required', 'uuid', Rule::exists('warehouses', 'id')->where('org_id', $orgId)], 'count_date' => ['required', 'date'], 'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'uuid', 'distinct', Rule::exists('products', 'id')->where('org_id', $orgId)], 'items.*.counted_quantity' => ['required', 'numeric', 'min:0'],
        ]);
        DB::transaction(function () use ($data, $orgId, $request): void {
            $countId = (string) Str::orderedUuid();
            DB::table('stock_counts')->insert(['id' => $countId, 'org_id' => $orgId, 'warehouse_id' => $data['warehouse_id'], 'count_date' => $data['count_date'], 'status' => 'posted', 'posted_by' => $request->user()->id, 'posted_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
            $currency = Organization::findOrFail($orgId)->currency;
            foreach ($data['items'] as $item) {
                $stock = StockMovement::where('org_id', $orgId)->where('warehouse_id', $data['warehouse_id'])->where('product_id', $item['product_id'])->lockForUpdate();
                $systemQuantity = (float) $stock->sum('quantity'); $countedQuantity = round((float) $item['counted_quantity'], 4); $difference = round($countedQuantity - $systemQuantity, 4); $baseUnitCost = $systemQuantity > 0 ? round((float) $stock->sum('base_total_cost') / $systemQuantity, 2) : 0;
                DB::table('stock_count_items')->insert(['id' => (string) Str::orderedUuid(), 'stock_count_id' => $countId, 'product_id' => $item['product_id'], 'system_quantity' => $systemQuantity, 'counted_quantity' => $countedQuantity, 'created_at' => now(), 'updated_at' => now()]);
                if ($difference === 0.0) continue;
                StockMovement::create(['org_id' => $orgId, 'product_id' => $item['product_id'], 'warehouse_id' => $data['warehouse_id'], 'stock_count_id' => $countId, 'movement_type' => $difference > 0 ? 'stock_count_in' : 'stock_count_out', 'movement_date' => $data['count_date'], 'quantity' => $difference, 'unit_cost' => $baseUnitCost, 'total_cost' => $difference * $baseUnitCost, 'currency' => $currency, 'base_currency' => $currency, 'exchange_rate' => 1, 'base_unit_cost' => $baseUnitCost, 'base_total_cost' => $difference * $baseUnitCost, 'created_by' => $request->user()->id]);
            }
        });
        return back();
    }
}
