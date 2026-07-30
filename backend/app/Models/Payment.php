<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use UsesOrderedUuid;

    public const ENTRY_TYPES = ['receipt', 'reversal'];

    public const METHODS = ['bank_transfer', 'cash', 'credit_card', 'promptpay', 'other'];

    protected $fillable = ['org_id', 'invoice_id', 'entry_type', 'reversal_of_payment_id', 'amount', 'payment_date', 'payment_method', 'reference_no', 'attachment_file_id', 'note', 'idempotency_key', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_payment_id');
    }

    public function reversals(): HasMany
    {
        return $this->hasMany(self::class, 'reversal_of_payment_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'attachment_file_id');
    }
}
