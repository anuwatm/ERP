<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\InventoryLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryMovementService;
use App\Services\JournalPostingService;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PosReturnInspectionImportService
{
    /** Called within the inbox's instance-locked transaction. */
    public function import(object $instance, array $event): array
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Inspection import requires an inbox transaction.');
        }
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_return_inspections_enabled') === true && $instance->business_type === 'pharmacy', 503, 'Local pharmacy inspection disabled.');
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'return.inspected');
        User::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->actor_id);
        Branch::where('org_id', $instance->org_id)->findOrFail($instance->branch_id);
        Warehouse::where('org_id', $instance->org_id)->findOrFail($instance->warehouse_id);
        $p = validator($event['payload'], ['inspection_id' => 'required|uuid', 'sale_id' => 'required|uuid', 'refund_id' => 'required|uuid', 'source_line_id' => 'required|uuid', 'grant_id' => 'required|uuid', 'base_quantity' => ['required', 'string', 'regex:/^\d{1,10}\.\d{4}$/D'], 'decision' => 'required|in:restock,destroy', 'packaging_intact' => 'required|boolean', 'storage_verified' => 'required|boolean', 'lot_verified' => 'required|boolean', 'reason' => 'required|string|max:500', 'branch_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'product_id' => 'required|uuid', 'lot_id' => 'required|uuid', 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok'])->validate();
        abort_unless($p['inspection_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && $p['occurred_at'] === $event['occurred_at'] && bccomp($p['base_quantity'], '0', 4) > 0, 422, 'Inspection scope invalid.');
        abort_unless(DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('role', 'manager')->where('active', true)->where('pharmacist_qualified', true)->exists(), 403, 'Registered pharmacist-qualified manager required.');
        $occurred = Carbon::parse($p['occurred_at']);
        abort_if($occurred->gt(now()->addMinutes(5)), 422, 'Inspection time is in the future.');
        $old = DB::table('pos_import_return_inspections')->where('pos_instance_id', $instance->id)->where('source_inspection_id', $p['inspection_id'])->first();
        if ($old) {
            abort_unless(hash_equals($old->payload_hash, $event['payload_hash']), 409, 'Inspection source conflict.');

            return ['status' => 'accepted', 'reason_code' => null];
        }
        $refund = DB::table('pos_import_refunds')->where('pos_instance_id', $instance->id)->where('source_refund_id', $p['refund_id'])->first();
        if (! $refund) {
            throw new PosDependencyPending('refund', [$p['refund_id']]);
        }
        $sale = DB::table('pos_import_sales')->where('id', $refund->sale_id)->where('pos_instance_id', $instance->id)->where('source_sale_id', $p['sale_id'])->firstOrFail();
        abort_unless($sale->inventory_status === 'posted', 422, 'Original sale stock must be posted before inspection.');
        $source = collect(json_decode($sale->source_snapshot, true)['lines'])->firstWhere('line_id', $p['source_line_id']);
        $refundSnapshot = json_decode($refund->source_snapshot, true);
        $returned = collect($refundSnapshot['lines'])->firstWhere('source_line_id', $p['source_line_id']);
        abort_unless($source && $returned && isset($source['conversion_factor'], $source['base_step']) && $source['product_id'] === $p['product_id'] && ($source['stock_effect'] ?? null) !== 'prepared' && $occurred->gte(Carbon::parse($refundSnapshot['occurred_at'])), 422, 'Original return evidence invalid.');
        $allocation = collect($source['allocations'])->firstWhere('grant_id', $p['grant_id']);
        $grant = DB::table('pos_stock_entitlements')->where('id', $p['grant_id'])->where('pos_instance_id', $instance->id)->where('org_id', $instance->org_id)->where('warehouse_id', $instance->warehouse_id)->where('product_id', $p['product_id'])->first();
        abort_unless($allocation && $grant && $grant->inventory_lot_id === $p['lot_id'] && ($allocation['lot_id'] ?? null) === $p['lot_id'] && isset($source['base_step']) && bccomp(bcmod($p['base_quantity'], $source['base_step'], 4), '0', 4) === 0, 422, 'Inspection lot or precision invalid.');
        $inventory = app(InventoryMovementService::class);
        $inventory->lockProducts($instance->org_id, [$p['product_id']]);
        $prior = DB::table('pos_import_return_inspections')->where('sale_id', $sale->id)->where('source_line_id', $p['source_line_id'])->get();
        $sameRefund = $prior->where('refund_id', $refund->id)->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->base_quantity, 4), $p['base_quantity']);
        $sameLot = $prior->where('grant_id', $p['grant_id'])->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->base_quantity, 4), $p['base_quantity']);
        abort_if(bccomp($sameRefund, bcmul($returned['returned_quantity'], $source['conversion_factor'], 4), 4) > 0 || bccomp($sameLot, $allocation['quantity'], 4) > 0, 422, 'Inspection exceeds returned source quantity.');
        $cost = '0.00';
        $id = (string) Str::uuid();
        if ($p['decision'] === 'restock') {
            $date = $occurred->copy()->setTimezone('Asia/Bangkok')->toDateString();
            $lot = InventoryLot::where('org_id', $instance->org_id)->where('product_id', $p['product_id'])->findOrFail($p['lot_id']);
            abort_unless($p['packaging_intact'] && $p['storage_verified'] && $p['lot_verified'] && (! $lot->expires_at || ($lot->expires_at->toDateString() >= $date && $lot->expires_at->toDateString() >= now('Asia/Bangkok')->toDateString())), 422, 'Unverified or expired goods cannot be restocked.');
            $originalKey = 'pos:'.$instance->id.':'.$p['sale_id'].':'.$p['source_line_id'].':'.$p['grant_id'];
            $movement = DB::table('stock_movements')->where('org_id', $instance->org_id)->where('source_key', $originalKey)->where('product_id', $p['product_id'])->where('warehouse_id', $instance->warehouse_id)->where('inventory_lot_id', $p['lot_id'])->firstOrFail();
            abort_unless(bccomp((string) $movement->quantity, bcmul($allocation['quantity'], '-1', 4), 4) === 0 && bccomp((string) $movement->base_total_cost, '0', 2) <= 0, 422, 'Original stock valuation invalid.');
            $restocked = $prior->where('grant_id', $p['grant_id'])->where('decision', 'restock');
            $qty = $restocked->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->base_quantity, 4), $p['base_quantity']);
            $oldCost = $restocked->reduce(fn ($sum, $r) => bcadd($sum, (string) $r->base_cost, 2), '0.00');
            $cost = bcsub(bcadd(bcdiv(bcmul(bcmul((string) $movement->base_total_cost, '-1', 2), $qty, 8), $allocation['quantity'], 8), '0.005', 2), $oldCost, 2);
            abort_if(bccomp($cost, '0', 2) < 0, 422, 'Invalid cumulative return cost.');
            $inventory->record(['org_id' => $instance->org_id, 'product_id' => $p['product_id'], 'warehouse_id' => $instance->warehouse_id, 'inventory_lot_id' => $p['lot_id'], 'movement_type' => 'pos_return', 'movement_date' => $date, 'quantity' => $p['base_quantity'], 'unit_cost' => $movement->base_unit_cost, 'total_cost' => $cost, 'currency' => $movement->base_currency, 'base_currency' => $movement->base_currency, 'exchange_rate' => '1', 'base_unit_cost' => $movement->base_unit_cost, 'base_total_cost' => $cost, 'created_by' => $instance->actor_id, 'source_key' => 'pos-inspection:'.$instance->id.':'.$p['inspection_id']]);
            if (bccomp($cost, '0', 2) > 0) {
                $mapping = PosAccountMapping::forSale($instance->id, $sale->id);
                app(JournalPostingService::class)->post($instance->org_id, $instance->actor_id, 'pos_return_inspection', $id, 'restocked', $date, 'Inspected pharmacy return', [['account_code' => $mapping->code('inventory', $instance->org_id), 'debit' => $cost], ['account_code' => $mapping->code('cogs', $instance->org_id), 'credit' => $cost]]);
            }
        }
        // Destroyed goods were already expensed by the original sale; never issue stock twice.
        DB::table('pos_import_return_inspections')->insert(['id' => $id, 'pos_instance_id' => $instance->id, 'source_inspection_id' => $p['inspection_id'], 'refund_id' => $refund->id, 'sale_id' => $sale->id, 'source_line_id' => $p['source_line_id'], 'grant_id' => $p['grant_id'], 'base_quantity' => $p['base_quantity'], 'base_cost' => $cost, 'decision' => $p['decision'], 'payload_hash' => $event['payload_hash'], 'source_snapshot' => PosCanonicalJson::encode($p), 'created_at' => now(), 'updated_at' => now()]);

        return ['status' => 'accepted', 'reason_code' => null];
    }
}
