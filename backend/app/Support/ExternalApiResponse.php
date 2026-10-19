<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class ExternalApiResponse
{
    public static function requestId(Request $request): string
    {
        if (! $request->attributes->has('external_request_id')) {
            $request->attributes->set('external_request_id', (string) Str::uuid());
        }

        return $request->attributes->get('external_request_id');
    }

    public static function error(Request $request, int $status, string $code, string $message): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => $message, 'request_id' => self::requestId($request)], $status)
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Request-Id', self::requestId($request));
    }
}
