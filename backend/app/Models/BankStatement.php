<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankStatement extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'bank_account_id', 'statement_date_from', 'statement_date_to', 'opening_balance', 'closing_balance', 'currency', 'base_currency', 'exchange_rate', 'base_opening_balance', 'base_closing_balance', 'line_count', 'status', 'imported_by', 'imported_at'];

    protected function casts(): array
    {
        return ['statement_date_from' => 'date', 'statement_date_to' => 'date', 'opening_balance' => 'decimal:2', 'closing_balance' => 'decimal:2', 'exchange_rate' => 'decimal:6', 'base_opening_balance' => 'decimal:2', 'base_closing_balance' => 'decimal:2', 'imported_at' => 'datetime'];
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(BankStatementLine::class);
    }
}
