<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ExternalPeriod
{
    public static function rules(): array
    {
        return ['start_date' => ['sometimes', 'required', 'date_format:Y-m-d'],
            'end_date' => ['sometimes', 'required', 'date_format:Y-m-d']];
    }

    public static function resolve(Request $request, array $filters): array
    {
        $key = $request->attributes->get('integration_key');
        $zone = $key->organization->timezone ?: config('app.timezone');
        $now = CarbonImmutable::now($zone);
        if (isset($filters['start_date']) !== isset($filters['end_date'])) {
            throw ValidationException::withMessages(['period' => 'Supply both dates or neither.']);
        }
        $start = $filters['start_date'] ?? $now->startOfMonth()->toDateString();
        $end = $filters['end_date'] ?? $now->endOfMonth()->toDateString();
        if ($end < $start || CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1 > 366) {
            throw ValidationException::withMessages(['period' => 'Use an ordered date range of at most 366 days.']);
        }

        return [$start, $end, $zone, $now];
    }
}
