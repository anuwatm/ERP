<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FxRevaluation extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'source_type', 'source_id', 'currency', 'revaluation_month', 'foreign_amount', 'closing_rate', 'base_before', 'base_after', 'difference', 'journal_entry_id', 'reversal_journal_entry_id', 'reversed_at', 'created_by'];

    protected function casts(): array
    {
        return ['revaluation_month' => 'date', 'foreign_amount' => 'decimal:2', 'closing_rate' => 'decimal:6', 'base_before' => 'decimal:2', 'base_after' => 'decimal:2', 'difference' => 'decimal:2', 'reversed_at' => 'datetime'];
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
