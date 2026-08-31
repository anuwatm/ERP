<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\TwoFactorTrustedDevice;
use App\Services\TotpService;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

class Phase18TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_privileged_user_must_pass_totp_after_password_login(): void
    {
        $user = User::factory()->create(['password' => 'password']);
        $this->financeRole($user);
        $secret = 'JBSWY3DPEHPK3PXP';
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_recovery_codes' => [Hash::make('RECOVERY01')], 'two_factor_confirmed_at' => now()])->save();

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])->assertRedirect(route('two-factor.challenge'));
        $this->assertGuest();
        $this->post(route('two-factor.challenge.verify'), ['code' => $this->currentCode($secret)])->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
    }

    public function test_recovery_code_is_single_use_and_invalid_codes_are_rejected(): void
    {
        $user = User::factory()->create();
        $this->financeRole($user);
        $user->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_recovery_codes' => [Hash::make('RECOVERY01')], 'two_factor_confirmed_at' => now()])->save();
        session(['two_factor_pending_user_id' => $user->id]);
        $this->post(route('two-factor.challenge.verify'), ['code' => 'INVALID01'])->assertSessionHasErrors('code');
        $this->post(route('two-factor.challenge.verify'), ['code' => 'RECOVERY01'])->assertRedirect(route('dashboard', absolute: false));
        $this->assertSame([], $user->fresh()->two_factor_recovery_codes);
    }

    public function test_trusted_device_is_accepted_only_before_expiry(): void
    {
        $user = User::factory()->create();
        $token = 'trusted-device-token';
        TwoFactorTrustedDevice::create(['user_id' => $user->id, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addDay()]);
        $method = new ReflectionMethod(AuthenticatedSessionController::class, 'hasTrustedDevice');
        $request = Request::create('/'); $request->cookies->set('erp_2fa_trusted', $token);
        $this->assertTrue($method->invoke(new AuthenticatedSessionController(), $request, $user));
        TwoFactorTrustedDevice::where('user_id', $user->id)->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($method->invoke(new AuthenticatedSessionController(), $request, $user));
    }

    public function test_owner_can_reset_two_factor_for_same_organization_user(): void
    {
        $owner = User::factory()->create(); $target = User::factory()->create(['org_id' => $owner->org_id, 'branch_id' => $owner->branch_id, 'division_id' => $owner->division_id, 'department_id' => $owner->department_id]);
        $role = Role::firstOrCreate(['org_id' => $owner->org_id, 'code' => 'owner'], ['name' => 'Owner', 'is_system' => true]); $owner->roles()->syncWithoutDetaching([$role->id]);
        $target->forceFill(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_recovery_codes' => [Hash::make('RECOVERY01')], 'two_factor_confirmed_at' => now()])->save();
        TwoFactorTrustedDevice::create(['user_id' => $target->id, 'token_hash' => hash('sha256', 'target-token'), 'expires_at' => now()->addDay()]);
        $this->actingAsOrgUser($owner)->withSession(['auth.password_confirmed_at' => time()])->post(route('two-factor.reset', $target))->assertRedirect();
        $target->refresh(); $this->assertNull($target->two_factor_secret); $this->assertNull($target->two_factor_confirmed_at); $this->assertSame(0, TwoFactorTrustedDevice::where('user_id', $target->id)->count());
    }

    private function financeRole(User $user): void
    {
        $role = Role::firstOrCreate(['org_id' => $user->org_id, 'code' => 'finance'], ['name' => 'Finance', 'is_system' => true]);
        $permission = Permission::firstOrCreate(['code' => 'dashboard.view'], ['module' => 'dashboard', 'action' => 'view']);
        $role->permissions()->syncWithoutDetaching([$permission->id]);
        $user->roles()->syncWithoutDetaching([$role->id]);
    }

    private function currentCode(string $secret): string
    {
        $method = new ReflectionMethod(TotpService::class, 'code');
        return $method->invoke(new TotpService(), $secret, intdiv(time(), 30));
    }
}
