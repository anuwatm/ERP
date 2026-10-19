<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderItem extends Model
{
    use UsesOrderedUuid;

    protected $table = 'work_order_items';

    protected $fillable = [
        'org_id',
        'work_order_id',
        'product_id',
        'planned_quantity',
        'issued_quantity',
        'unit',
        'unit_cost',
        'total_cost',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'issued_quantity' => 'decimal:4',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
