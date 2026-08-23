<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class NotificationPreference extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'type', 'email_enabled', 'in_app_enabled'];

    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
        ];
    }
}
