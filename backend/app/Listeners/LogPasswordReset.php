<?php

namespace App\Listeners;

use App\Services\ActivityLogService;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Audit trail for "forgot password" resets (docs/SECURITY.md §5).
 */
class LogPasswordReset
{
    public function __construct(private readonly ActivityLogService $logs) {}

    public function handle(PasswordReset $event): void
    {
        $this->logs->log('auth.password_reset', null, 'Password reset by e-mail link', $event->user);
    }
}
