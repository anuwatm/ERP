<?php

namespace Pos\Http;

use Closure;
use Illuminate\Http\Request;
use Pos\Services\PublicMenuService;

class LocalPublicMenu
{
    public function handle(Request $request, Closure $next)
    {
        $menu = app(PublicMenuService::class);
        $menu->guard();
        if (! in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            abort_unless($menu->allowsPrivateLan($request->getSchemeAndHttpHost(), $request->ip() ?? ''), 403);
        }

        return $next($request)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
