<?php

namespace App\Support;

use Carbon\CarbonImmutable;

final class DashboardDate
{
    public static function now(): CarbonImmutable
    {
        return CarbonImmutable::now(request()->user()?->organization?->timezone ?: config('app.timezone'));
    }

    public static function range($query, string $column, ?string $from, ?string $to)
    {
        // Business DATE columns are already local. Timestamps are stored in the
        // application timezone and need local-midnight boundaries converted back.
        $timestamp = str_ends_with($column, '_at');
        $zone = self::now()->timezone;
        if ($from) {
            $timestamp
                ? $query->where($column, '>=', CarbonImmutable::parse($from, $zone)->startOfDay()->setTimezone(config('app.timezone')))
                : $query->whereDate($column, '>=', $from);
        }
        if ($to) {
            $timestamp
                ? $query->where($column, '<', CarbonImmutable::parse($to, $zone)->addDay()->startOfDay()->setTimezone(config('app.timezone')))
                : $query->whereDate($column, '<=', $to);
        }

        return $query;
    }
}
