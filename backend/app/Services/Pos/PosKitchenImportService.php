<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryMovementService;
use App\Services\JournalPostingService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class PosKitchenImportService
{
    public function import(object $instance, array $event): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_restaurant_enabled') === true && $instance->business_type === 'restaurant', 503, 'Local Restaurant import disabled.');
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Kitchen import requires an inbox transaction.');
        }

        return $event['event_type'] === 'kitchen.consumed' ? $this->consume($instance, $event) : $this->waste($instance, $event);
    }

    private function scope(object $instance, array $event, array $p, array $roles): Carbon
    {
        abort_unless($p['branch_id'] === $instance->branch_id && in_array($p['terminal_id'], json_decode($instance->terminal_ids, true), true) && $p['occurred_at'] === $event['occurred_at'], 403, 'Kitchen source scope mismatch.');
        Branch::where('org_id', $instance->org_id)->findOrFail($instance->branch_id);
        Warehouse::where('org_id', $instance->org_id)->findOrFail($instance->warehouse_id);
        User::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->actor_id);
        abort_unless(Organization::findOrFail($instance->org_id)->currency === $p['currency'], 422, 'Kitchen currency unsupported.');
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->whereIn('role', $roles)->where('active', true)->exists(), 403, 'Kitchen staff authority unavailable.');
        $occurred = Carbon::parse($p['occurred_at'])->utc();
        abort_if($occurred->gt(now()->addMinutes(5)), 422, 'Kitchen event time is in the future.');

        return $occurred;
    }

    private function consume(object $instance, array $event): array
    {
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'kitchen.consumed');
        $qty = ['required', 'string', 'regex:/^\d{1,10}\.\d{4}$/D'];
        $p = validator($event['payload'], ['consumption_id' => 'required|uuid', 'order_id' => 'required|uuid', 'product_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'catalog_version' => 'required|string|max:80', 'recipe_hash' => 'required|string|size:64', 'purpose' => 'required|in:preparation,waste_before_preparation', 'quantity' => $qty, 'unit' => 'required|string|max:30', 'currency' => 'required|string|size:3', 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok', 'ingredients' => 'required|array|min:1|max:100', 'ingredients.*.product_id' => 'required|uuid|distinct', 'ingredients.*.quantity' => $qty, 'ingredients.*.allocations' => 'required|array|min:1|max:100', 'ingredients.*.allocations.*.grant_id' => 'required|uuid|distinct', 'ingredients.*.allocations.*.epoch' => 'required|integer|min:1', 'ingredients.*.allocations.*.lot_id' => 'nullable|uuid', 'ingredients.*.allocations.*.quantity' => $qty])->validate();
        $waste = $p['purpose'] === 'waste_before_preparation';
        if ($waste) {
            $p += validator($event['payload'], ['waste_id' => 'required|uuid', 'reason' => 'required|in:customer_cancel,preparation_error,spoiled'])->validate();
        }
        abort_unless($p['consumption_id'] === $event['aggregate_id'] && bccomp($p['quantity'], '1', 4) === 0, 422, 'Kitchen consumption must identify one portion.');
        $occurred = $this->scope($instance, $event, $p, $waste ? ['manager'] : ['kitchen', 'manager']);
        $date = $occurred->copy()->setTimezone($p['business_timezone'])->toDateString();
        $catalog = DB::table('pos_catalog_publications')->where('pos_instance_id', $instance->id)->where('version', $p['catalog_version'])->first();
        abort_unless($catalog && $occurred->gte(Carbon::parse($catalog->created_at)) && ($waste || $occurred->lte(Carbon::parse($catalog->valid_until))), 422, 'Kitchen catalog unavailable at consumption time.');
        $published = json_decode($catalog->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($catalog->payload_hash, hash('sha256', PosCanonicalJson::encode($published))), 409, 'Kitchen catalog evidence conflict.');
        $menu = collect($published['items'])->firstWhere('product_id', $p['product_id']);
        abort_unless($menu && $menu['active'] && ! ($menu['pharmacy']['requires_pharmacist'] ?? false) && isset($menu['restaurant']) && $menu['unit'] === $p['unit'] && hash_equals($menu['restaurant']['recipe_hash'], $p['recipe_hash']), 422, 'Kitchen recipe differs from published policy.');
        $demands = collect($menu['restaurant']['ingredients'])->keyBy('product_id');
        abort_unless($demands->count() === count($p['ingredients']), 422, 'Kitchen ingredient set mismatch.');
        foreach ($p['ingredients'] as $ingredient) {
            $demand = $demands->get($ingredient['product_id']);
            $product = Product::withTrashed()->where('org_id', $instance->org_id)->find($ingredient['product_id']);
            abort_unless($demand && $product && $product->unit === $demand['unit'] && bccomp($ingredient['quantity'], $demand['quantity'], 4) === 0, 422, 'Kitchen base-unit demand mismatch.');
            $sum = '0.0000';
            foreach ($ingredient['allocations'] as $allocation) {
                $grant = DB::table('pos_stock_entitlements')->where('id', $allocation['grant_id'])->where('pos_instance_id', $instance->id)->where('org_id', $instance->org_id)->where('warehouse_id', $instance->warehouse_id)->where('product_id', $ingredient['product_id'])->first();
                abort_unless($grant && $grant->epoch === $allocation['epoch'] && $grant->inventory_lot_id === ($allocation['lot_id'] ?? null) && bccomp($allocation['quantity'], '0', 4) > 0, 422, 'Kitchen grant scope invalid.');
                if (! $waste && $grant->inventory_lot_id) {
                    $lot = DB::table('inventory_lots')->where('id', $grant->inventory_lot_id)->where('org_id', $instance->org_id)->where('product_id', $ingredient['product_id'])->first();
                    abort_unless($lot && (! $lot->expires_at || substr($lot->expires_at, 0, 10) >= $date), 422, 'Kitchen used an expired ingredient.');
                }
                $sum = bcadd($sum, $allocation['quantity'], 4);
            }
            abort_unless(bccomp($sum, $demand['quantity'], 4) === 0, 422, 'Kitchen allocation total invalid.');
        }
        $record = DB::table('pos_import_kitchen_consumptions')->where('pos_instance_id', $instance->id)->where('source_line_id', $p['consumption_id'])->first();
        if ($record) {
            abort_unless(hash_equals($record->payload_hash, $event['payload_hash']), 409, 'Kitchen source identity conflict.');
            $mapping = PosAccountMapping::forKitchen($instance->id, $record->id);
        } else {
            $mapping = $waste ? PosAccountMapping::forWaste($instance->id) : PosAccountMapping::forInstance($instance->id);
            $id = (string) Str::uuid();
            DB::table('pos_import_kitchen_consumptions')->insert(['id' => $id, 'pos_instance_id' => $instance->id, 'source_line_id' => $p['consumption_id'], 'source_order_id' => $p['order_id'], 'product_id' => $p['product_id'], 'catalog_version' => $p['catalog_version'], 'recipe_hash' => $p['recipe_hash'], 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'account_snapshot' => PosCanonicalJson::encode($mapping->snapshot($instance->org_id)), 'purpose' => $p['purpose'], 'inventory_status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
            $record = DB::table('pos_import_kitchen_consumptions')->where('id', $id)->firstOrFail();
        }
        if ($record->inventory_status === 'posted') {
            return ['status' => 'accepted', 'reason_code' => null];
        }
        try {
            DB::transaction(function () use ($instance, $p, $record, $mapping, $date, $waste, $menu) {
                $inventory = app(InventoryMovementService::class);
                $inventory->lockProducts($instance->org_id, array_column($p['ingredients'], 'product_id'));
                $cost = '0.00';
                $effect = $waste ? 'food_waste_spoilage' : ($menu['restaurant']['kind'] === 'recipe' ? 'recipe_consumption' : 'pos_sale');
                foreach ($p['ingredients'] as $ingredient) {
                    foreach ($ingredient['allocations'] as $allocation) {
                        $key = 'pos-kitchen:'.$instance->id.':'.$p['consumption_id'].':'.$ingredient['product_id'].':'.$allocation['grant_id'];
                        $movement = $inventory->consumePosEntitlement($instance->id, $allocation['grant_id'], $allocation['epoch'], $allocation['quantity'], $date, $key, $effect);
                        $cost = bcsub($cost, $movement->base_total_cost, 2);
                    }
                }
                if (bccomp($cost, '0', 2) > 0) {
                    app(JournalPostingService::class)->post($instance->org_id, $instance->actor_id, 'pos_kitchen_consumption', $record->id, 'inventory', $date, 'POS kitchen consumption', [['account_code' => $mapping->code($waste ? 'waste' : 'cogs', $instance->org_id), 'debit' => $cost], ['account_code' => $mapping->code('inventory', $instance->org_id), 'credit' => $cost]]);
                }
                DB::table('pos_import_kitchen_consumptions')->where('id', $record->id)->update(['inventory_status' => 'posted', 'base_cost' => $cost, 'updated_at' => now()]);
            });

            return ['status' => 'accepted', 'reason_code' => null];
        } catch (HttpExceptionInterface|ValidationException $e) {
            if ($e instanceof HttpExceptionInterface && $e->getStatusCode() !== 422) {
                throw $e;
            }

            return ['status' => 'inventory_pending', 'reason_code' => 'recipe_inventory_discrepancy'];
        }
    }

    private function waste(object $instance, array $event): array
    {
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'kitchen.wasted');
        $p = validator($event['payload'], ['waste_id' => 'required|uuid', 'consumption_id' => 'required|uuid', 'consumption_hash' => 'required|string|size:64', 'branch_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'reason' => 'required|in:customer_cancel,preparation_error,spoiled', 'quantity' => 'required|string|in:1.0000', 'currency' => 'required|string|size:3', 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok'])->validate();
        abort_unless($p['waste_id'] === $event['aggregate_id'], 422);
        $occurred = $this->scope($instance, $event, $p, ['manager']);
        $old = DB::table('pos_import_kitchen_waste')->where('pos_instance_id', $instance->id)->where('source_waste_id', $p['waste_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->payload_hash, $event['payload_hash']), 409, 'Kitchen waste source conflict.');

            return ['status' => 'accepted', 'reason_code' => null];
        }
        $parent = DB::table('pos_import_kitchen_consumptions')->where('pos_instance_id', $instance->id)->where('source_line_id', $p['consumption_id'])->first();
        if (! $parent || $parent->inventory_status !== 'posted') {
            throw new PosDependencyPending('kitchen_posted', [$p['consumption_id']]);
        }
        $original = json_decode($parent->source_snapshot, true);
        abort_unless($parent->purpose === 'preparation' && hash_equals($parent->payload_hash, $p['consumption_hash']) && $original['branch_id'] === $p['branch_id'] && $original['currency'] === $p['currency'] && $occurred->gte(Carbon::parse($original['occurred_at'])), 422, 'Kitchen waste parent evidence invalid.');
        abort_if(DB::table('pos_import_kitchen_waste')->where('consumption_id', $parent->id)->exists() || DB::table('pos_import_kitchen_sales')->where('pos_instance_id', $instance->id)->where('source_kitchen_line_id', $p['consumption_id'])->exists(), 409, 'Kitchen portion already wasted or billed.');
        $mapping = PosAccountMapping::forWaste($instance->id, $parent->id);
        $id = (string) Str::uuid();
        $journal = null;
        if (bccomp((string) $parent->base_cost, '0', 2) > 0) {
            $journal = app(JournalPostingService::class)->post($instance->org_id, $instance->actor_id, 'pos_kitchen_waste', $id, 'reclassified', $occurred->setTimezone($p['business_timezone'])->toDateString(), 'POS prepared food waste', [['account_code' => $mapping->code('waste', $instance->org_id), 'debit' => $parent->base_cost], ['account_code' => $mapping->code('cogs', $instance->org_id), 'credit' => $parent->base_cost]]);
        }
        DB::table('pos_import_kitchen_waste')->insert(['id' => $id, 'pos_instance_id' => $instance->id, 'source_waste_id' => $p['waste_id'], 'consumption_id' => $parent->id, 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'account_snapshot' => PosCanonicalJson::encode($mapping->snapshot($instance->org_id)), 'journal_entry_id' => $journal?->id, 'created_at' => now(), 'updated_at' => now()]);

        return ['status' => 'accepted', 'reason_code' => null];
    }
}
