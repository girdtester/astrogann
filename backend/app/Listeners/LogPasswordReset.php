<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use App\Services\SessionService;
use Illuminate\Auth\Events\PasswordReset;

/**
 * After a "forgot password" reset: audit it and end every existing login (web + mobile), so only the new
 * password works anywhere (docs/SECURITY.md §5, DECISIONS D-84).
 */
class LogPasswordReset
{
    public function __construct(
        private readonly ActivityLogService $logs,
        private readonly SessionService $sessions,
    ) {}

    public function handle(PasswordReset $event): void
    {
        $this->sessions->endAll($event->user);
        $this->logs->log('auth.password_reset', null, 'Password reset by e-mail link; all sessions ended', $event->user);
    }
}
