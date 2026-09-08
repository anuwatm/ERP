<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeWorkProfile extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'employee_shift_id', 'manager_user_id', 'employee_code', 'employment_type', 'started_on', 'ended_on'];

    protected function casts(): array
    {
        return ['started_on' => 'date', 'ended_on' => 'date'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }
}
