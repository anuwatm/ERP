<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceSummary extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'reversal_of_id', 'period_start', 'period_end', 'cutoff_date', 'scheduled_minutes', 'worked_minutes', 'overtime_minutes', 'paid_leave_days', 'unpaid_leave_days', 'status', 'locked_by', 'locked_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'cutoff_date' => 'date', 'locked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reversedSummary(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversal_of_id');
    }
}
