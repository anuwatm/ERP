<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $fillable = ['org_id', 'sku', 'name', 'type', 'category', 'unit', 'price', 'cost', 'is_active', 'description', 'track_inventory', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'cost' => 'decimal:2',
            'is_active' => 'boolean',
            'track_inventory' => 'boolean',
        ];
    }
}
