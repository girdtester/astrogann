<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use App\Services\ActivityLogService;
use App\Services\SessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests from deactivated accounts and from sessions/tokens that are no longer the
 * user's single active session (spec 3.8, DECISIONS D-23). Web: logout + redirect to login.
 * API: 401/403 envelope with errors.code = session_replaced / account_inactive.
 */
class EnsureActiveSession
{
    public const MSG_REPLACED = 'You were logged out because your account was signed in on another device.';

    public const MSG_INACTIVE = 'Your account is inactive. Please contact the admin.';

    public function __construct(
        private readonly SessionService $sessions,
        private readonly ActivityLogService $logs,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if (! $user->isActive()) {
            return $this->reject($request, self::MSG_INACTIVE, 'account_inactive', 403);
        }

        $session = $this->sessions->activeSessionFor($user, $request);

        if ($session === null && $this->sessions->enforcesSingleSession($user)) {
            $this->logs->log('auth.session_replaced', null, 'Request from a replaced session rejected', $user);

            return $this->reject($request, self::MSG_REPLACED, 'session_replaced', 401);
        }

        if ($session) {
            $this->sessions->touch($session);
        }

        return $next($request);
    }

    private function reject(Request $request, string $message, string $code, int $status): Response
    {
        if ($request->is('api/*') || $request->expectsJson()) {
            $token = $request->user()?->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }

            return ApiResponse::error($message, $status, ['code' => $code]);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', $message);
    }
}
