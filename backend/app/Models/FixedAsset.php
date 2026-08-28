<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FixedAsset extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    public const STATUSES = ['active', 'disposed', 'written_off'];

    public const METHODS = ['straight_line'];

    protected $fillable = ['org_id', 'asset_category_id', 'asset_no', 'name', 'description', 'capitalization_source_type', 'capitalization_source_id', 'acquisition_date', 'available_for_use_date', 'depreciation_start_date', 'cost', 'salvage_value', 'useful_life_months', 'depreciation_method', 'accumulated_depreciation', 'net_book_value', 'last_depreciated_for', 'status', 'location', 'custodian_user_id', 'attachment_file_id', 'disposed_at', 'disposal_proceeds', 'disposal_reason', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['acquisition_date' => 'date', 'available_for_use_date' => 'date', 'depreciation_start_date' => 'date', 'last_depreciated_for' => 'date', 'disposed_at' => 'date', 'cost' => 'decimal:2', 'salvage_value' => 'decimal:2', 'accumulated_depreciation' => 'decimal:2', 'net_book_value' => 'decimal:2', 'disposal_proceeds' => 'decimal:2'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_user_id');
    }

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(StoredFile::class, 'attachment_file_id');
    }

    public function depreciations(): HasMany
    {
        return $this->hasMany(AssetDepreciation::class);
    }
}
