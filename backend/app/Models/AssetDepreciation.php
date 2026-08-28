<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetDepreciation extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'fixed_asset_id', 'depreciation_month', 'amount', 'accumulated_depreciation_after', 'net_book_value_after', 'journal_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['depreciation_month' => 'date', 'amount' => 'decimal:2', 'accumulated_depreciation_after' => 'decimal:2', 'net_book_value_after' => 'decimal:2'];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
