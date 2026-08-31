<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class SocialSecurityPolicy extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'name', 'effective_from', 'effective_to', 'employee_rate', 'employer_rate', 'wage_ceiling', 'source_url', 'updated_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date'];
    }
}
