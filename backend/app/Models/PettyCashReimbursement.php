<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PettyCashReimbursement extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'petty_cash_fund_id', 'bank_account_id', 'amount', 'reimbursed_at', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'reimbursed_at' => 'date'];
    }
}
