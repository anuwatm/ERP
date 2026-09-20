<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    use UsesOrderedUuid;

    protected $fillable = [
        'org_id', 'code', 'name', 'starts_at', 'ends_at', 'break_minutes',
        'work_minutes_per_day', 'is_active', 'ot_rate_normal', 'ot_rate_holiday',
        'ot_rate_special_holiday', 'grace_period_minutes', 'overtime_starts_after_minutes',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'ot_rate_normal' => 'float',
            'ot_rate_holiday' => 'float',
            'ot_rate_special_holiday' => 'float',
            'grace_period_minutes' => 'integer',
            'overtime_starts_after_minutes' => 'integer',
        ];
    }
}
