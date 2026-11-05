<?php

namespace Pos\Http;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Pos\Services\PatientHistoryScope;

final class PatientHistoryAccess
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(in_array($request->ip(), ['127.0.0.1', '::1'], true), 403);
        $scope = DB::transaction(fn () => app(PatientHistoryScope::class)->lock($request->user()));
        abort_unless(in_array($scope['staff']->role, ['cashier', 'manager', 'pharmacist'], true), 403);
        if (! ($request->isMethod('GET') && $request->is('patients'))) {
            abort_unless($request->expectsJson(), 406, 'JSON requests required.');
        }
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        $identity = hash('sha256', json_encode([config('pos.instance_id'), $scope['branch']->id, $scope['staff']->id, $scope['staff']->role, $scope['staff']->pharmacist_qualified], JSON_THROW_ON_ERROR));
        $response->headers->set('X-POS-Patient-Scope', $identity);
        $remaining = max(0, min(config('pos.idle_seconds'), $request->session()->get('pos_login_at', 0) + config('pos.absolute_seconds') - time()));

        return $response->header('Cache-Control', 'private, no-store')->header('Pragma', 'no-cache')->header('X-Content-Type-Options', 'nosniff')->header('X-POS-Session-Remaining', (string) $remaining)->header('Vary', 'Cookie');
    }
}
