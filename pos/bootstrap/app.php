<?php

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Log;
use Pos\Http\ConnectorAuthentication;
use Pos\Http\LocalPublicMenu;
use Pos\Http\StaffAuthentication;
use Pos\Providers\PosServiceProvider;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/connector.php', commands: __DIR__.'/../routes/console.php', apiPrefix: 'connector/v1', health: '/up')
    ->withProviders([PosServiceProvider::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['pos.staff' => StaffAuthentication::class, 'pos.connector' => ConnectorAuthentication::class, 'pos.local-menu' => LocalPublicMenu::class]);
    })->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['member_name', 'member_phone', 'manager_password', 'manager_email', 'setup_token', 'token', 'password', 'password_confirmation', 'recipient', 'phone', 'address']);
        $exceptions->report(function (QueryException $exception) {
            Log::error('database_error', ['sqlstate' => (string) $exception->getCode()]);

            return false;
        });
    })->create();
