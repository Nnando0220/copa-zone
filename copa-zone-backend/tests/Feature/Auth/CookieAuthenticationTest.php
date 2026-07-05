<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CookieAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanctum_csrf_cookie_endpoint_is_available(): void
    {
        $this->get('/sanctum/csrf-cookie')
            ->assertNoContent()
            ->assertCookie('XSRF-TOKEN');
    }

    public function test_user_can_register_and_is_authenticated_by_session(): void
    {
        $response = $this->postJsonWithCsrf('/api/v1/auth/register', [
            'name' => 'Fernando',
            'email' => 'fernando@example.com',
            'password' => 'senha123',
            'password_confirmation' => 'senha123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.user.email', 'fernando@example.com')
            ->assertJsonMissingPath('data.csrf_token');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'fernando@example.com']);
    }

    public function test_user_can_login_and_fetch_current_profile(): void
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('senha123'),
        ]);

        $this->postJsonWithCsrf('/api/v1/auth/login', [
            'email' => 'user@example.com',
            'password' => 'senha123',
        ])
            ->assertOk()
            ->assertJsonMissingPath('data.csrf_token');

        $this->assertAuthenticatedAs($user);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.user.email', 'user@example.com');
    }

    public function test_user_can_login_with_remember_cookie(): void
    {
        $user = User::factory()->create([
            'email' => 'remember@example.com',
            'password' => Hash::make('senha123'),
        ]);

        $response = $this->postJsonWithCsrf('/api/v1/auth/login', [
            'email' => 'remember@example.com',
            'password' => 'senha123',
            'remember' => true,
        ]);

        $response
            ->assertOk()
            ->assertCookie(Auth::getRecallerName());

        $this->assertAuthenticatedAs($user);
    }

    public function test_current_profile_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create([
            'email' => 'logout@example.com',
            'password' => Hash::make('senha123'),
        ]);

        $this->postJsonWithCsrf('/api/v1/auth/login', [
            'email' => 'logout@example.com',
            'password' => 'senha123',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);

        $this->postJsonWithCsrf('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('data', null);

        $this->refreshApplication();

        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }
}
