<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationApiKey;
use App\Services\ExternalSummaryService;
use App\Support\ExternalApiResponse;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SummaryController extends Controller
{
    public function sales(Request $request, ExternalSummaryService $summaries): JsonResponse
    {
        return $this->summary($request, $summaries, 'sales');
    }

    public function finance(Request $request, ExternalSummaryService $summaries): JsonResponse
    {
        return $this->summary($request, $summaries, 'finance');
    }

    public function projects(Request $request, ExternalSummaryService $summaries): JsonResponse
    {
        return $this->summary($request, $summaries, 'projects');
    }

    private function summary(Request $request, ExternalSummaryService $summaries, string $domain): JsonResponse
    {
        /** @var IntegrationApiKey $key */
        $key = $request->attributes->get('integration_key');
        $zone = $key->organization->timezone ?: config('app.timezone');
        $now = CarbonImmutable::now($zone);
        if (array_diff(array_keys($request->query()), ['start_date', 'end_date']) || ! in_array(trim($request->getContent()), ['', '[]', '{}'], true)) {
            throw ValidationException::withMessages(['filters' => 'Only start_date and end_date are supported.']);
        }
        $filters = $request->validate([
            'start_date' => ['bail', 'sometimes', 'required', 'string', 'date_format:Y-m-d'],
            'end_date' => ['bail', 'sometimes', 'required', 'string', 'date_format:Y-m-d'],
        ]);
        if (count($filters) === 1) {
            throw ValidationException::withMessages(['filters' => 'Supply both dates or neither.']);
        }
        $start = $filters['start_date'] ?? $now->startOfMonth()->toDateString();
        $end = $filters['end_date'] ?? $now->endOfMonth()->toDateString();
        if ($end < $start) {
            throw ValidationException::withMessages(['end_date' => 'The end date must not precede the start date.']);
        }
        if (CarbonImmutable::parse($start, $zone)->diffInDays(CarbonImmutable::parse($end, $zone)) + 1 > config('external_api.max_date_days')) {
            throw ValidationException::withMessages(['end_date' => 'The date window is too large.']);
        }

        return response()->json([
            'data' => $summaries->$domain($key->org_id, $start, $end, $zone, $now),
            'meta' => [
                'api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request),
                'generated_at' => $now->toIso8601String(), 'timezone' => $zone,
                'period' => ['start_date' => $start, 'end_date' => $end],
                'snapshot_as_of' => $now->toIso8601String(),
                'currency_mode' => match ($domain) {
                    'sales' => 'original_currency',
                    'finance' => 'stored_base_currency',
                    'projects' => 'not_applicable',
                },
                'visibility' => 'organization', 'freshness' => 'live',
            ],
        ]);
    }
}
