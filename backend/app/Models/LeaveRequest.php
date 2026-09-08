<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'leave_type_id', 'starts_on', 'ends_on', 'total_days', 'reason', 'status', 'submitted_at', 'cancelled_at'];

    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date', 'submitted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
