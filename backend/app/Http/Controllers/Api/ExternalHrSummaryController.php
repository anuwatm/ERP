<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExternalHrSummaryService;
use App\Support\ExternalApiResponse;
use App\Support\ExternalPeriod;
use App\Support\ExternalReadPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalHrSummaryController extends Controller
{
    public function show(Request $request, string $domain, ExternalHrSummaryService $service): JsonResponse
    {
        $filters = ExternalReadPagination::filters($request, array_merge(ExternalPeriod::rules(), [
            'limit' => ['prohibited'], 'cursor' => ['prohibited'], 'updated_since' => ['prohibited'],
        ]));
        [$start, $end, $zone, $now] = ExternalPeriod::resolve($request, $filters);

        return response()->json(['data' => $service->summarize($request, $domain, $start, $end, $now), 'meta' => [
            'api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request), 'generated_at' => $now->toIso8601String(),
            'snapshot_as_of' => $now->toIso8601String(), 'period' => ['start_date' => $start, 'end_date' => $end],
            'timezone' => $zone, 'currency_mode' => 'not_applicable', 'visibility' => 'credential_hr_policy', 'freshness' => 'live',
        ]]);
    }
}
