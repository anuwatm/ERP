<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class GatewayTransaction extends Model
{
    use UsesOrderedUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'expires_at' => 'datetime', 'settled_at' => 'datetime'];
    }
}
