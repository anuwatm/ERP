<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryOrder extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'invoice_id', 'do_no', 'status', 'delivery_date', 'receiver_name', 'delivered_at', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['delivery_date' => 'date', 'delivered_at' => 'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryOrderItem::class);
    }
}
