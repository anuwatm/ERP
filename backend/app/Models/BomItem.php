<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BomItem extends Model
{
    use UsesOrderedUuid;

    protected $table = 'bom_items';

    protected $fillable = [
        'org_id',
        'bill_of_materials_id',
        'product_id',
        'sub_bom_id',
        'quantity',
        'unit',
        'waste_percent',
        'unit_cost',
        'total_cost',
        'note',
        'sequence',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'waste_percent' => 'decimal:2',
            'unit_cost' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'sequence' => 'integer',
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

    public function subBom(): BelongsTo
    {
        return $this->belongsTo(BillOfMaterials::class, 'sub_bom_id');
    }
}
