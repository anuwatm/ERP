<?php

namespace App\Services\Pos;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\PosCanonicalJson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PosSaleValidator
{
    public function validate(object $instance, array $event): array
    {
        $event = app(PosEventVerifier::class)->verify($instance, $event, 'sale.completed');
        validator($event['payload'], ['lines.*' => 'array:line_id,product_id,name,quantity,tax_rate,subtotal,discount_amount,tax_amount,total,allocations,unit,unit_price,base_quantity,base_step,conversion_factor,conversion_version,pharmacist_id,stock_effect,kitchen_refs', 'lines.*.allocations.*' => 'array:grant_id,epoch,lot_id,quantity', 'lines.*.kitchen_refs.*' => 'array:line_id,consumption_hash'])->validate();
        $p = validator($event['payload'], ['sale_id' => 'required|uuid', 'branch_id' => 'required|uuid', 'terminal_id' => 'required|uuid', 'shift_id' => 'required|uuid', 'staff_id' => 'required|uuid', 'receipt_no' => 'required|string|max:100', 'catalog_version' => 'required|string|max:80', 'currency' => 'required|string|size:3', 'subtotal' => $this->money(), 'tax_amount' => $this->money(), 'total' => $this->money(), 'cash_tendered' => $this->money(), 'change' => $this->money(), 'occurred_at' => 'required|date', 'business_timezone' => 'required|in:Asia/Bangkok', 'lines' => 'required|array|min:1|max:100', 'lines.*.line_id' => 'required|uuid|distinct', 'lines.*.product_id' => 'required|uuid|distinct', 'lines.*.quantity' => $this->quantity(), 'lines.*.unit_price' => $this->money(), 'lines.*.subtotal' => $this->money(), 'lines.*.tax_amount' => $this->money(), 'lines.*.total' => $this->money(), 'lines.*.tax_rate' => ['required', 'string', 'regex:/^\d{1,2}(\.\d{1,2})?$/D'], 'lines.*.unit' => 'required|string|max:30', 'lines.*.name' => 'required|string|max:255', 'lines.*.allocations' => 'present|array|max:100', 'lines.*.allocations.*.grant_id' => 'required|uuid', 'lines.*.allocations.*.epoch' => 'required|integer|min:1', 'lines.*.allocations.*.quantity' => $this->quantity(), 'lines.*.allocations.*.lot_id' => 'nullable|uuid'])->validate();
        $p = array_merge($p, validator($event['payload'], ['discount_approval_id' => 'sometimes|uuid', 'discount_approved_by' => 'sometimes|uuid', 'discount_approved_at' => 'sometimes|date'])->validate());
        abort_unless($p['sale_id'] === $event['aggregate_id'] && $p['branch_id'] === $instance->branch_id && in_array($p['terminal_id'], json_decode($instance->terminal_ids, true), true) && $p['occurred_at'] === $event['occurred_at'], 403, 'POS sale scope mismatch.');
        Branch::where('org_id', $instance->org_id)->findOrFail($instance->branch_id);
        Warehouse::where('org_id', $instance->org_id)->findOrFail($instance->warehouse_id);
        Customer::where('org_id', $instance->org_id)->findOrFail($instance->customer_id);
        User::where('org_id', $instance->org_id)->where('status', 'active')->findOrFail($instance->actor_id);
        abort_unless(Organization::findOrFail($instance->org_id)->currency === $p['currency'], 422, 'POS currency unsupported.');
        $occurred = Carbon::parse($p['occurred_at'])->utc();
        abort_if($occurred->gt(now()->addMinutes(5)), 422, 'POS event time is in the future.');
        $catalog = DB::table('pos_catalog_publications')->where('pos_instance_id', $instance->id)->where('version', $p['catalog_version'])->first();
        abort_unless($catalog && ($instance->business_type === 'restaurant' || $occurred->lte(Carbon::parse($catalog->valid_until))) && $occurred->gte(Carbon::parse($catalog->created_at)), 422, 'POS catalog version unavailable at sale time.');
        $published = json_decode($catalog->payload, true, flags: JSON_THROW_ON_ERROR);
        abort_unless(hash_equals($catalog->payload_hash, hash('sha256', PosCanonicalJson::encode($published))), 409, 'ERP catalog evidence conflict.');
        $items = collect($published['items'])->keyBy('product_id');
        $subtotal = '0.00';
        $tax = '0.00';
        $discountTotal = '0.00';
        $missingKitchen = [];
        $seenKitchen = [];
        foreach ($p['lines'] as $index => $line) {
            $metadata = validator($event['payload']['lines'][$index], [
                'base_quantity' => ['sometimes', 'string', 'regex:/^\d{1,14}(\.\d{1,4})?$/D'],
                'conversion_factor' => ['sometimes', 'string', 'regex:/^\d{1,8}(\.\d{1,4})?$/D'],
                'conversion_version' => 'sometimes|string|max:80',
                'pharmacist_id' => 'nullable|uuid',
                'discount_amount' => ['sometimes', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'],
                'stock_effect' => 'sometimes|in:prepared',
                'kitchen_refs' => 'sometimes|array|min:1|max:100',
                'kitchen_refs.*.line_id' => 'required|uuid|distinct',
                'kitchen_refs.*.consumption_hash' => 'required|string|size:64',
            ])->validate();
            $line = array_merge($line, $metadata);
            $p['lines'][$index] = $line;
            $item = $items->get($line['product_id']);
            abort_unless($item && $item['active'] && $item['tax_rate'] === $line['tax_rate'] && bccomp($line['quantity'], '0', 4) > 0, 422, 'POS line differs from published policy.');
            $pharmacy = $item['pharmacy'] ?? [];
            $prepared = ($line['stock_effect'] ?? null) === 'prepared';
            abort_unless(($instance->business_type === 'restaurant') === $prepared, 422, 'Restaurant receipts require prepared kitchen evidence.');
            abort_if(! $prepared && isset($line['kitchen_refs']), 422, 'Unexpected kitchen references.');
            if ($prepared) {
                abort_unless(isset($line['kitchen_refs']), 422, 'Kitchen references required.');
                foreach ($line['kitchen_refs'] as $ref) {
                    abort_if(isset($seenKitchen[$ref['line_id']]), 422, 'Kitchen portion repeated in this sale.');
                    $seenKitchen[$ref['line_id']] = true;
                }
                abort_if(count($seenKitchen) > 100, 422, 'Prepared sale exceeds portion limit.');
                $missingKitchen = array_merge($missingKitchen, app(PosKitchenSalePolicy::class)->validateLine($instance, $line, $item, $p['catalog_version'], $p['sale_id'], $p['currency']));
            }
            $p['lines'][$index]['base_step'] = $prepared ? '1.0000' : ($pharmacy['base_step'] ?? '0.0001');
            $unit = $line['unit'] === $item['unit'] ? ['unit' => $item['unit'], 'price' => $item['price'], 'factor' => '1.0000', 'conversion_version' => 'base'] : collect($pharmacy['units'] ?? [])->firstWhere('unit', $line['unit']);
            abort_unless($unit && $unit['price'] === $line['unit_price'], 422, 'POS selling unit or price invalid.');
            abort_if($line['unit'] !== $item['unit'] && ! isset($line['base_quantity'], $line['conversion_factor'], $line['conversion_version']), 422, 'POS conversion evidence required.');
            $rawBase = bcmul($line['quantity'], $unit['factor'], 8);
            $baseQuantity = bcadd($rawBase, '0', 4);
            abort_unless(bccomp($rawBase, $baseQuantity, 8) === 0 && bccomp(bcmod($baseQuantity, $pharmacy['base_step'] ?? '0.0001', 4), '0', 4) === 0
                && (! isset($line['base_quantity']) || bccomp($line['base_quantity'], $baseQuantity, 4) === 0)
                && (! isset($line['conversion_factor']) || bccomp($line['conversion_factor'], $unit['factor'], 4) === 0)
                && (! isset($line['conversion_version']) || $line['conversion_version'] === $unit['conversion_version']), 422, 'POS conversion snapshot invalid.');
            if ($pharmacy['requires_pharmacist'] ?? false) {
                abort_unless(($line['pharmacist_id'] ?? null) === $p['staff_id'] && DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['staff_id'])->where('role', 'pharmacist')->where('active', true)->exists(), 403, 'POS pharmacist authority unavailable.');
            }
            Product::withTrashed()->where('org_id', $instance->org_id)->findOrFail($line['product_id']);
            $lineSubtotal = bcadd(bcmul($line['quantity'], $unit['price'], 8), '0.005', 2);
            $discount = $line['discount_amount'] ?? '0.00';
            $maximum = bcdiv(bcmul($lineSubtotal, $item['max_manager_discount_pct'] ?? '0.00', 8), '100', 2);
            abort_if(bccomp($discount, $maximum, 2) > 0 || bccomp($discount, $lineSubtotal, 2) > 0, 422, 'POS discount exceeds published manager policy.');
            $lineSubtotal = bcsub($lineSubtotal, $discount, 2);
            $discountTotal = bcadd($discountTotal, $discount, 2);
            $lineTax = bcadd(bcdiv(bcmul($lineSubtotal, $item['tax_rate'], 8), '100', 8), '0.005', 2);
            abort_unless(bccomp($lineSubtotal, $line['subtotal'], 2) === 0 && bccomp($lineTax, $line['tax_amount'], 2) === 0 && bccomp(bcadd($lineSubtotal, $lineTax, 2), $line['total'], 2) === 0, 422, 'POS line totals invalid.');
            if (! $prepared) {
                abort_unless(count($line['allocations']) > 0, 422, 'Stock allocations required.');
            }
            $allocated = '0.0000';
            $grantIds = [];
            foreach ($line['allocations'] as $allocation) {
                abort_unless(bccomp($allocation['quantity'], '0', 4) > 0 && ! in_array($allocation['grant_id'], $grantIds, true), 422, 'POS allocation invalid.');
                $grantIds[] = $allocation['grant_id'];
                $grant = DB::table('pos_stock_entitlements')->where('id', $allocation['grant_id'])->where('pos_instance_id', $instance->id)->where('org_id', $instance->org_id)->where('warehouse_id', $instance->warehouse_id)->where('product_id', $line['product_id'])->first();
                abort_unless($grant && $grant->epoch === $allocation['epoch'] && $grant->inventory_lot_id === ($allocation['lot_id'] ?? null), 422, 'POS allocation scope mismatch.');
                $allocated = bcadd($allocated, $allocation['quantity'], 4);
            }
            abort_unless($prepared || bccomp($allocated, $baseQuantity, 4) === 0, 422, 'POS allocation total invalid.');
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $tax = bcadd($tax, $lineTax, 2);
        }
        if (bccomp($discountTotal, '0', 2) > 0) {
            abort_unless(app()->environment(['local', 'testing']) && config('pos.local_discounts_enabled') === true && isset($p['discount_approval_id'], $p['discount_approved_by'], $p['discount_approved_at']), 403, 'POS discount approval required.');
            $approved = Carbon::parse($p['discount_approved_at'])->utc();
            abort_unless($approved->lte($occurred) && $approved->gte($occurred->copy()->subMinutes(5)) && DB::table('pos_staff_authorities')->where('pos_instance_id', $instance->id)->where('source_staff_id', $p['discount_approved_by'])->where('role', 'manager')->where('active', true)->exists(), 403, 'POS discount manager authority or approval time invalid.');
        } else {
            abort_if(isset($p['discount_approval_id']) || isset($p['discount_approved_by']) || isset($p['discount_approved_at']), 422, 'Unexpected discount approval on an undiscounted sale.');
        }
        abort_unless(bccomp($subtotal, $p['subtotal'], 2) === 0 && bccomp($tax, $p['tax_amount'], 2) === 0 && bccomp(bcadd($subtotal, $tax, 2), $p['total'], 2) === 0 && bccomp($p['total'], '0', 2) > 0 && bccomp(bcsub($p['cash_tendered'], $p['change'], 2), $p['total'], 2) === 0, 422, 'POS cash totals invalid.');

        if ($missingKitchen !== []) {
            throw new PosDependencyPending('kitchen_consumption', array_values(array_unique($missingKitchen)));
        }

        return $p;
    }

    private function money(): array
    {
        return ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,2})?$/D'];
    }

    private function quantity(): array
    {
        return ['required', 'string', 'regex:/^\d{1,10}(\.\d{1,4})?$/D'];
    }
}
