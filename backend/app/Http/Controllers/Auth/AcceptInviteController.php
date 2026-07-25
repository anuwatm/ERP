<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInviteController extends Controller
{
    public function edit(User $user, string $token): Response
    {
        abort_unless($this->validToken($user, $token), 404);

        return Inertia::render('Auth/AcceptInvite', [
            'invite' => [
                'user_id' => $user->id,
                'token' => $token,
                'email' => $user->email,
                'name' => $user->name,
            ],
        ]);
    }

    public function update(Request $request, User $user, string $token): RedirectResponse
    {
        abort_unless($this->validToken($user, $token), 404);

        $validated = $request->validate([
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->forceFill([
            'password' => $validated['password'],
            'status' => 'active',
            'email_verified_at' => now(),
            'invite_token_hash' => null,
            'invite_expires_at' => null,
            'invite_accepted_at' => now(),
        ])->save();

        AuditLog::create([
            'org_id' => $user->org_id,
            'actor_user_id' => $user->id,
            'action' => 'user.accept_invite',
            'entity_type' => 'user',
            'entity_id' => $user->id,
            'after_json' => ['email' => $user->email],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect(route('dashboard', absolute: false));
    }

    private function validToken(User $user, string $token): bool
    {
        return $user->status === 'invited'
            && $user->invite_token_hash
            && hash_equals($user->invite_token_hash, hash('sha256', $token))
            && $user->invite_expires_at
            && $user->invite_expires_at->isFuture();
    }
}
