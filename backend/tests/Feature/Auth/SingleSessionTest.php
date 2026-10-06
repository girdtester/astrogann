<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\UserSession;
use App\Services\SessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SingleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_web_login_replaces_the_previous_session(): void
    {
        $user = User::factory()->create();

        // First device logs in.
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $first = UserSession::where('user_id', $user->id)->sole();
        $firstSessionData = [SessionService::WEB_SESSION_KEY => $first->id];
        $this->post('/logout'); // clear the test client's state without touching the first row
        $first->update(['is_active' => true]);

        // Second device logs in → first row deactivated.
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertFalse($first->fresh()->is_active);
        $this->assertSame(1, UserSession::where('user_id', $user->id)->where('is_active', true)->count());

        // The first device's next request is rejected and logged out with a message.
        $this->flushSession();
        $this->actingAs($user)->withSession($firstSessionData)
            ->get('/user')
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn ($m) => str_contains($m, 'another device'));
        $this->assertGuest();
    }

    public function test_login_deletes_other_web_sessions_and_api_tokens(): void
    {
        config(['session.driver' => 'database']); // as in .env; phpunit.xml uses "array"
        $user = User::factory()->create();
        $user->createToken('old-phone');
        DB::table('sessions')->insert([
            'id' => 'old-browser-session', 'user_id' => $user->id, 'ip_address' => '1.1.1.1',
            'user_agent' => 'x', 'payload' => '', 'last_activity' => time(),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertDatabaseMissing('sessions', ['id' => 'old-browser-session']);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_request_without_session_marker_is_rejected_for_traders(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/user')->assertRedirect(route('login'));
    }

    public function test_staff_single_session_can_be_disabled_by_config(): void
    {
        config(['astrogann.single_session_staff' => false]);
        $admin = User::factory()->admin()->create();
        $existing = $admin->userSessions()->create(['device_type' => 'web', 'is_active' => true]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');

        $this->assertTrue($existing->fresh()->is_active);
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_staff_single_session_is_on_by_default(): void
    {
        $admin = User::factory()->admin()->create();
        $existing = $admin->userSessions()->create(['device_type' => 'web', 'is_active' => true]);

        $this->post('/login', ['email' => $admin->email, 'password' => 'password']);

        $this->assertFalse($existing->fresh()->is_active);
    }
}
