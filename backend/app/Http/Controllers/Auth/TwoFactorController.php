<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TotpService;
use App\Services\TwoFactorPolicyService;
use App\Support\DashboardLanding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TotpService $totp, TwoFactorPolicyService $policy): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($policy->isEnabled($request->user()), 404);
        abort_if($request->user()->two_factor_confirmed_at, 409, 'Two-factor authentication is already enabled.');
        if (! $request->session()->has('two_factor_setup_secret') || $request->session()->get('two_factor_setup_expires_at', 0) <= time()) {
            $request->session()->put(['two_factor_setup_secret' => $totp->generateSecret(), 'two_factor_setup_expires_at' => time() + 600]);
        }
        $secret = $request->session()->get('two_factor_setup_secret');
        Inertia::encryptHistory();

        $response = Inertia::render('Auth/TwoFactorSetup', ['secret' => $secret, 'otpauthUri' => $totp->uri('ERP', $request->user()->email, $secret)])->toResponse($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    public function confirmSetup(Request $request, TotpService $totp, TwoFactorPolicyService $policy): \Symfony\Component\HttpFoundation\Response
    {
        abort_unless($policy->isEnabled($request->user()), 404);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        abort_if($request->user()->two_factor_confirmed_at, 409, 'Two-factor authentication is already enabled.');
        $secret = $request->session()->get('two_factor_setup_secret');
        if ($request->session()->get('two_factor_setup_expires_at', 0) <= time()) {
            $request->session()->forget(['two_factor_setup_secret', 'two_factor_setup_expires_at']);
            throw ValidationException::withMessages(['code' => 'Setup expired. Open the setup page again.']);
        }
        if (! $secret || ! $totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid authenticator code.']);
        }
        $codes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
        $user = $request->user();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes), 'two_factor_confirmed_at' => now()])->save();
        $this->audit($request, $user, 'two_factor.enabled');
        $request->session()->forget(['two_factor_setup_secret', 'two_factor_setup_expires_at']);
        $request->session()->flash('two_factor_recovery_codes', $codes);

        return Inertia::location(route('two-factor.recovery'));
    }

    public function recoveryCodes(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $codes = $request->session()->pull('two_factor_recovery_codes');
        if (! $codes) {
            return redirect()->route('profile.edit');
        }

        return response()->view('auth.recovery-codes', ['codes' => $codes])->withHeaders(['Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache', 'Referrer-Policy' => 'no-referrer']);
    }

    public function challenge(Request $request, TwoFactorPolicyService $policy): Response
    {
        abort_unless(session('two_factor_pending_user_id'), 404);

        $user = User::findOrFail($request->session()->get('two_factor_pending_user_id'));

        return Inertia::render('Auth/TwoFactorChallenge', ['allowTrustedDevices' => $policy->allowsTrustedDevices($user), 'trustedDeviceDays' => $policy->trustedDeviceDays($user)]);
    }

    public function verifyChallenge(Request $request, TotpService $totp, TwoFactorPolicyService $policy): RedirectResponse
    {
        $id = $request->session()->get('two_factor_pending_user_id');
        $user = User::findOrFail($id);
        $key = 'two-factor:'.$user->id.'|'.$request->ip();
        abort_if(RateLimiter::tooManyAttempts($key, 5), 429, 'Too many authenticator attempts.');
        $code = (string) $request->input('code');
        $valid = $totp->verify((string) $user->two_factor_secret, $code) || $this->consumeRecoveryCode($user, $code);
        if (! $valid) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'Invalid authenticator or recovery code.']);
        }
        RateLimiter::clear($key);
        $request->session()->forget('two_factor_pending_user_id');
        Auth::login($user);
        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();
        $this->audit($request, $user, 'two_factor.challenge_passed');
        $response = redirect()->intended(DashboardLanding::routeFor($user));
        if ($request->boolean('trust_device') && $policy->allowsTrustedDevices($user)) {
            $days = $policy->trustedDeviceDays($user);
            $token = Str::random(80);
            TwoFactorTrustedDevice::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDays($days), 'user_agent_hash' => hash('sha256', (string) $request->userAgent())]);
            $response->withCookie(Cookie::make('erp_2fa_trusted', $token, 60 * 24 * $days, null, null, (bool) config('session.secure'), true, false, 'lax'));
        }

        return $response;
    }

    public function resetByOwner(Request $request, User $user): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor->org_id === $user->org_id && $actor->roles()->where('code', 'owner')->exists(), 403);
        $user->forceFill(['two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null])->save();
        TwoFactorTrustedDevice::where('user_id', $user->id)->delete();
        $this->audit($request, $user, 'two_factor.reset_by_owner');

        return back()->with('success', 'Two-factor authentication reset.');
    }

    private function consumeRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        foreach ($codes as $index => $hash) {
            if (Hash::check($code, $hash)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    private function audit(Request $request, User $user, string $action): void
    {
        AuditLog::create(['org_id' => $user->org_id, 'actor_user_id' => $user->id, 'action' => $action, 'entity_type' => 'user', 'entity_id' => $user->id, 'ip_address' => $request->ip(), 'user_agent' => $request->userAgent()]);
    }
}
