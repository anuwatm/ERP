<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'goods_receipt_id', 'purchase_order_item_id', 'product_id', 'inventory_lot_id', 'description', 'quantity', 'unit', 'unit_cost', 'tax_rate', 'tax_amount', 'line_total', 'currency', 'base_currency', 'exchange_rate', 'base_unit_cost', 'base_tax_amount', 'base_line_total'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'base_unit_cost' => 'decimal:2',
            'base_tax_amount' => 'decimal:2',
            'base_line_total' => 'decimal:2',
        ];
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }
}
