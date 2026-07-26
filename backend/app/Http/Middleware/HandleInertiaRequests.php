<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
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

        $org = $user?->organization ?? (Schema::hasTable('organizations') ? Organization::first() : null);

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
            'org' => $org ? [
                'id' => $org->id,
                'name' => $org->name,
                'legal_name' => $org->legal_name,
                'logo_url' => Organization::formatLogoUrl($org->logo_url),
                'currency' => $org->currency,
                'timezone' => $org->timezone,
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
