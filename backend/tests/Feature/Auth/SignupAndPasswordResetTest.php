<?php

namespace Tests\Feature\Auth;

use App\Models\Category;
use App\Models\Entry;
use App\Models\User;
use App\Services\PasswordResetService;
use App\Services\SettingsService;
use Database\Seeders\AccessSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * D-84 forgot password (web + API, same service) and D-85 self sign-up (web + API).
 */
class SignupAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([AccessSeeder::class, SettingsSeeder::class]);
        $cat = Category::factory()->create(['name' => 'Nifty 50']);
        for ($i = 0; $i < 10; $i++) {
            Entry::factory()->create(['category_id' => $cat->id, 'entry_date' => today()->subDays($i)->toDateString()]);
        }
    }

    // ---- sign-up --------------------------------------------------------------------------

    public function test_web_signup_logs_in_as_unsubscribed_user_with_3_entries_and_show_more(): void
    {
        $this->get('/login')->assertSee('Create an account');
        $this->get('/register')->assertOk();

        $this->post('/register', [
            'name' => 'New Trader', 'email' => 'New@Example.com', 'phone' => '+91 98765 43210',
            'password' => 'password1', 'password_confirmation' => 'password1',
        ])->assertRedirect(route('user.dashboard'));

        $user = User::where('email', 'new@example.com')->sole();
        $this->assertTrue($user->isTrader());
        $this->assertSame(0, $user->subscriptions()->count());
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('activity_logs', ['action' => 'user.registered', 'user_id' => $user->id]);

        $this->get('/user')->assertOk()->assertSee('Show More')
            ->assertSee(today()->subDays(2)->format('d M Y'))
            ->assertDontSee(today()->subDays(3)->format('d M Y'));
    }

    public function test_api_signup_returns_token_and_no_subscription(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'name' => 'App User', 'email' => 'app@example.com', 'password' => 'password1', 'password_confirmation' => 'password1',
            'device_type' => 'mobile',
        ])->assertCreated()
            ->assertJsonPath('data.user.role.kind', 'user')
            ->assertJsonPath('data.subscription.status', 'none');

        $cat = Category::sole();
        $this->withToken($res->json('data.token'))->getJson("/api/categories/{$cat->id}/entries")
            ->assertJsonCount(3, 'data.entries')->assertJsonPath('data.restricted', true);
    }

    public function test_signup_validation_and_duplicate_email(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->postJson('/api/auth/register', ['name' => 'X', 'email' => 'taken@example.com', 'password' => 'password1', 'password_confirmation' => 'password1'])
            ->assertStatus(422)->assertJsonPath('errors.email.0', 'An account with this e-mail already exists. Please log in or use "Forgot password".');
        $this->postJson('/api/auth/register', ['name' => 'X', 'email' => 'x@example.com', 'password' => 'short', 'password_confirmation' => 'nope'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['password']]);
    }

    public function test_signup_can_be_switched_off_in_settings(): void
    {
        app(SettingsService::class)->update(['allow_registration' => '0']);

        $this->get('/login')->assertDontSee('Create an account');
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password1', 'password_confirmation' => 'password1'])->assertForbidden();
        $this->postJson('/api/auth/register', ['name' => 'x', 'email' => 'x@x.test', 'password' => 'password1', 'password_confirmation' => 'password1'])
            ->assertForbidden();
        $this->getJson('/api/settings')->assertJsonPath('data.allow_registration', false);
        $this->assertSame(0, User::where('email', 'x@x.test')->count());
    }

    // ---- forgot password -------------------------------------------------------------------

    public function test_forgot_password_gives_the_same_answer_and_only_mails_real_active_accounts(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'real@example.com']);
        $inactive = User::factory()->inactive()->create(['email' => 'off@example.com']);

        foreach (['real@example.com', 'nobody@example.com', 'off@example.com'] as $email) {
            $this->post('/forgot-password', ['email' => $email])->assertSessionHas('status', PasswordResetService::NEUTRAL_MESSAGE);
        }
        $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])->assertOk()
            ->assertJsonPath('message', PasswordResetService::NEUTRAL_MESSAGE);

        Notification::assertSentTo($user, ResetPassword::class);
        Notification::assertNotSentTo($inactive, ResetPassword::class);
    }

    public function test_reset_link_from_the_app_changes_password_everywhere_and_ends_old_sessions(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'trader@example.com']);
        $oldToken = $this->postJson('/api/auth/login', ['email' => 'trader@example.com', 'password' => 'password', 'device_type' => 'mobile'])->json('data.token');

        $this->postJson('/api/auth/forgot-password', ['email' => 'trader@example.com'])->assertOk();

        $resetToken = null;
        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$resetToken) {
            $resetToken = $n->token;

            return true;
        });

        // The e-mail link opens the website reset page.
        $this->app['auth']->forgetGuards();
        $this->get('/reset-password/'.$resetToken.'?email=trader@example.com')->assertOk();
        $this->post('/reset-password', [
            'token' => $resetToken, 'email' => 'trader@example.com', 'password' => 'new-password1', 'password_confirmation' => 'new-password1',
        ])->assertRedirect(route('login'));

        // Old phone session is gone; old password fails; new password works on API and web.
        $this->withToken($oldToken)->getJson('/api/categories')->assertStatus(403)->assertJsonPath('errors.code', 'account_inactive');
        $this->flushHeaders();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => 'trader@example.com', 'password' => 'password', 'device_type' => 'mobile'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => 'trader@example.com', 'password' => 'new-password1', 'device_type' => 'mobile'])->assertOk();
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_reset', 'user_id' => $user->id]);
    }
}
