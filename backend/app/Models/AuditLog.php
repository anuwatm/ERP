<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use App\Support\PersonIdMask;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use UsesOrderedUuid;

    public const UPDATED_AT = null;

    protected $fillable = ['org_id', 'actor_user_id', 'action', 'entity_type', 'entity_id', 'before_json', 'after_json', 'ip_address', 'user_agent', 'request_id'];

    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (AuditLog $log) {
            $log->before_json = static::redactData($log->before_json);
            $log->after_json = static::redactData($log->after_json);
        });
    }

    protected static function redactData(mixed $data): mixed
    {
        if (! is_array($data)) {
            return $data;
        }

        foreach ($data as $key => $value) {
            if (static::isSensitiveKey((string) $key)) {
                $data[$key] = '[REDACTED]';
            } elseif (strtolower((string) $key) === 'person_id') {
                $data[$key] = PersonIdMask::mask($value === null ? null : (string) $value);
            } elseif (is_array($value)) {
                $data[$key] = static::redactData($value);
            }
        }

        return $data;
    }

    protected static function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower($key);

        return str_contains($normalized, 'password')
            || str_contains($normalized, 'token')
            || str_contains($normalized, 'secret');
    }
}
