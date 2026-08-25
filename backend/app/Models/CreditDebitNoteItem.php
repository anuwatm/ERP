<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditDebitNoteItem extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'credit_debit_note_id', 'description', 'quantity', 'unit_price', 'tax_rate', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:4', 'unit_price' => 'decimal:2', 'tax_rate' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    public function creditDebitNote(): BelongsTo
    {
        return $this->belongsTo(CreditDebitNote::class);
    }
}
