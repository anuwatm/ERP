<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ForecastScenario extends Model
{
    use UsesOrderedUuid;

    public const DEFAULT_SCENARIOS = [
        'base' => [
            'name' => 'Base Case',
            'scenario_key' => 'base',
            'is_default' => true,
            'deal_probability_weighting' => true,
            'deal_multiplier' => 1.00,
            'ar_collection_rate' => 1.00,
            'ar_delay_days' => 0,
            'ap_payment_rate' => 1.00,
            'expense_buffer_percent' => 0.00,
            'payroll_buffer_percent' => 0.00,
            'assumptions_json' => [
                'description' => 'Normal operations: 100% AR collected on due date, standard pipeline probability weighting, expected AP and payroll.',
            ],
        ],
        'best' => [
            'name' => 'Best Case (Optimistic)',
            'scenario_key' => 'best',
            'is_default' => false,
            'deal_probability_weighting' => true,
            'deal_multiplier' => 1.20,
            'ar_collection_rate' => 1.00,
            'ar_delay_days' => -5,
            'ap_payment_rate' => 1.00,
            'expense_buffer_percent' => 0.00,
            'payroll_buffer_percent' => 0.00,
            'assumptions_json' => [
                'description' => 'Optimistic outlook: accelerated AR collection (-5 days), deal pipeline conversion boosted by 20%.',
            ],
        ],
        'worst' => [
            'name' => 'Worst Case (Stress Test)',
            'scenario_key' => 'worst',
            'is_default' => false,
            'deal_probability_weighting' => false,
            'deal_multiplier' => 0.00,
            'ar_collection_rate' => 0.80,
            'ar_delay_days' => 15,
            'ap_payment_rate' => 1.00,
            'expense_buffer_percent' => 10.00,
            'payroll_buffer_percent' => 5.00,
            'assumptions_json' => [
                'description' => 'Severe stress test: 20% AR default/haircut, 15 days collection delay, 0% unclosed pipeline deals, 10% emergency expense buffer.',
            ],
        ],
    ];

    protected $fillable = [
        'org_id',
        'name',
        'scenario_key',
        'is_default',
        'deal_probability_weighting',
        'deal_multiplier',
        'ar_collection_rate',
        'ar_delay_days',
        'ap_payment_rate',
        'expense_buffer_percent',
        'payroll_buffer_percent',
        'assumptions_json',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'deal_probability_weighting' => 'boolean',
            'deal_multiplier' => 'decimal:2',
            'ar_collection_rate' => 'decimal:2',
            'ar_delay_days' => 'integer',
            'ap_payment_rate' => 'decimal:2',
            'expense_buffer_percent' => 'decimal:2',
            'payroll_buffer_percent' => 'decimal:2',
            'assumptions_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(CashFlowForecastSnapshot::class, 'forecast_scenario_id');
    }
}
