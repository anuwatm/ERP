<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkOrder extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $table = 'work_orders';

    protected $fillable = [
        'org_id',
        'wo_no',
        'bill_of_materials_id',
        'product_id',
        'warehouse_id',
        'source_warehouse_id',
        'planned_quantity',
        'actual_quantity',
        'scrap_quantity',
        'status',
        'start_date',
        'due_date',
        'completed_date',
        'material_cost',
        'labor_cost',
        'overhead_cost',
        'total_cost',
        'unit_cost',
        'notes',
        'qc_notes',
        'qc_passed',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'planned_quantity' => 'decimal:4',
            'actual_quantity' => 'decimal:4',
            'scrap_quantity' => 'decimal:4',
            'material_cost' => 'decimal:2',
            'labor_cost' => 'decimal:2',
            'overhead_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'start_date' => 'date',
            'due_date' => 'date',
            'completed_date' => 'date',
            'qc_passed' => 'boolean',
        ];
    }

    public function billOfMaterials(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class, 'bill_of_materials_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class, 'work_order_id');
    }

    public function productionCosts(): HasMany
    {
        return $this->hasMany(ProductionCost::class, 'work_order_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class, 'work_order_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
