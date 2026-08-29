<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\FxRevaluationService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunFxRevaluation extends Command
{
    protected $signature = 'fx:revalue {--month= : Month to post in YYYY-MM; defaults to the current month}';

    protected $description = 'Revalue open foreign-currency receivables, payables and FCD at month-end rates';

    public function handle(FxRevaluationService $fx): int
    {
        $month = $this->option('month') ?: now()->format('Y-m');
        Carbon::createFromFormat('Y-m', $month);
        $count = 0;
        foreach (Organization::where('status', 'active')->orderBy('id')->get(['id']) as $organization) {
            $count += $fx->revalueReceivables($organization->id, $month, null);
            $count += $fx->revaluePayables($organization->id, $month, null);
            $count += $fx->revalueFcd($organization->id, $month, null);
        }
        $this->info("Posted {$count} FX revaluation(s) for {$month}.");

        return self::SUCCESS;
    }
}
