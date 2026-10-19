<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class IntegrationWebhookEvent extends Model
{
    use UsesOrderedUuid;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
