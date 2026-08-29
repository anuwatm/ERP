<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PurchaseOrder extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STATUSES = ['draft', 'sent', 'approved', 'partially_received', 'received', 'cancelled', 'closed'];

    public const TAX_MODES = ['exclusive', 'inclusive', 'no_tax'];

    protected $fillable = ['org_id', 'supplier_id', 'project_id', 'po_no', 'status', 'order_date', 'expected_date', 'tax_mode', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'currency', 'base_currency', 'exchange_rate', 'base_subtotal', 'base_tax_amount', 'base_total', 'note', 'created_by', 'updated_by', 'approved_at', 'approved_by', 'cancelled_at'];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_date' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'base_subtotal' => 'decimal:2',
            'base_tax_amount' => 'decimal:2',
            'base_total' => 'decimal:2',
            'approved_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function goodsReceipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }
}
