<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Phase1FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_updates_last_login_at_and_audit_log(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotNull($user->refresh()->last_login_at);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('entity_id', $user->id)->exists());
    }

    public function test_valid_login_regenerates_session(): void
    {
        $user = User::factory()->create(['last_login_at' => null]);

        $this->withSession(['phase' => 'before-login']);
        $oldSessionId = session()->getId();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertNotSame($oldSessionId, session()->getId());
    }

    public function test_invalid_password_is_rate_limited(): void
    {
        $user = User::factory()->create();
        $key = Str::transliterate(Str::lower($user->email).'|127.0.0.1');
        RateLimiter::clear($key);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_password_reset_token_expires_and_cannot_be_reused(): void
    {
        config(['auth.passwords.users.expire' => 1]);

        $expiredUser = User::factory()->create();
        $expiredToken = Password::broker()->createToken($expiredUser);

        $this->travel(2)->minutes();
        $this->post('/reset-password', [
            'token' => $expiredToken,
            'email' => $expiredUser->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasErrors('email');
        $this->travelBack();

        $reuseUser = User::factory()->create();
        $token = Password::broker()->createToken($reuseUser);

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $reuseUser->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertRedirect(route('login', absolute: false));

        $this->post('/reset-password', [
            'token' => $token,
            'email' => $reuseUser->email,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertSessionHasErrors('email');
    }

    public function test_email_verification_link_expires_and_cannot_be_replayed(): void
    {
        $expiredUser = User::factory()->unverified()->create();
        $expiredUrl = URL::temporarySignedRoute('verification.verify', now()->subMinute(), [
            'id' => $expiredUser->id,
            'hash' => sha1($expiredUser->getEmailForVerification()),
        ]);

        $this->actingAsOrgUser($expiredUser)->get($expiredUrl)->assertForbidden();

        $user = User::factory()->unverified()->create();
        $url = URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);

        $this->actingAsOrgUser($user)->get($url)->assertRedirect();
        $this->assertNotNull($user->refresh()->email_verified_at);

        $this->actingAsOrgUser($user)->get($url)->assertForbidden();
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'password' => Hash::make('password'),
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_unverified_user_cannot_reach_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAsOrgUser($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice', absolute: false));
    }

    public function test_owner_permissions_are_seeded_during_registration(): void
    {
        $this->post('/register', [
            'organization_name' => 'Phase One Co., Ltd.',
            'name' => 'Owner User',
            'email' => 'owner@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $owner = User::where('email', 'owner@example.com')->firstOrFail();
        $ownerRole = Role::where('org_id', $owner->org_id)->where('code', 'owner')->firstOrFail();

        $this->assertTrue($owner->roles()->where('code', 'owner')->exists());
        $this->assertTrue($ownerRole->is_system);
        $this->assertGreaterThan(0, $ownerRole->permissions()->count());
    }

    public function test_shared_permissions_are_union_from_all_roles(): void
    {
        $user = User::factory()->create();

        $dashboard = Permission::create([
            'code' => 'dashboard.view',
            'module' => 'dashboard',
            'action' => 'view',
        ]);
        $viewUsers = Permission::create([
            'code' => 'users.view',
            'module' => 'users',
            'action' => 'view',
        ]);
        $viewAudit = Permission::create([
            'code' => 'audit.view',
            'module' => 'audit',
            'action' => 'view',
        ]);

        $admin = Role::create([
            'org_id' => $user->org_id,
            'code' => 'admin',
            'name' => 'Admin',
            'is_system' => true,
        ]);
        $viewer = Role::create([
            'org_id' => $user->org_id,
            'code' => 'viewer',
            'name' => 'Viewer',
            'is_system' => true,
        ]);

        $admin->permissions()->attach([$dashboard->id, $viewUsers->id]);
        $viewer->permissions()->attach($viewAudit->id);
        $user->roles()->attach([$admin->id, $viewer->id]);

        $this->actingAsOrgUser($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions', ['dashboard.view', 'users.view', 'audit.view'])
            );
    }

    public function test_inertia_shared_props_redact_sensitive_user_fields(): void
    {
        $user = User::factory()->create([
            'person_id' => '1234567890123',
        ]);
        $permission = Permission::create([
            'code' => 'dashboard.view',
            'module' => 'dashboard',
            'action' => 'view',
        ]);
        $role = Role::create([
            'org_id' => $user->org_id,
            'code' => 'viewer',
            'name' => 'Viewer',
            'is_system' => true,
        ]);
        $role->permissions()->attach($permission->id);
        $user->roles()->attach($role->id);

        $this->actingAsOrgUser($user)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('auth.user.id')
                ->has('auth.user.email')
                ->missing('auth.user.password')
                ->missing('auth.user.remember_token')
                ->missing('auth.user.person_id')
                ->has('auth.permissions')
                ->has('org.id')
                ->has('flash')
            );
    }
}
