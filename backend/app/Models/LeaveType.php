<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class LeaveType extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'code', 'name', 'is_paid', 'annual_entitlement_days', 'carry_over_limit_days', 'is_active'];

    protected function casts(): array
    {
        return ['is_paid' => 'boolean', 'is_active' => 'boolean'];
    }
}
