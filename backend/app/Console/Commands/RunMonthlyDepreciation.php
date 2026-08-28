<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Services\FixedAssetService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RunMonthlyDepreciation extends Command
{
    protected $signature = 'assets:depreciate {--month= : Month to post in YYYY-MM; defaults to the previous month}';

    protected $description = 'Post straight-line monthly depreciation for all organizations';

    public function handle(FixedAssetService $assets): int
    {
        $month = $this->option('month') ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth() : now()->subMonth()->startOfMonth();
        $count = 0;
        Organization::where('status', 'active')->orderBy('id')->chunkById(100, function ($organizations) use ($assets, $month, &$count): void {
            foreach ($organizations as $organization) {
                $count += $assets->runThroughMonth($organization->id, $month);
            }
        });
        $this->info("Posted {$count} depreciation record(s) for {$month->format('Y-m')}.");

        return self::SUCCESS;
    }
}
