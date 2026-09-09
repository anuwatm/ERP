<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PortalSession extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['portal_user_id', 'token_hash', 'user_agent_hash', 'ip_hash', 'expires_at', 'revoked_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'revoked_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }
}
