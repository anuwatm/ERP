<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use UsesOrderedUuid;

    public const UPDATED_AT = null;

    protected $fillable = ['org_id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'before_json', 'after_json', 'ip_address', 'user_agent', 'request_id'];

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
