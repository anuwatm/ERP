<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;

class PayrollTaxPolicy extends Model
{
    use UsesOrderedUuid;

    protected $fillable = ['org_id', 'name', 'effective_from', 'effective_to', 'employment_expense_rate', 'employment_expense_cap', 'brackets_json', 'source_url', 'updated_by'];

    protected function casts(): array
    {
        return ['effective_from' => 'date', 'effective_to' => 'date', 'brackets_json' => 'array'];
    }
}
