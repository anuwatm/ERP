<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorPolicyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorEnrollment
{
    private const ALLOWED_ROUTES = [
        'two-factor.setup',
        'two-factor.setup.confirm',
        'two-factor.reset',
        'password.confirm',
        'password.update',
        'logout',
        'verification.notice',
        'verification.verify',
        'verification.send',
    ];

    public function __construct(private readonly TwoFactorPolicyService $policy) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $this->policy->requiresEnrollment($user) && ! $user->two_factor_confirmed_at && ! $request->routeIs(self::ALLOWED_ROUTES)) {
            return redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
