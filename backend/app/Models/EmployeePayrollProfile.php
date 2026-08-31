<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayrollProfile extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'user_id', 'monthly_salary', 'fixed_allowance', 'fixed_deduction', 'annual_tax_allowance', 'tax_id', 'social_security_enabled', 'payment_method', 'payment_reference', 'status', 'updated_by'];

    protected function casts(): array
    {
        return ['social_security_enabled' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
