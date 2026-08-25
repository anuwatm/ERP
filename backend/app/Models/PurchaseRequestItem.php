<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PurchaseRequestItem extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'purchase_request_id', 'product_id', 'description', 'quantity', 'unit', 'unit_price', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'line_total' => 'decimal:2'];
    }
}
