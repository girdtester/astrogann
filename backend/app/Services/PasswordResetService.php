<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * "Forgot password" for web and API (DECISIONS D-84). The reset link opens the web page /reset-password/{token};
 * after a reset every existing login (web + mobile) is ended by the LogPasswordReset listener.
 *
 * Always answers with the same neutral message so nobody can find out which e-mails have accounts.
 */
class PasswordResetService
{
    public const NEUTRAL_MESSAGE = 'If an account exists for this e-mail, we have sent a password reset link. Please check your inbox (and spam folder).';

    public function __construct(private readonly ActivityLogService $logs) {}

    public function sendLink(string $email): string
    {
        $email = Str::lower(trim($email));
        $user = User::query()->where('email', $email)->first();

        if ($user && $user->isActive()) {
            $status = Password::sendResetLink(['email' => $email]);
            $this->logs->log('auth.password_reset_requested', $user, 'Reset link '.($status === Password::RESET_LINK_SENT ? 'sent' : 'not sent ('.$status.')'), $user);
        }

        return self::NEUTRAL_MESSAGE;
    }
}
