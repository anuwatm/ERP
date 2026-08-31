<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollItem extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'payroll_run_id', 'employee_payroll_profile_id', 'user_id', 'salary_amount', 'allowance_amount', 'other_deduction_amount', 'employee_social_security_amount', 'employer_social_security_amount', 'withholding_tax_amount', 'net_pay_amount', 'calculation_snapshot'];

    protected function casts(): array
    {
        return ['calculation_snapshot' => 'array'];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(EmployeePayrollProfile::class, 'employee_payroll_profile_id');
    }
}
