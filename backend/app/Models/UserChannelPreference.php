<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class UserChannelPreference extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'channel', 'enabled', 'quiet_hours_start', 'quiet_hours_end', 'urgent_only'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'urgent_only' => 'boolean'];
    }
}
