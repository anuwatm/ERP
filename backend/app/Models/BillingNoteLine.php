<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingNoteLine extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'billing_note_id', 'invoice_id', 'amount_due'];

    protected function casts(): array
    {
        return ['amount_due' => 'decimal:2'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
