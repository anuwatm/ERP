<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TwoFactorTrustedDevice;
use App\Models\User;
use App\Services\TotpService;
use App\Services\TwoFactorPolicyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorController extends Controller
{
    public function setup(Request $request, TotpService $totp, TwoFactorPolicyService $policy): Response
    {
        abort_unless($policy->isEnabled($request->user()), 404);
        $secret = $totp->generateSecret();
        $request->session()->put('two_factor_setup_secret', $secret);

        return Inertia::render('Auth/TwoFactorSetup', ['secret' => $secret, 'otpauthUri' => $totp->uri('ERP', $request->user()->email, $secret)]);
    }

    public function confirmSetup(Request $request, TotpService $totp, TwoFactorPolicyService $policy): RedirectResponse
    {
        abort_unless($policy->isEnabled($request->user()), 404);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $secret = $request->session()->pull('two_factor_setup_secret');
        abort_unless($secret && $totp->verify($secret, $data['code']), 422, 'Invalid authenticator code.');
        $codes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(10)))->all();
        $user = $request->user();
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => array_map(fn ($code) => Hash::make($code), $codes), 'two_factor_confirmed_at' => now()])->save();
        $this->audit($request, $user, 'two_factor.enabled');

        return redirect()->route('profile.edit')->with('two_factor_recovery_codes', $codes);
    }

    public function challenge(): Response
    {
        abort_unless(session('two_factor_pending_user_id'), 404);

        return Inertia::render('Auth/TwoFactorChallenge');
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
        $response = redirect()->intended(route('dashboard', absolute: false));
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
