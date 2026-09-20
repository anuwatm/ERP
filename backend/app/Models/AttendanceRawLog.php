<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceRawLog extends Model
{
    use UsesOrderedUuid;

    protected $fillable = [
        'org_id', 'batch_id', 'employee_code', 'employee_id', 'punch_time',
        'punch_type', 'device_id', 'status', 'error_reason',
    ];

    protected function casts(): array
    {
        return [
            'punch_time' => 'datetime',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
