<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationOutbox extends Model
{
    use UsesOrderedUuid;

    protected $table = 'notification_outbox';

    protected $fillable = ['org_id', 'user_id', 'event_type', 'channel', 'priority', 'payload', 'idempotency_key', 'status', 'attempt_count', 'available_at', 'sent_at', 'failed_at', 'last_error'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'available_at' => 'datetime', 'sent_at' => 'datetime', 'failed_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dispatches(): HasMany
    {
        return $this->hasMany(NotificationDispatch::class);
    }
}
