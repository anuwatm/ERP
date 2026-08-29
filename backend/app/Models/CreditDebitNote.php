<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditDebitNote extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'invoice_id', 'note_no', 'type', 'status', 'issue_date', 'subtotal', 'tax_amount', 'total', 'currency', 'base_currency', 'exchange_rate', 'base_subtotal', 'base_tax_amount', 'base_total', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'base_subtotal' => 'decimal:2', 'base_tax_amount' => 'decimal:2', 'base_total' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CreditDebitNoteItem::class);
    }
}
