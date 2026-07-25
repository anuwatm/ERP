<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $permissions = [];

        if ($user) {
            $permissions = $request->attributes->get('auth.permissions');

            if ($permissions === null) {
                $permissions = $user->roles()
                    ->with('permissions:id,code')
                    ->get()
                    ->flatMap(fn ($role) => $role->permissions->pluck('code'))
                    ->unique()
                    ->values()
                    ->all();

                $request->attributes->set('auth.permissions', $permissions);
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'email_verified_at' => optional($user->email_verified_at)->toISOString(),
                ] : null,
                'permissions' => $permissions,
            ],
            'org' => $user?->organization ? [
                'id' => $user->organization->id,
                'name' => $user->organization->name,
                'currency' => $user->organization->currency,
                'timezone' => $user->organization->timezone,
            ] : null,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info'),
            ],
        ];
    }
}
