<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'accounting_period_id', 'entry_no', 'posting_date', 'description', 'status', 'source_type', 'source_id', 'posting_event', 'reversal_of_id', 'posted_by', 'posted_at'];

    protected function casts(): array
    {
        return ['posting_date' => 'date', 'posted_at' => 'datetime'];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('sort_order');
    }

    public function reversalOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
