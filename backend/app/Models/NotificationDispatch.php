<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationDispatch extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['notification_outbox_id', 'channel', 'attempt_no', 'status', 'response_code', 'error_message', 'dispatched_at'];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime'];
    }

    public function outbox(): BelongsTo
    {
        return $this->belongsTo(NotificationOutbox::class, 'notification_outbox_id');
    }
}
