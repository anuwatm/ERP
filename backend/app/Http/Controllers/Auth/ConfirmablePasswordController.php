<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ConfirmablePasswordController extends Controller
{
    /**
     * Show the confirm password view.
     */
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    /**
     * Confirm the user's password.
     */
    public function status(Request $request): JsonResponse
    {
        return response()->json(['confirmed' => time() - $request->session()->get('auth.password_confirmed_at', 0) <= config('auth.password_timeout', 10800)]);
    }

    public function store(Request $request): RedirectResponse|\Illuminate\Http\Response
    {
        if (! Auth::guard('web')->validate([
            'email' => $request->user()->email,
            'password' => $request->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        $request->session()->put('auth.password_confirmed_at', time());

        if ($request->expectsJson()) {
            return response()->noContent();
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
