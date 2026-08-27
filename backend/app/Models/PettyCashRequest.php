<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PettyCashRequest extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'petty_cash_fund_id', 'request_no', 'requester_id', 'amount', 'expense_date', 'purpose', 'status', 'approved_by', 'approved_at', 'paid_by', 'paid_at', 'rejection_reason'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'expense_date' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }
}
