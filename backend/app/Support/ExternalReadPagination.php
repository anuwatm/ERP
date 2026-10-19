<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

final class ExternalReadPagination
{
    public static function filters(Request $request, array $rules = []): array
    {
        $rules = array_merge([
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'cursor' => ['sometimes', 'string', 'max:4096'],
            'updated_since' => ['sometimes', 'date_format:Y-m-d\TH:i:s\Z'],
        ], $rules);
        if (array_diff(array_keys($request->query()), array_keys($rules)) || ! in_array(trim($request->getContent()), ['', '[]', '{}'], true)) {
            throw ValidationException::withMessages(['filters' => 'Unsupported filters.']);
        }

        return $request->validate($rules);
    }

    public static function page(Request $request, Builder $query, array $fields, array $filters, ?callable $map = null, bool $deltaSupported = true): JsonResponse
    {
        $key = $request->attributes->get('integration_key');
        $contextFilters = $filters;
        unset($contextFilters['cursor']);
        ksort($contextFilters);
        $context = hash('sha256', json_encode([$key->id, $key->org_id, $key->scopes, $key->access_policy, $request->path(), $contextFilters]));
        $until = now()->format('Y-m-d H:i:s');
        $expires = now()->addMinutes(30)->timestamp;
        $after = null;
        if (isset($filters['cursor'])) {
            try {
                $cursor = json_decode(Crypt::decryptString($filters['cursor']), true, 16, JSON_THROW_ON_ERROR);
                if (($cursor['context'] ?? null) !== $context || ($cursor['expires'] ?? 0) <= now()->timestamp
                    || ! is_array($cursor['after'] ?? null) || count($cursor['after']) !== 2) {
                    throw new \RuntimeException;
                }
                [$until, $expires, $after] = [$cursor['until'], $cursor['expires'], $cursor['after']];
            } catch (\Throwable) {
                throw ValidationException::withMessages(['cursor' => 'The cursor is invalid or expired. Start a full reconciliation.']);
            }
        }
        if (isset($filters['updated_since'])) {
            if (CarbonImmutable::parse($filters['updated_since'])->gt(now())) {
                throw ValidationException::withMessages(['updated_since' => 'The sync checkpoint must not be in the future.']);
            }
            $query->where('updated_at', '>=', CarbonImmutable::parse($filters['updated_since'])->setTimezone(config('app.timezone')));
        }
        $query->where('updated_at', '<=', $until);
        if ($after) {
            $query->where(fn ($q) => $q->where('updated_at', '>', $after[0])->orWhere(fn ($same) => $same->where('updated_at', $after[0])->where('id', '>', $after[1])));
        }
        $limit = (int) ($filters['limit'] ?? 50);
        $rows = $query->orderBy('updated_at')->orderBy('id')->limit($limit + 1)->get(array_unique([...$fields, 'updated_at', 'id']));
        $more = $rows->count() > $limit;
        $rows = $rows->take($limit);
        $last = $rows->last();
        $next = $more ? Crypt::encryptString(json_encode([
            'context' => $context, 'until' => $until, 'expires' => $expires,
            'after' => [$last->getRawOriginal('updated_at'), $last->id],
        ])) : null;
        $data = $rows->map(function ($row) use ($fields, $map) {
            if ($row->getAttribute('deleted_at')) {
                return ['id' => $row->id, 'deleted_at' => $row->deleted_at->toIso8601String(), 'updated_at' => $row->updated_at->toIso8601String()];
            }

            return $map ? $map($row) : self::fields($row, $fields);
        })->values();

        return response()->json(['data' => $data, 'meta' => [
            'api_version' => 'v1', 'request_id' => ExternalApiResponse::requestId($request),
            'generated_at' => now()->toIso8601String(), 'next_cursor' => $next,
            'limit' => $limit, 'consistency' => 'live', 'reconciliation_required' => true,
            'delta_supported' => $deltaSupported,
            'sync_watermark' => CarbonImmutable::parse($until, config('app.timezone'))->utc()->format('Y-m-d\TH:i:s\Z'),
            'cursor_expires_at' => CarbonImmutable::createFromTimestamp($expires)->toIso8601String(),
            'visibility' => 'organization',
        ]]);
    }

    public static function fields($row, array $fields): array
    {
        $data = $row->only($fields);
        foreach ($fields as $field) {
            $cast = $row->getCasts()[$field] ?? '';
            if (($cast === 'date' || str_starts_with($cast, 'date:')) && $row->$field) {
                $data[$field] = $row->$field->toDateString();
            }
        }

        return $data;
    }
}
