<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\FxRevaluationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ReverseFxRevaluation extends Command
{
    protected $signature = 'fx:reverse-revaluations {--month= : Revaluation month YYYY-MM; defaults to the previous month}';

    protected $description = 'Reverse prior-month foreign exchange revaluation journals';

    public function handle(FxRevaluationService $fx): int
    {
        $month = $this->option('month') ?: now()->subMonth()->format('Y-m');
        Carbon::createFromFormat('Y-m', $month);
        $count = 0;
        foreach (Organization::where('status', 'active')->orderBy('id')->get(['id']) as $organization) {
            $count += $fx->reverseMonth($organization->id, $month, null);
        }
        $this->info("Reversed {$count} FX revaluation(s) for {$month}.");

        return self::SUCCESS;
    }
}
