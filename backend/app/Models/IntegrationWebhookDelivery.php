<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class IntegrationWebhookDelivery extends Model
{
    use UsesOrderedUuid;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['available_at' => 'immutable_datetime', 'locked_until' => 'immutable_datetime', 'completed_at' => 'immutable_datetime'];
    }

    public function webhook()
    {
        return $this->belongsTo(IntegrationWebhook::class, 'integration_webhook_id');
    }

    public function event()
    {
        return $this->belongsTo(IntegrationWebhookEvent::class, 'integration_webhook_event_id');
    }
}
