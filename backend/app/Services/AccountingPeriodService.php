<?php

namespace App\Services;

use App\Models\AccountingPeriod;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class AccountingPeriodService
{
    public function openPeriodFor(string $orgId, CarbonInterface|string $date): AccountingPeriod
    {
        $date = is_string($date) ? Carbon::parse($date) : $date;
        $period = AccountingPeriod::where('org_id', $orgId)
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderBy('start_date')
            ->first();

        if (! $period) {
            if (AccountingPeriod::where('org_id', $orgId)->exists()) {
                throw ValidationException::withMessages(['posting_date' => 'No accounting period covers the posting date.']);
            }
            $period = AccountingPeriod::create([
                'org_id' => $orgId,
                'name' => 'FY '.$date->year,
                'start_date' => $date->copy()->startOfYear()->toDateString(),
                'end_date' => $date->copy()->endOfYear()->toDateString(),
                'status' => 'open',
            ]);
        }

        if ($period->status !== 'open') {
            throw ValidationException::withMessages(['posting_date' => 'Accounting period is closed.']);
        }

        return $period;
    }

    public function assertOpen(string $orgId, CarbonInterface|string $date): void
    {
        $this->openPeriodFor($orgId, $date);
    }
}
