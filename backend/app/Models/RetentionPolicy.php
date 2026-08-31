<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class RetentionPolicy extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'code', 'name', 'minimum_retention_days', 'effective_from', 'effective_to', 'legal_hold_required'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'legal_hold_required' => 'boolean'];
    }
}
