<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PortalUser extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'party_type', 'party_id', 'email', 'name', 'is_active', 'last_login_at'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'last_login_at' => 'datetime'];
    }
}
