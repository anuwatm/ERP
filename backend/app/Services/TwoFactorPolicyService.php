<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;

class TwoFactorPolicyService
{
    public const SETTING_KEY = 'security.two_factor';

    private const DEFAULTS = [
        'enabled' => false,
        'required_for_privileged_roles' => true,
        'allow_trusted_devices' => true,
        'trusted_device_days' => 30,
    ];

    public function forOrg(string $orgId): array
    {
        $stored = Setting::where('org_id', $orgId)
            ->where('key', self::SETTING_KEY)
            ->value('value_json') ?? [];

        return array_replace(self::DEFAULTS, $stored);
    }

    public function isEnabled(User $user): bool
    {
        return (bool) $this->forOrg($user->org_id)['enabled'];
    }

    public function requiresEnrollment(User $user): bool
    {
        $policy = $this->forOrg($user->org_id);

        return (bool) $policy['enabled']
            && (bool) $policy['required_for_privileged_roles']
            && $user->roles()->whereIn('code', ['owner', 'admin', 'finance'])->exists();
    }

    public function shouldChallenge(User $user): bool
    {
        return $this->isEnabled($user) && $user->two_factor_confirmed_at !== null;
    }

    public function allowsTrustedDevices(User $user): bool
    {
        $policy = $this->forOrg($user->org_id);

        return (bool) $policy['enabled'] && (bool) $policy['allow_trusted_devices'];
    }

    public function trustedDeviceDays(User $user): int
    {
        return (int) $this->forOrg($user->org_id)['trusted_device_days'];
    }
}
