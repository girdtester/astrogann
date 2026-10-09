<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PasswordResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/** D-98: mail check page and forgot password that never crashes on a mail-server error. */
class MailDiagnosticsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'abcdefghijklmnopqrstuvwxyz0123456789ABCD';

    public function test_mail_check_needs_the_secret_or_a_super_admin(): void
    {
        config(['astrogann.cron_token' => self::TOKEN, 'mail.default' => 'array']);

        $this->get('/diagnostics/mail/wrongtoken123')->assertNotFound();
        $this->get('/diagnostics/mail/'.self::TOKEN.'?to=owner@example.test')
            ->assertOk()->assertSee('CURRENT SETTINGS')->assertSee('TEST E-MAIL to owner@example.test')->assertSee('SENT in');

        $this->get(route('superadmin.diagnostics.mail'))->assertRedirect(route('login'));
        $this->signIn(User::factory()->superAdmin()->create());
        $this->get(route('superadmin.diagnostics.mail'))->assertOk()->assertSee('MAIL_MAILER');
    }

    public function test_forgot_password_shows_the_normal_message_when_the_mail_server_is_down(): void
    {
        $user = User::factory()->create(['email' => 'trader@example.test']);
        Password::shouldReceive('sendResetLink')->andThrow(new \RuntimeException('Connection could not be established with host "smtp.gmail.com:587"'));

        $this->post(route('password.email'), ['email' => 'trader@example.test'])
            ->assertSessionHasNoErrors()->assertSessionHas('status', PasswordResetService::NEUTRAL_MESSAGE);
        $this->assertDatabaseHas('activity_logs', ['action' => 'auth.password_reset_requested', 'user_id' => $user->id]);
    }
}
