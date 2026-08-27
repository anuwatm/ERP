<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reconciliation extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'bank_statement_line_id', 'reconcilable_type', 'reconcilable_id', 'match_method', 'note', 'matched_by', 'matched_at', 'unmatched_by', 'unmatched_at', 'unmatch_note'];

    protected function casts(): array
    {
        return ['matched_at' => 'datetime', 'unmatched_at' => 'datetime'];
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankStatementLine::class, 'bank_statement_line_id');
    }
}
