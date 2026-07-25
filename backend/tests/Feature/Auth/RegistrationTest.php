<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Division;
use App\Models\NumberSequence;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'organization_name' => 'Acme ERP Co., Ltd.',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));

        $user = User::where('email', 'test@example.com')->firstOrFail();

        $this->assertSame('active', $user->status);
        $this->assertSame('local', $user->auth_provider);
        $this->assertSame($user->name, $user->display_name);
        $this->assertNotNull($user->org_id);
        $this->assertNotNull($user->branch_id);
        $this->assertNotNull($user->division_id);
        $this->assertNotNull($user->department_id);

        $this->assertSame(1, Organization::count());
        $this->assertSame(1, Branch::where('code', '000001')->where('is_head_office', true)->count());
        $this->assertSame(1, Division::where('code', '000001')->count());
        $this->assertSame(1, Department::where('code', '000001')->count());
        $this->assertSame(7, Role::count());
        $this->assertGreaterThan(0, Permission::count());
        $this->assertSame(3, NumberSequence::count());
        $this->assertTrue($user->roles()->where('code', 'owner')->exists());
        $this->assertTrue(AuditLog::where('action', 'auth.register')->where('entity_id', $user->id)->exists());
    }
}
