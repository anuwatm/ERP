<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BankStatementLine extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'bank_statement_id', 'bank_account_id', 'transaction_date', 'amount_signed', 'balance_after', 'currency', 'base_currency', 'exchange_rate', 'base_amount_signed', 'base_balance_after', 'description', 'reference_no', 'row_fingerprint', 'status'];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'amount_signed' => 'decimal:2', 'balance_after' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'base_amount_signed' => 'decimal:2', 'base_balance_after' => 'decimal:2'];
    }

    public function statement(): BelongsTo
    {
        return $this->belongsTo(BankStatement::class, 'bank_statement_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciliation(): HasOne
    {
        return $this->hasOne(Reconciliation::class)->latestOfMany();
    }
}
