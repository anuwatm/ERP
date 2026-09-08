<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'employee_shift_id', 'work_date', 'clock_in_at', 'clock_out_at', 'clock_in_source', 'clock_out_source', 'clock_in_ip_hash', 'clock_out_ip_hash', 'clock_in_latitude', 'clock_in_longitude', 'clock_out_latitude', 'clock_out_longitude', 'clock_in_gps_accuracy_meters', 'clock_out_gps_accuracy_meters'];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }
}
