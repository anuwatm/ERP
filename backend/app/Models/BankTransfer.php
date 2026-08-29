<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankTransfer extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'source_bank_account_id', 'destination_bank_account_id', 'transfer_date', 'source_amount', 'source_currency', 'source_base_amount', 'destination_amount', 'destination_currency', 'destination_base_amount', 'reference_no', 'note', 'idempotency_key', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'source_amount' => 'decimal:2', 'source_base_amount' => 'decimal:2', 'destination_amount' => 'decimal:2', 'destination_base_amount' => 'decimal:2'];
    }

    public function sourceBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'source_bank_account_id');
    }

    public function destinationBankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'destination_bank_account_id');
    }
}
