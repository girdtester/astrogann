<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $this->get('/login')->assertStatus(200)->assertDontSee('Register')->assertDontSee('Remember me');
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('user.dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.failed']);
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->signIn($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect(route('login'));
        $this->assertDatabaseHas('user_sessions', ['user_id' => $user->id, 'is_active' => false]);
    }

    public function test_registration_is_closed_when_the_setting_is_off(): void
    {
        // Sign-up is allowed by default since D-85; the Super Admin can switch it off.
        app(\App\Services\SettingsService::class)->update(['allow_registration' => '0']);

        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password'])->assertForbidden();
    }
}
