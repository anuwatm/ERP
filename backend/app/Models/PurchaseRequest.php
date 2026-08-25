<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseRequest extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'supplier_id', 'pr_no', 'status', 'request_date', 'total', 'reason', 'approved_at', 'approved_by', 'converted_at', 'converted_po_id', 'created_by'];

    protected function casts(): array
    {
        return ['request_date' => 'date', 'total' => 'decimal:2', 'approved_at' => 'datetime', 'converted_at' => 'datetime'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class);
    }
}
