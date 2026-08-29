<?php

namespace App\Services;

use App\Models\ExchangeRate;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class FxRateService
{
    public function snapshot(string $orgId, string $currency, string $date, array $amounts): array
    {
        $baseCurrency = strtoupper((string) (Organization::findOrFail($orgId)->currency ?: 'THB'));
        $currency = strtoupper($currency);
        $rate = $currency === $baseCurrency ? 1.0 : $this->rate($orgId, $baseCurrency, $currency, $date);

        $snapshot = [
            'base_currency' => $baseCurrency,
            'exchange_rate' => number_format($rate, 6, '.', ''),
        ];

        foreach ($amounts as $field => $amount) {
            $snapshot['base_'.$field] = round((float) $amount * $rate, 2);
        }

        return $snapshot;
    }

    public function rate(string $orgId, string $baseCurrency, string $quoteCurrency, string $date): float
    {
        $rate = ExchangeRate::query()
            ->where('org_id', $orgId)
            ->where('base_currency', strtoupper($baseCurrency))
            ->where('quote_currency', strtoupper($quoteCurrency))
            ->whereDate('rate_date', '<=', Carbon::parse($date)->toDateString())
            ->latest('rate_date')
            ->value('rate');

        if ($rate === null) {
            throw ValidationException::withMessages([
                'currency' => "No exchange rate is configured for {$quoteCurrency} on or before {$date}.",
            ]);
        }

        return (float) $rate;
    }
}
