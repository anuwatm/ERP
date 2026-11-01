<?php

namespace Pos\Http;

use Closure;
use Illuminate\Http\Request;

class ConfigurationAccess
{
    public function handle(Request $request, Closure $next)
    {
        app(ConfigurationController::class)->access($request);

        return $next($request);
    }
}
