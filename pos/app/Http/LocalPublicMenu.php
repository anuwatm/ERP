<?php

namespace Pos\Http;

use Closure;
use Illuminate\Http\Request;
use Pos\Services\PublicMenuService;

class LocalPublicMenu
{
    public function handle(Request $request, Closure $next)
    {
        app(PublicMenuService::class)->guard();
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403, 'Loopback menu development only.');

        return $next($request);
    }
}
