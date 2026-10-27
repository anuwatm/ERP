<?php

namespace App\Models;

use App\Models\Concerns\TransactionalExternalEvents;
use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use TransactionalExternalEvents, UsesOrderedUuid;

    protected $fillable = ['org_id', 'product_id', 'goods_receipt_id', 'purchase_order_id', 'warehouse_id', 'warehouse_bin_id', 'inventory_lot_id', 'stock_transfer_id', 'stock_count_id', 'work_order_id', 'movement_type', 'movement_date', 'quantity', 'unit_cost', 'total_cost', 'currency', 'base_currency', 'exchange_rate', 'base_unit_cost', 'base_total_cost', 'note', 'created_by', 'source_key', 'source_hash'];

    protected function casts(): array
    {
        return [
            'movement_date' => 'date',
            'quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'base_unit_cost' => 'decimal:2',
            'base_total_cost' => 'decimal:2',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }
}
