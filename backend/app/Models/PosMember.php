<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PosMember extends Model
{
    use UsesOrderedUuid;

    protected $guarded = [];

    protected $hidden = ['contact', 'phone_fingerprint', 'request_key', 'request_hash'];

    protected function casts(): array
    {
        return ['contact' => 'encrypted:array'];
    }
}
