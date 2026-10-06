<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    public static function roles(): array
    {
        return [
            'superadmin' => ['superAdmin', '/superadmin', ['/admin', '/user']],
            'admin' => ['admin', '/admin', ['/superadmin', '/user']],
            'user' => ['trader', '/user', ['/superadmin', '/admin']],
        ];
    }

    #[DataProvider('roles')]
    public function test_login_redirects_to_role_dashboard(string $state, string $home, array $others): void
    {
        $user = User::factory()->{$state}()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect($home);
    }

    #[DataProvider('roles')]
    public function test_each_role_sees_only_its_own_area(string $state, string $home, array $others): void
    {
        $user = User::factory()->{$state}()->create();
        $this->signIn($user);

        $this->get($home)->assertOk();
        foreach ($others as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login_from_every_area(): void
    {
        foreach (['/superadmin', '/admin', '/user'] as $path) {
            $this->get($path)->assertRedirect(route('login'));
        }
    }

    public function test_seed_accounts_reach_their_dashboards(): void
    {
        $this->seed(\Database\Seeders\SettingsSeeder::class);
        $accounts = [
            ['superadmin@astrogann.test', 'superAdmin', '/superadmin'],
            ['admin@astrogann.test', 'admin', '/admin'],
            ['user.active@astrogann.test', 'trader', '/user'],
            ['user.expired@astrogann.test', 'trader', '/user'],
        ];

        foreach ($accounts as [$email, $state, $home]) {
            User::factory()->{$state}()->create(['email' => $email]);
            $this->post('/login', ['email' => $email, 'password' => 'password'])->assertRedirect($home);
            $this->get($home)->assertOk();
            $this->post('/logout');
        }
    }

    public function test_inactive_users_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_user_deactivated_while_logged_in_is_logged_out(): void
    {
        $user = User::factory()->create();
        $this->signIn($user);
        $user->update(['status' => 'inactive']);

        $this->get('/user')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_failures(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
