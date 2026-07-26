<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    use UsesOrderedUuid;

    public const ENTITY_TYPES = ['customer', 'contact', 'deal'];

    public const ACTIVITY_TYPES = ['call', 'meeting', 'email', 'line', 'note', 'system'];

    protected $fillable = ['org_id', 'entity_type', 'entity_id', 'activity_type', 'subject', 'body', 'activity_at', 'follow_up_at', 'completed_at', 'owner_id', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'activity_at' => 'datetime',
            'follow_up_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
