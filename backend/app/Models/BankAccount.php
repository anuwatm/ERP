<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccount extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const ACCOUNT_TYPES = ['savings', 'current', 'cash', 'petty_cash'];

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = ['org_id', 'branch_id', 'bank_name', 'bank_code', 'branch_name', 'account_name', 'account_number', 'account_number_hash', 'account_type', 'currency', 'is_cash_account', 'status', 'opening_balance', 'opening_balance_date', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'is_cash_account' => 'boolean',
            'opening_balance' => 'decimal:2',
            'opening_balance_date' => 'date',
        ];
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function maskedAccountNumber(): string
    {
        $number = (string) $this->account_number;
        $lastFour = substr($number, -4);

        return str_repeat('*', max(0, strlen($number) - strlen($lastFour))).$lastFour;
    }
}
