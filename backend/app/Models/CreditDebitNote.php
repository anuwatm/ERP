<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditDebitNote extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'invoice_id', 'note_no', 'type', 'status', 'issue_date', 'subtotal', 'tax_amount', 'total', 'reason', 'created_by'];

    protected function casts(): array
    {
        return ['issue_date' => 'date', 'subtotal' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
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
