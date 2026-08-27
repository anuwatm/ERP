<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class Cheque extends Model
{
    use UsesOrderedUuid;

    public const STATUSES = ['registered', 'deposited', 'cleared', 'bounced', 'cancelled'];

    protected $fillable = ['org_id', 'bank_account_id', 'bank_statement_line_id', 'direction', 'cheque_no', 'bank_name', 'drawer_or_payee', 'amount', 'issue_date', 'due_date', 'status', 'cleared_at', 'bounced_at', 'cancelled_at', 'status_reason', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'issue_date' => 'date', 'due_date' => 'date', 'cleared_at' => 'datetime', 'bounced_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }
}
