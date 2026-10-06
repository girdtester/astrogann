<?php

namespace App\Http\Controllers\Api;

use App\Enums\DeviceType;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureActiveSession;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ActivityLogService;
use App\Services\PasswordResetService;
use App\Services\SessionService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(
        private readonly SessionService $sessions,
        private readonly SubscriptionService $subscriptions,
        private readonly ActivityLogService $logs,
    ) {}

    /** Login. Single session: every previous web session and token of this user is ended (spec 3.8). */
    public function login(LoginRequest $request): JsonResponse
    {
        $key = 'api-login|'.Str::lower($request->string('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            return ApiResponse::error('Too many login attempts. Try again in '.RateLimiter::availableIn($key).' seconds.', 429, ['code' => 'too_many_requests']);
        }

        $user = User::query()->where('email', Str::lower($request->string('email')))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            RateLimiter::hit($key);
            $this->logs->log('auth.failed', null, 'Failed API login for '.$request->string('email'));

            return ApiResponse::error(trans('auth.failed'), 401, ['code' => 'invalid_credentials']);
        }

        if (! $user->isActive()) {
            RateLimiter::hit($key);

            return ApiResponse::error(EnsureActiveSession::MSG_INACTIVE, 403, ['code' => 'account_inactive']);
        }

        RateLimiter::clear($key);
        [$token, $session] = $this->sessions->startApi(
            $user, $request, DeviceType::from($request->input('device_type')), $request->input('fcm_token'),
        );

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'session_id' => $session->id,
            'user' => (new UserResource($user))->resolve($request),
            'subscription' => $user->isTrader() ? $this->subscriptions->summary($user) : null,
        ], 'Logged in. Any other session of this account has been logged out.');
    }

    /** POST /api/auth/register — self sign-up (D-85); logs straight in like /auth/login (single session). */
    public function register(RegisterRequest $request, AccountService $accounts): JsonResponse
    {
        $user = $accounts->register($request->validated());
        [$token, $session] = $this->sessions->startApi(
            $user, $request, DeviceType::from($request->input('device_type', 'mobile')), $request->input('fcm_token'),
        );

        return ApiResponse::success([
            'token' => $token,
            'token_type' => 'Bearer',
            'session_id' => $session->id,
            'user' => (new UserResource($user))->resolve($request),
            'subscription' => $this->subscriptions->summary($user),
        ], 'Welcome! Your account is ready.', 201);
    }

    /** POST /api/auth/forgot-password — e-mails a reset link (opens the website); neutral answer (D-84). */
    public function forgotPassword(Request $request, PasswordResetService $resets): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        return ApiResponse::success(null, $resets->sendLink($data['email']));
    }

    public function logout(Request $request): JsonResponse
    {
        $this->sessions->end($request->user(), $request);

        return ApiResponse::success(null, 'Logged out.');
    }

    /** Current account (handy for the app on start-up). */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success([
            'user' => (new UserResource($user))->resolve($request),
            'subscription' => $user->isTrader() ? $this->subscriptions->summary($user) : null,
        ]);
    }

    /** POST /api/update-fcm-token (image panel 5, DECISIONS D-09). */
    public function updateFcmToken(Request $request): JsonResponse
    {
        $data = $request->validate(['fcm_token' => ['nullable', 'string', 'max:512']]);
        $this->sessions->updateFcmToken($request->user(), $request, $data['fcm_token'] ?? null);

        return ApiResponse::success(null, 'FCM token saved.');
    }
}
