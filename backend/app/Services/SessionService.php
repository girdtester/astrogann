<?php

namespace App\Services;

use App\Enums\DeviceType;
use App\Models\User;
use App\Models\UserSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Single login session for web and API (spec 3.8, DECISIONS D-23).
 *
 * A new login deactivates every previous user_sessions row, revokes all Sanctum tokens and deletes
 * the other web sessions of that user, then records the new session. EnsureActiveSession rejects
 * any request that does not belong to the active session.
 */
class SessionService
{
    public const WEB_SESSION_KEY = 'user_session_id';

    public function __construct(private readonly ActivityLogService $logs) {}

    public function enforcesSingleSession(User $user): bool
    {
        return $user->isTrader() || config('astrogann.single_session_staff');
    }

    /** Web login: call after Auth::attempt() and session()->regenerate(). */
    public function startWeb(User $user, Request $request): UserSession
    {
        $this->endOtherSessions($user, $request->session()->getId());

        $session = $this->record($user, DeviceType::Web, $request);
        $request->session()->put(self::WEB_SESSION_KEY, $session->id);

        $this->logs->log('auth.login', $session, 'Web login', $user);

        return $session;
    }

    /**
     * API login. Returns the plain-text Sanctum token and the new session row.
     *
     * @return array{0: string, 1: UserSession}
     */
    public function startApi(User $user, Request $request, DeviceType $device, ?string $fcmToken = null): array
    {
        $this->endOtherSessions($user, null);

        $token = $user->createToken($device->value.'-'.now()->timestamp);
        $session = $this->record($user, $device, $request, $fcmToken, $token->accessToken->id);

        $this->logs->log('auth.login', $session, 'API login ('.$device->value.')', $user);

        return [$token->plainTextToken, $session];
    }

    /** Is the current request part of the active session (web session or Sanctum token)? */
    public function isActive(User $user, Request $request): bool
    {
        if (! $this->enforcesSingleSession($user)) {
            return true;
        }

        return $this->activeSessionFor($user, $request) !== null;
    }

    public function activeSessionFor(User $user, Request $request): ?UserSession
    {
        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            return UserSession::query()->where('user_id', $user->id)->where('token_id', $token->id)->active()->first();
        }

        $id = $request->hasSession() ? $request->session()->get(self::WEB_SESSION_KEY) : null;

        return $id ? UserSession::query()->whereKey($id)->where('user_id', $user->id)->active()->first() : null;
    }

    public function touch(UserSession $session): void
    {
        if (! $session->last_seen_at || $session->last_seen_at->lt(now()->subMinutes(5))) {
            $session->forceFill(['last_seen_at' => now()])->saveQuietly();
        }
    }

    public function updateFcmToken(User $user, Request $request, ?string $fcmToken): ?UserSession
    {
        $session = $this->activeSessionFor($user, $request);
        $session?->update(['fcm_token' => $fcmToken]);

        return $session;
    }

    /** Logout of the current web session / API token. */
    public function end(User $user, Request $request): void
    {
        $session = $this->activeSessionFor($user, $request);
        $session?->update(['is_active' => false, 'fcm_token' => null]);

        $token = $user->currentAccessToken();
        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        }

        $this->logs->log('auth.logout', $session, null, $user);
    }

    /** Ends every session of the user, e.g. when the account is deactivated. */
    public function endAll(User $user): void
    {
        $this->endOtherSessions($user, null, force: true);
    }

    private function endOtherSessions(User $user, ?string $keepWebSessionId, bool $force = false): void
    {
        if (! $force && ! $this->enforcesSingleSession($user)) {
            return;
        }

        $user->userSessions()->where('is_active', true)->update(['is_active' => false, 'fcm_token' => null]);
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table') ?: 'sessions')
                ->where('user_id', $user->id)
                ->when($keepWebSessionId, fn ($q) => $q->where('id', '!=', $keepWebSessionId))
                ->delete();
        }
    }

    private function record(User $user, DeviceType $device, Request $request, ?string $fcmToken = null, ?int $tokenId = null): UserSession
    {
        return $user->userSessions()->create([
            'device_type' => $device,
            'fcm_token' => $fcmToken,
            'is_active' => true,
            'token_id' => $tokenId,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 509),
            'last_seen_at' => now(),
        ]);
    }
}
