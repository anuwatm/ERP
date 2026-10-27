<?php

namespace App\Services\Pos;

use App\Models\BillOfMaterials;
use App\Models\Product;
use App\Support\PosCanonicalJson;

class PosRecipeSnapshotService
{
    /** Lightweight material demands; existing WorkOrder/labor/overhead postings are not invoked per plate. */
    public function build(string $orgId, Product $menu, ?string $bomId): array
    {
        if ($bomId === null) {
            $body = ['kind' => 'stocked', 'recipe_id' => null, 'recipe_version' => 'stock-v1', 'yield_quantity' => '1.0000', 'yield_unit' => $menu->unit, 'ingredients' => [['product_id' => $menu->id, 'unit' => $menu->unit, 'quantity' => '1.0000']]];
        } else {
            $bom = BillOfMaterials::where('org_id', $orgId)->where('product_id', $menu->id)->where('is_active', true)->with('items.product')->findOrFail($bomId);
            abort_unless($bom->unit === $menu->unit && bccomp($bom->quantity, '0', 4) > 0 && $bom->items->isNotEmpty() && $bom->items->count() <= 100, 422, 'Recipe yield/unit unsupported.');
            $ingredients = [];
            foreach ($bom->items as $item) {
                $product = $item->product;
                abort_unless(! $item->sub_bom_id && $product && $product->org_id === $orgId && ! $product->trashed() && $product->is_active && $product->type === 'product' && $item->unit === $product->unit && bccomp($item->quantity, '0', 4) > 0 && bccomp($item->waste_percent, '0', 2) >= 0, 422, 'Recipe requires flat base-unit ingredients.');
                $raw = bcdiv(bcdiv(bcmul($item->quantity, bcadd('100', $item->waste_percent, 2), 12), '100', 12), $bom->quantity, 12);
                $quantity = bcadd($raw, '0', 4);
                abort_unless(bccomp($quantity, '0', 4) > 0 && bccomp($raw, $quantity, 12) === 0, 422, 'Recipe demand must be exactly representable to four base decimals per portion.');
                if (isset($ingredients[$product->id])) {
                    $ingredients[$product->id]['quantity'] = bcadd($ingredients[$product->id]['quantity'], $quantity, 4);
                } else {
                    $ingredients[$product->id] = ['product_id' => $product->id, 'unit' => $product->unit, 'quantity' => $quantity];
                }
            }
            ksort($ingredients);
            $body = ['kind' => 'recipe', 'recipe_id' => $bom->id, 'recipe_version' => (string) $bom->version, 'yield_quantity' => $bom->quantity, 'yield_unit' => $bom->unit, 'ingredients' => array_values($ingredients)];
        }

        return $body + ['recipe_hash' => hash('sha256', PosCanonicalJson::encode($body))];
    }
}
