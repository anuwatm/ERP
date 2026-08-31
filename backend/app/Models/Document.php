<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use SoftDeletes, UsesOrderedUuid;

    protected $fillable = ['org_id', 'category_id', 'retention_policy_id', 'owner_user_id', 'document_no', 'title', 'sensitivity', 'status', 'expires_at', 'renewal_alert_days', 'legal_hold', 'retention_until', 'current_version_id'];

    protected function casts(): array
    {
        return ['expires_at' => 'date', 'retention_until' => 'datetime', 'legal_hold' => 'boolean'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class);
    }

    public function links(): HasMany
    {
        return $this->hasMany(DocumentLink::class);
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(DocumentCategory::class);
    }
}
