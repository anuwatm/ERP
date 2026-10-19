<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class BillOfMaterials extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $table = 'bill_of_materials';

    protected $fillable = [
        'org_id',
        'bom_no',
        'product_id',
        'name',
        'version',
        'quantity',
        'unit',
        'routing_notes',
        'is_active',
        'labor_cost',
        'overhead_cost',
        'total_cost',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'labor_cost' => 'decimal:2',
            'overhead_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(BomItem::class, 'bill_of_materials_id')->orderBy('sequence');
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'bill_of_materials_id');
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
