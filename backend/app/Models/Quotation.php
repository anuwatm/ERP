<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Quotation extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STATUSES = ['draft', 'sent', 'approved', 'rejected', 'expired', 'converted'];

    public const TAX_MODES = ['exclusive', 'inclusive', 'no_tax'];

    protected $fillable = ['org_id', 'branch_id', 'quotation_no', 'customer_id', 'deal_id', 'status', 'tax_mode', 'issue_date', 'valid_until', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'currency', 'notes', 'sent_at', 'approved_at', 'rejected_at', 'expired_at', 'converted_at', 'converted_invoice_id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'expired_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class)->orderBy('sort_order');
    }

    public function convertedInvoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'converted_invoice_id');
    }
}
