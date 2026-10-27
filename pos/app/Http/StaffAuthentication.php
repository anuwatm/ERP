<?php

namespace Pos\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StaffAuthentication
{
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();
        abort_unless($user && $user->active, 401, 'Staff authentication required.');
        $now = time();
        if ($now - $request->session()->get('pos_last_seen', 0) > config('pos.idle_seconds') || $now - $request->session()->get('pos_login_at', 0) > config('pos.absolute_seconds')) {
            Auth::logout();
            $request->session()->invalidate();
            abort(401, 'Staff session expired.');
        }
        if (! ($request->isMethod('GET') && $request->is('state') && $request->header('X-POS-Passive') === '1')) {
            $request->session()->put('pos_last_seen', $now);
        }

        return $next($request);
    }
}
