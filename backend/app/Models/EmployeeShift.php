<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class EmployeeShift extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'code', 'name', 'starts_at', 'ends_at', 'break_minutes', 'work_minutes_per_day', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
