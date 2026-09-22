<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialMetricLog extends Model
{
    use UsesOrderedUuid;

    protected $fillable = [
        'org_id',
        'as_of_date',
        'current_ratio',
        'quick_ratio',
        'dso_days',
        'dpo_days',
        'working_capital',
        'working_capital_runway_months',
        'monthly_burn_rate',
        'current_cash',
        'current_ar',
        'current_ap',
        'inventory_value',
        'metadata_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'current_ratio' => 'decimal:2',
            'quick_ratio' => 'decimal:2',
            'dso_days' => 'decimal:1',
            'dpo_days' => 'decimal:1',
            'working_capital' => 'decimal:2',
            'working_capital_runway_months' => 'decimal:1',
            'monthly_burn_rate' => 'decimal:2',
            'current_cash' => 'decimal:2',
            'current_ar' => 'decimal:2',
            'current_ap' => 'decimal:2',
            'inventory_value' => 'decimal:2',
            'metadata_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
