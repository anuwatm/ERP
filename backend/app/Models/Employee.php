<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use UsesOrderedUuid;

    protected $fillable = [
        'org_id', 'user_id', 'branch_id', 'division_id', 'department_id', 'position_id', 'manager_id', 'employee_code',
        'title', 'first_name', 'last_name', 'nickname', 'national_id', 'tax_id', 'date_of_birth', 'gender',
        'email', 'phone', 'position_title', 'employment_type', 'employment_status', 'started_on',
        'probation_ends_on', 'resigned_on', 'payment_method', 'bank_name', 'bank_account_number',
    ];

    protected function casts(): array
    {
        return [
            'national_id' => 'encrypted',
            'bank_account_number' => 'encrypted',
            'date_of_birth' => 'date',
            'started_on' => 'date',
            'probation_ends_on' => 'date',
            'resigned_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(self::class, 'manager_id');
    }

    public function shiftSchedules(): HasMany
    {
        return $this->hasMany(EmployeeShiftSchedule::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrollProfile()
    {
        return $this->hasOne(EmployeePayrollProfile::class);
    }

    public function workProfile()
    {
        return $this->hasOne(EmployeeWorkProfile::class);
    }
}
