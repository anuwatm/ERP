<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class TwoFactorTrustedDevice extends Model
{
    use UsesOrderedUuid;
    protected $fillable = ['user_id', 'token_hash', 'expires_at', 'last_used_at', 'user_agent_hash'];
    protected function casts(): array { return ['expires_at' => 'datetime', 'last_used_at' => 'datetime']; }
}
