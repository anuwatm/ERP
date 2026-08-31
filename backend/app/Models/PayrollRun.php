<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'run_no', 'period_start', 'period_end', 'payment_date', 'payroll_tax_policy_id', 'social_security_policy_id', 'bank_account_id', 'currency', 'status', 'gross_amount', 'employee_social_security_amount', 'employer_social_security_amount', 'withholding_tax_amount', 'net_pay_amount', 'created_by', 'approved_by', 'approved_at', 'paid_by', 'paid_at'];

    protected function casts(): array
    {
        return ['period_start' => 'date', 'period_end' => 'date', 'payment_date' => 'date', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    public function taxPolicy(): BelongsTo
    {
        return $this->belongsTo(PayrollTaxPolicy::class, 'payroll_tax_policy_id');
    }

    public function socialSecurityPolicy(): BelongsTo
    {
        return $this->belongsTo(SocialSecurityPolicy::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }
}
