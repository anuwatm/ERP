<?php

namespace App\Services\Pos;

use Illuminate\Support\Facades\DB;

class PosKitchenSalePolicy
{
    public function validateLine(object $instance, array $line, array $menu, string $version, string $sourceSaleId, string $currency): array
    {
        abort_unless(app()->environment(['local', 'testing']) && config('pos.local_restaurant_enabled') === true && $instance->business_type === 'restaurant' && isset($menu['restaurant']) && $line['allocations'] === [] && $line['unit'] === $menu['unit'] && bccomp($line['quantity'], (string) count($line['kitchen_refs']), 4) === 0, 422, 'Prepared sale requires frozen whole-portion kitchen references.');
        $missing = [];
        foreach ($line['kitchen_refs'] as $ref) {
            $parent = DB::table('pos_import_kitchen_consumptions')->where('pos_instance_id', $instance->id)->where('source_line_id', $ref['line_id'])->first();
            if (! $parent) {
                $missing[] = $ref['line_id'];

                continue;
            }
            $origin = json_decode($parent->source_snapshot, true, flags: JSON_THROW_ON_ERROR);
            abort_unless($parent->purpose === 'preparation' && $parent->product_id === $line['product_id'] && $parent->catalog_version === $version && $origin['branch_id'] === $instance->branch_id && $origin['currency'] === $currency && hash_equals($parent->recipe_hash, $menu['restaurant']['recipe_hash']) && hash_equals($parent->payload_hash, $ref['consumption_hash']), 422, 'Prepared sale kitchen evidence mismatch.');
            abort_if(DB::table('pos_import_kitchen_waste')->where('consumption_id', $parent->id)->exists(), 409, 'Wasted kitchen portion cannot be billed.');
            $claim = DB::table('pos_import_kitchen_sales as k')->join('pos_import_sales as s', 's.id', '=', 'k.sale_id')->where('k.pos_instance_id', $instance->id)->where('k.source_kitchen_line_id', $ref['line_id'])->first(['s.source_sale_id']);
            abort_if($claim && $claim->source_sale_id !== $sourceSaleId, 409, 'Kitchen portion already billed by another source sale.');
        }

        return $missing;
    }

    public function assertPosted(object $instance, array $line): void
    {
        foreach ($line['kitchen_refs'] as $ref) {
            $parent = DB::table('pos_import_kitchen_consumptions')->where('pos_instance_id', $instance->id)->where('source_line_id', $ref['line_id'])->where('inventory_status', 'posted')->first();
            abort_unless($parent && $parent->base_cost !== null, 422, 'Kitchen stock/COGS is still pending.');
        }
    }
}
