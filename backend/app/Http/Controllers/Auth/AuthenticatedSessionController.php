<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        if ($user->roles()->whereIn('code', ['owner', 'admin', 'finance'])->exists() && $user->two_factor_confirmed_at && ! $this->hasTrustedDevice($request, $user)) {
            $request->session()->put('two_factor_pending_user_id', $user->id);
            Auth::logout();
            return redirect()->route('two-factor.challenge');
        }
        $user->forceFill(['last_login_at' => now()])->save();

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'auth.login',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'after_json' => ['email' => $user->email],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $request->headers->get('X-Request-Id'),
        ]);

        if ($user->roles()->whereIn('code', ['owner', 'admin', 'finance'])->exists() && ! $user->two_factor_confirmed_at) {
            return redirect()->route('two-factor.setup');
        }
        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    private function hasTrustedDevice(Request $request, \App\Models\User $user): bool
    {
        $token = $request->cookie('erp_2fa_trusted');
        if (! $token) return false;
        $device = \App\Models\TwoFactorTrustedDevice::where('user_id', $user->id)->where('token_hash', hash('sha256', $token))->where('expires_at', '>', now())->first();
        if (! $device) return false;
        $device->update(['last_used_at' => now()]);
        return true;
    }
}
