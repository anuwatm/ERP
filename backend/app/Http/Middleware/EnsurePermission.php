<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        $hasPermission = $user->roles()
            ->whereHas('permissions', fn ($query) => $query->where('code', $permission))
            ->exists();

        abort_unless($hasPermission, 403);

        return $next($request);
    }
}
