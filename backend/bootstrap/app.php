<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureTwoFactorEnrollment;
use App\Http\Middleware\HandleInertiaRequests;
use App\Support\ExternalApiResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/gateway.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'permission' => EnsurePermission::class,
        ]);

        $middleware->web(append: [
            EnsureTwoFactorEnrollment::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception) {
            $request = request();
            if ($request->is('api/v1', 'api/v1/*')) {
                Log::error('external_summary_api_error', [
                    'request_id' => ExternalApiResponse::requestId($request),
                    'exception_class' => $exception::class,
                ]);

                // Do not serialize request credentials, query bindings or exception messages.
                return false;
            }

            return null;
        });
        $exceptions->render(function (Throwable $exception, Request $request) {
            if (! $request->is('api/v1', 'api/v1/*')) {
                return null;
            }
            $status = $exception instanceof ValidationException ? 422
                : ($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
            [$code, $message] = match ($status) {
                401 => ['unauthorized', 'A valid integration key is required.'],
                403 => ['forbidden', 'The integration credential does not allow this resource.'],
                409 => ['conflict', 'The request conflicts with the current resource state.'],
                404 => ['not_found', 'The summary endpoint was not found.'],
                405 => ['method_not_allowed', 'This API only supports GET requests.'],
                422 => ['invalid_filters', 'Use valid start_date and end_date within the allowed date window. No other filters are supported.'],
                default => ['server_error', 'Unable to generate the summary.'],
            };

            return ExternalApiResponse::error($request, $status, $code, $message);
        });
    })->create();
