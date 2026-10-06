<?php

namespace Tests;

use App\Enums\DeviceType;
use App\Models\User;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Act as $user on the web guard *with* an active single-session row, the way a real login would.
     * (Plain actingAs() is rejected by EnsureActiveSession.)
     */
    protected function signIn(User $user): static
    {
        $session = $user->userSessions()->create(['device_type' => DeviceType::Web, 'is_active' => true]);

        return $this->actingAs($user)->withSession([SessionService::WEB_SESSION_KEY => $session->id]);
    }

    /** Issue an API token the way POST /api/auth/login does and use it for the next requests. */
    protected function apiSignIn(User $user, DeviceType $device = DeviceType::Mobile): static
    {
        $token = $user->createToken('test');
        $user->userSessions()->create(['device_type' => $device, 'is_active' => true, 'token_id' => $token->accessToken->id]);

        return $this->withToken($token->plainTextToken);
    }
}
