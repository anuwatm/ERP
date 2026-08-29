<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'expense_id', 'bank_account_id', 'reversal_of_vendor_payment_id', 'entry_type', 'payment_date', 'amount', 'currency', 'base_currency', 'exchange_rate', 'base_amount', 'expense_base_amount', 'reference_no', 'note', 'idempotency_key', 'created_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'base_amount' => 'decimal:2', 'expense_base_amount' => 'decimal:2'];
    }

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_vendor_payment_id');
    }
}
