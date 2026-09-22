<?php

namespace App\Models;

use App\Models\Concerns\UsesOrderedUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashFlowForecastSnapshot extends Model
{
    use UsesOrderedUuid;

    protected $fillable = [
        'org_id',
        'forecast_scenario_id',
        'scenario_key',
        'as_of_date',
        'horizon_days',
        'starting_cash',
        'projected_inflow',
        'projected_outflow',
        'net_cash_flow',
        'projected_ending_cash',
        'lowest_cash_point',
        'lowest_cash_date',
        'breakdown_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'as_of_date' => 'date',
            'horizon_days' => 'integer',
            'starting_cash' => 'decimal:2',
            'projected_inflow' => 'decimal:2',
            'projected_outflow' => 'decimal:2',
            'net_cash_flow' => 'decimal:2',
            'projected_ending_cash' => 'decimal:2',
            'lowest_cash_point' => 'decimal:2',
            'lowest_cash_date' => 'date',
            'breakdown_json' => 'array',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function scenario(): BelongsTo
    {
        return $this->belongsTo(ForecastScenario::class, 'forecast_scenario_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
