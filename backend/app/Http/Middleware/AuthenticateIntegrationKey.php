<?php

namespace App\Http\Middleware;

use App\Models\IntegrationApiKey;
use App\Support\ExternalApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthenticateIntegrationKey
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        $started = microtime(true);
        $id = ExternalApiResponse::requestId($request);
        if (app()->isProduction() && ! $request->isSecure()) {
            return ExternalApiResponse::error($request, 426, 'https_required', 'HTTPS is required for this API.');
        }
        $ipBucket = 'external-api-invalid:'.hash('sha256', (string) $request->ip());
        $token = $request->bearerToken();
        $key = null;
        if (is_string($token) && preg_match('/\A(erp_[a-f0-9]{24})\.([a-f0-9]{64})\z/D', $token, $parts)) {
            $key = IntegrationApiKey::where('prefix', $parts[1])->first();
        }
        if (! $key || ! hash_equals($key->secret_hash, hash('sha256', (string) $token)) || $key->revoked_at || $key->expires_at->lte(now()) || $key->organization?->status !== 'active') {
            if (RateLimiter::tooManyAttempts($ipBucket, config('external_api.invalid_requests_per_minute'))) {
                $this->record($id, null, $request, 429, $started);

                return $this->limited($request, $ipBucket);
            }
            RateLimiter::hit($ipBucket, 60);
            $response = ExternalApiResponse::error($request, 401, 'unauthorized', 'A valid integration key is required.');
            $this->record($id, null, $request, 401, $started);

            return $response;
        }

        $request->attributes->set('integration_key', $key);
        $bucket = 'external-api-key:'.$key->id;
        $limit = (int) config('external_api.requests_per_minute');
        if (RateLimiter::tooManyAttempts($bucket, $limit)) {
            $this->record($id, $key->id, $request, 429, $started);

            return $this->limited($request, $bucket);
        }
        RateLimiter::hit($bucket, 60);
        IntegrationApiKey::whereKey($key->id)->update(['last_used_at' => now()]);

        try {
            $response = in_array($scope, $key->scopes, true) && $key->allowsPrincipalScope($scope)
                ? $next($request)
                : ExternalApiResponse::error($request, 403, 'forbidden', 'The integration key does not allow this summary.');
        } catch (\Throwable $exception) {
            $this->record($id, $key->id, $request, $exception instanceof HttpException ? $exception->getStatusCode() : ($exception instanceof ValidationException ? 422 : 500), $started);
            throw $exception;
        }
        $this->record($id, $key->id, $request, $response->getStatusCode(), $started);

        $response->headers->add([
            'Cache-Control' => 'no-store, private', 'X-Request-Id' => $id,
            'X-RateLimit-Limit' => $limit,
            'X-RateLimit-Remaining' => max(0, $limit - RateLimiter::attempts($bucket)),
        ]);

        return $response;
    }

    private function limited(Request $request, string $bucket): Response
    {
        return ExternalApiResponse::error($request, 429, 'rate_limited', 'Too many requests. Please retry later.')
            ->header('Retry-After', max(1, RateLimiter::availableIn($bucket)))
            ->header('X-RateLimit-Remaining', 0);
    }

    private function record(string $id, ?string $keyId, Request $request, int $status, float $started): void
    {
        Log::info('external_summary_api', [
            'request_id' => $id, 'key_id' => $keyId, 'endpoint' => $request->route()?->getName(),
            'status' => $status, 'duration_ms' => (int) ((microtime(true) - $started) * 1000),
        ]);
    }
}
