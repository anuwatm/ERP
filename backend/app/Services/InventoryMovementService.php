<?php

namespace App\Services;

use App\Models\InventoryLot;
use App\Models\Organization;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class InventoryMovementService
{
    /** Existing product rows serialize all warehouses/lots, even an empty ledger. */
    public function lockProducts(string $orgId, array $productIds): void
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inventory locks require an active transaction.');
        }
        $ids = array_values(array_unique(array_filter($productIds)));
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            Product::where('org_id', $orgId)->whereKey($id)->lockForUpdate()->firstOrFail();
        }
    }

    public function stock(string $orgId, string $productId, ?string $warehouseId = null, ?string $lotId = null): Builder
    {
        return StockMovement::where('org_id', $orgId)->where('product_id', $productId)
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($lotId !== null, fn ($q) => $q->where('inventory_lot_id', $lotId));
    }

    public function available(string $orgId, string $productId, ?string $warehouseId = null, ?string $lotId = null): string
    {
        $reserved = DB::table('pos_stock_entitlements')->where('org_id', $orgId)->where('product_id', $productId)->where('status', '!=', 'closed')
            ->when($warehouseId !== null, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($lotId !== null, fn ($q) => $q->where('inventory_lot_id', $lotId))
            ->get(['granted_quantity', 'consumed_quantity'])
            ->reduce(fn ($sum, $row) => bcadd($sum, bcsub((string) $row->granted_quantity, (string) $row->consumed_quantity, 4), 4), '0.0000');

        return bcsub((string) $this->stock($orgId, $productId, $warehouseId, $lotId)->sum('quantity'), $reserved, 4);
    }

    /** Caller owns the document/journal transaction. Scope and quota are never client flags. */
    public function record(array $attributes): StockMovement
    {
        return DB::transaction(function () use ($attributes) {
            $orgId = $attributes['org_id'];
            $productId = $attributes['product_id'] ?? null;
            $this->lockProducts($orgId, [$productId]);
            $warehouseId = $attributes['warehouse_id'] ?? null;
            $lotId = $attributes['inventory_lot_id'] ?? null;
            if ($lotId && in_array($attributes['movement_type'] ?? '', ['pos_sale', 'recipe_consumption', 'deliver_to_customer', 'pos_return'], true)) {
                abort_if(DB::table('pos_lot_recalls')->where('org_id', $orgId)->where('inventory_lot_id', $lotId)->exists(), 422, 'Lot is recalled and quarantined.');
            }
            if ($warehouseId !== null) {
                Warehouse::where('org_id', $orgId)->whereKey($warehouseId)->firstOrFail();
            }
            if ($lotId !== null) {
                InventoryLot::where('org_id', $orgId)->where('product_id', $productId)->whereKey($lotId)->firstOrFail();
            }
            $quantity = (string) $attributes['quantity'];
            abort_unless(is_numeric($quantity) && is_finite((float) $quantity), 422, 'Invalid stock quantity.');
            $quantity = is_float($attributes['quantity']) ? number_format($attributes['quantity'], 4, '.', '') : $quantity;
            abort_unless(preg_match('/^-?\d{1,14}(?:\.\d{1,4})?$/D', $quantity), 422, 'Stock quantity exceeds ledger precision.');
            $attributes['quantity'] = bcadd($quantity, '0', 4);
            if (! empty($attributes['source_key'])) {
                $identity = $attributes;
                unset($identity['source_hash']);
                ksort($identity);
                $attributes['source_hash'] = hash('sha256', json_encode($identity, JSON_THROW_ON_ERROR));
                $existing = StockMovement::where('org_id', $orgId)->where('source_key', $attributes['source_key'])->first();
                if ($existing) {
                    abort_unless(hash_equals($existing->source_hash, $attributes['source_hash']), 409, 'Stock source identity conflict.');

                    return $existing;
                }
            }
            if ($productId && bccomp($quantity, '0', 4) < 0) {
                $hasEntitlement = DB::table('pos_stock_entitlements')->where('org_id', $orgId)->where('product_id', $productId)->where('status', '!=', 'closed')->exists();
                abort_if($hasEntitlement && $warehouseId === null, 422, 'Explicit warehouse is required when POS stock entitlement exists.');
                if ($hasEntitlement && $lotId === null && $warehouseId !== null) {
                    $untracked = (string) $this->stock($orgId, $productId, $warehouseId)->whereNull('inventory_lot_id')->sum('quantity');
                    abort_if(bccomp($untracked, bcmul($quantity, '-1', 4), 4) < 0, 422, 'Explicit lot allocation is required for this stock pool.');
                }
                abort_if(bccomp($this->available($orgId, $productId, $warehouseId, $lotId), bcmul($quantity, '-1', 4), 4) < 0, 422, 'Stock movement cannot consume reserved or unavailable stock.');
            }

            return StockMovement::create($attributes);
        });
    }

    public function deductStockForSale(string $orgId, string $productId, string $quantity, ?string $warehouseId, string $date, string $currency, ?string $actorId, string $sourceKey, ?string $note = null): StockMovement
    {
        return DB::transaction(function () use ($orgId, $productId, $quantity, $warehouseId, $date, $currency, $actorId, $sourceKey, $note) {
            $this->lockProducts($orgId, [$productId]);
            abort_unless(preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', $quantity) && bccomp($quantity, '0', 4) > 0, 422, 'Invalid sale quantity.');
            $existing = StockMovement::where('org_id', $orgId)->where('source_key', $sourceKey)->first();
            if ($existing) {
                abort_unless($existing->product_id === $productId && $existing->warehouse_id === $warehouseId && bccomp($existing->quantity, bcmul($quantity, '-1', 4), 4) === 0, 409, 'Stock source identity conflict.');

                return $existing;
            }
            abort_if(bccomp($this->available($orgId, $productId, $warehouseId), $quantity, 4) < 0, 422, 'Delivery order cannot make stock negative.');
            $stock = $this->stock($orgId, $productId, $warehouseId);
            $onHand = (string) $stock->sum('quantity');
            $cost = bccomp($onHand, '0', 4) > 0 ? round((float) bcdiv((string) $stock->sum('base_total_cost'), $onHand, 8), 2) : 0;
            $movement = $this->record(['org_id' => $orgId, 'product_id' => $productId, 'warehouse_id' => $warehouseId,
                'movement_type' => 'deliver_to_customer', 'movement_date' => $date, 'quantity' => bcmul($quantity, '-1', 4),
                'unit_cost' => $cost, 'total_cost' => round((float) $quantity * -$cost, 2), 'currency' => $currency,
                'base_currency' => $currency, 'exchange_rate' => 1, 'base_unit_cost' => $cost,
                'base_total_cost' => round((float) $quantity * -$cost, 2), 'created_by' => $actorId, 'note' => $note, 'source_key' => $sourceKey]);
            DB::afterCommit(fn () => $this->notifyLowStock($orgId, $productId));

            return $movement;
        });
    }

    public function consumePosEntitlement(string $instanceId, string $grantId, int $epoch, string $quantity, string $date, string $sourceKey, string $movementType = 'pos_sale'): StockMovement
    {
        abort_unless(in_array($movementType, ['pos_sale', 'recipe_consumption', 'food_waste_spoilage'], true), 422, 'Unsupported POS consumption effect.');

        return DB::transaction(function () use ($instanceId, $grantId, $epoch, $quantity, $date, $sourceKey, $movementType) {
            $instance = DB::table('pos_instances')->where('id', $instanceId)->where('active', true)->firstOrFail();
            $reference = DB::table('pos_stock_entitlements')->where('id', $grantId)->where('pos_instance_id', $instanceId)->where('org_id', $instance->org_id)->where('warehouse_id', $instance->warehouse_id)->firstOrFail();
            $this->lockProducts($instance->org_id, [$reference->product_id]);
            $grant = DB::table('pos_stock_entitlements')->where('id', $grantId)->lockForUpdate()->firstOrFail();
            abort_unless(preg_match('/^\d{1,14}(?:\.\d{1,4})?$/D', $quantity) && bccomp($quantity, '0', 4) > 0 && $grant->epoch === $epoch && $grant->status !== 'closed', 422, 'Invalid stock entitlement consumption.');
            $existing = StockMovement::where('org_id', $instance->org_id)->where('source_key', $sourceKey)->first();
            if ($existing) {
                abort_unless($existing->movement_type === $movementType && $existing->product_id === $grant->product_id && $existing->warehouse_id === $grant->warehouse_id && $existing->inventory_lot_id === $grant->inventory_lot_id && bccomp($existing->quantity, bcmul($quantity, '-1', 4), 4) === 0, 409, 'Stock source identity conflict.');

                return $existing;
            }
            $remaining = bcsub((string) $grant->granted_quantity, (string) $grant->consumed_quantity, 4);
            abort_if(bccomp($remaining, $quantity, 4) < 0, 422, 'Stock entitlement exhausted.');
            $pool = $this->stock($instance->org_id, $grant->product_id, $grant->warehouse_id)->where('inventory_lot_id', $grant->inventory_lot_id);
            $onHand = (string) $pool->sum('quantity');
            abort_if(bccomp($onHand, $quantity, 4) < 0, 422, 'POS inventory discrepancy.');
            if ($grant->inventory_lot_id) {
                $lot = InventoryLot::where('org_id', $instance->org_id)->where('product_id', $grant->product_id)->findOrFail($grant->inventory_lot_id);
                abort_if($movementType !== 'food_waste_spoilage' && $lot->expires_at && $lot->expires_at->toDateString() < $date, 422, 'POS sale used an expired lot.');
            }
            $cost = round((float) bcdiv((string) $pool->sum('base_total_cost'), $onHand, 8), 2);
            abort_if($cost < 0, 422, 'POS inventory valuation is invalid.');
            $currency = Organization::findOrFail($instance->org_id)->currency;
            DB::table('pos_stock_entitlements')->where('id', $grantId)->update(['consumed_quantity' => bcadd((string) $grant->consumed_quantity, $quantity, 4), 'updated_at' => now()]);
            $movement = $this->record(['org_id' => $instance->org_id, 'product_id' => $grant->product_id, 'warehouse_id' => $grant->warehouse_id, 'inventory_lot_id' => $grant->inventory_lot_id,
                'movement_type' => $movementType, 'movement_date' => $date, 'quantity' => bcmul($quantity, '-1', 4), 'unit_cost' => $cost, 'base_unit_cost' => $cost,
                'total_cost' => round((float) $quantity * -$cost, 2), 'base_total_cost' => round((float) $quantity * -$cost, 2), 'currency' => $currency, 'base_currency' => $currency, 'exchange_rate' => 1, 'source_key' => $sourceKey, 'created_by' => $instance->actor_id]);
            DB::afterCommit(fn () => $this->notifyLowStock($instance->org_id, $grant->product_id));

            return $movement;
        });
    }

    private function notifyLowStock(string $orgId, string $productId): void
    {
        $product = Product::where('org_id', $orgId)->where('reorder_point', '>', 0)->find($productId);
        if (! $product) {
            return;
        }
        $onHand = $this->stock($orgId, $productId)->sum('quantity');
        if (bccomp((string) $onHand, (string) $product->reorder_point, 4) > 0) {
            return;
        }
        User::where('org_id', $orgId)->where('status', 'active')->whereHas('roles.permissions', fn ($q) => $q->whereIn('code', ['inventory.view', 'inventory.adjust']))
            ->get()->each(fn ($user) => app(NotificationService::class)->notify($user, 'inventory.low_stock', "inventory.low_stock:{$product->id}:".today()->toDateString(), "Low stock: {$product->name}", "On hand: {$onHand} {$product->unit}; reorder point: {$product->reorder_point}.", route('inventory-operations.index')));
    }
}
