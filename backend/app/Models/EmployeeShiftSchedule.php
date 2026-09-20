<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeShiftSchedule extends Model
{
    use UsesOrderedUuid;

    protected $fillable = [
        'org_id', 'employee_id', 'employee_shift_id', 'schedule_date', 'is_day_off', 'note',
    ];

    protected function casts(): array
    {
        return [
            'schedule_date' => 'date',
            'is_day_off' => 'boolean',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }
}
