<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationChannel extends Model
{
    use UsesOrderedUuid;

    protected $hidden = ['config'];

    protected $fillable = ['org_id', 'channel', 'name', 'config', 'is_enabled', 'created_by'];

    protected function casts(): array
    {
        return ['config' => 'encrypted:array', 'is_enabled' => 'boolean'];
    }
}
