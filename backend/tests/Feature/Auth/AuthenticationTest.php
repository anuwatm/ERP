<?php

namespace Tests\Feature\Auth;

use App\Models\Organization;
use App\Models\User;
use App\Support\DashboardLanding;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_guest_page_is_private_and_does_not_share_another_organizations_details(): void
    {
        Organization::create(['name' => 'Private test tenant', 'legal_name' => 'Confidential legal name']);

        $this->get('/login')
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Login')->where('org', null));
    }

    public function test_query_exception_reporting_excludes_sql_and_bindings(): void
    {
        Log::spy();

        app(ExceptionHandler::class)->report(new QueryException(
            'sqlite',
            'select * from users where email = ?',
            ['private@example.test'],
            new \PDOException('database failure with private detail', 19),
        ));

        Log::shouldHaveReceived('error')->once()->with('database_error', ['sqlstate' => '19']);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(DashboardLanding::routeFor($user));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
