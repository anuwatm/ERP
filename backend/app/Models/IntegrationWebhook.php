<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class IntegrationWebhook extends Model
{
    use UsesOrderedUuid;

    protected $guarded = ['id'];

    protected $hidden = ['signing_secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'signing_secret' => 'encrypted', 'verified_at' => 'immutable_datetime'];
    }

    public function key()
    {
        return $this->belongsTo(IntegrationApiKey::class, 'integration_api_key_id');
    }
}
