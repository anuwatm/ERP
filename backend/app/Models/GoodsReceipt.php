<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GoodsReceipt extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'purchase_order_id', 'grn_no', 'received_date', 'currency', 'base_currency', 'exchange_rate', 'status', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['received_date' => 'date', 'exchange_rate' => 'decimal:6'];
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }
}
