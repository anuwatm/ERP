<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class DeliveryOrderItem extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'delivery_order_id', 'product_id', 'description', 'quantity', 'unit'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4'];
    }
}
