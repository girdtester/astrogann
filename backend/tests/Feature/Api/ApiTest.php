<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Entry;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserSession;
use App\Support\Permissions as P;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use RefreshDatabase;

    private Category $nifty;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SettingsSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->nifty = Category::factory()->create(['name' => 'Nifty 50', 'sort_order' => 1]);
        for ($i = 0; $i < 10; $i++) {
            Entry::factory()->create(['category_id' => $this->nifty->id, 'admin_id' => $this->admin->id,
                'entry_date' => today()->subDays($i)->toDateString(), 'entry_time' => '09:15:00',
                'created_at' => today()->subDays($i)->setTime(9, 15)]);
        }
    }

    private function trader(bool $active = true): User
    {
        $u = User::factory()->create();
        $active ? Subscription::factory()->create(['user_id' => $u->id]) : Subscription::factory()->expired()->create(['user_id' => $u->id, 'end_date' => today()->subDays(2)->toDateString()]);

        return $u;
    }

    private function envelope(): array
    {
        return ['success', 'message', 'data', 'errors'];
    }

    // ---- auth -------------------------------------------------------------------------------

    public function test_login_returns_token_user_and_subscription(): void
    {
        $u = $this->trader();

        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile', 'fcm_token' => 'fcm-123'])
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'errors', 'data' => ['token', 'token_type', 'user' => ['id', 'role' => ['kind']], 'subscription' => ['status', 'end_date']]])
            ->assertJsonPath('data.user.role.kind', 'user')
            ->assertJsonPath('data.subscription.status', 'active');

        $this->assertDatabaseHas('user_sessions', ['user_id' => $u->id, 'device_type' => 'mobile', 'fcm_token' => 'fcm-123', 'is_active' => true]);
    }

    public function test_login_validation_and_bad_credentials_use_the_envelope(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'x'])->assertStatus(422)
            ->assertJsonStructure($this->envelope())
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['email', 'password', 'device_type']]);

        $u = User::factory()->create();
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'wrong', 'device_type' => 'mobile'])
            ->assertStatus(401)->assertJsonPath('errors.code', 'invalid_credentials');
    }

    public function test_inactive_account_cannot_login(): void
    {
        $u = User::factory()->inactive()->create();
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile'])
            ->assertStatus(403)->assertJsonPath('errors.code', 'account_inactive');
    }

    public function test_login_is_rate_limited(): void
    {
        $u = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'bad', 'device_type' => 'mobile']);
        }
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile'])
            ->assertStatus(429)->assertJsonPath('errors.code', 'too_many_requests');
    }

    public function test_new_login_replaces_previous_token_with_session_replaced_code(): void
    {
        $u = $this->trader();
        $first = $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile'])->json('data.token');
        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'web'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->withToken($first)->getJson('/api/categories')
            ->assertStatus(401)->assertJsonPath('errors.code', 'session_replaced');
        $this->assertSame(1, $u->userSessions()->where('is_active', true)->count());
    }

    public function test_api_login_kills_the_web_session_and_vice_versa(): void
    {
        $u = $this->trader();
        $this->post('/login', ['email' => $u->email, 'password' => 'password']);
        $webSession = UserSession::where('user_id', $u->id)->sole();

        $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile'])->assertOk();
        $this->assertFalse($webSession->fresh()->is_active);
        $this->assertSame('replaced', $webSession->fresh()->ended_reason);
    }

    public function test_deactivated_account_token_gets_account_inactive(): void
    {
        $u = $this->trader();
        $token = $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile'])->json('data.token');
        app(\App\Services\AccountService::class)->setStatus($u, \App\Enums\ActiveStatus::Inactive, User::factory()->superAdmin()->create());

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/categories')->assertStatus(403)->assertJsonPath('errors.code', 'account_inactive');
    }

    public function test_logout_revokes_the_token(): void
    {
        $u = $this->trader();
        $token = $this->postJson('/api/auth/login', ['email' => $u->email, 'password' => 'password', 'device_type' => 'mobile'])->json('data.token');

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk()->assertJsonPath('success', true);
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/categories')->assertStatus(401);
        $this->assertSame(0, $u->tokens()->count());
    }

    public function test_unauthenticated_requests_get_envelope_401(): void
    {
        foreach (['/api/categories', '/api/notifications', '/api/subscription', '/api/admin/users'] as $url) {
            $this->get($url)->assertStatus(401)->assertJsonPath('errors.code', 'unauthenticated');
        }
    }

    public function test_update_fcm_token(): void
    {
        $u = $this->trader();
        $this->apiSignIn($u)->postJson('/api/update-fcm-token', ['fcm_token' => 'new-token'])->assertOk();
        $this->assertDatabaseHas('user_sessions', ['user_id' => $u->id, 'fcm_token' => 'new-token']);
    }

    // ---- content ------------------------------------------------------------------------------

    public function test_settings_are_public(): void
    {
        $this->getJson('/api/settings')->assertOk()
            ->assertJsonPath('data.entries_limit_active', 7)
            ->assertJsonPath('data.entries_limit_expired', 3)
            ->assertJsonStructure(['data' => ['app_name', 'reminder_days', 'show_more_text', 'contact' => ['phone', 'whatsapp', 'email'], 'notice_text']]);
    }

    public function test_categories_are_active_only_in_order(): void
    {
        Category::factory()->create(['name' => 'First', 'sort_order' => 0]);
        Category::factory()->inactive()->create(['name' => 'Hidden']);

        $names = $this->apiSignIn($this->trader())->getJson('/api/categories')->assertOk()->json('data.*.name');
        $this->assertSame(['First', 'Nifty 50'], $names);
    }

    public function test_entries_seven_for_active_three_for_expired(): void
    {
        $res = $this->apiSignIn($this->trader(true))->getJson("/api/categories/{$this->nifty->id}/entries")->assertOk();
        $res->assertJsonCount(7, 'data.entries')->assertJsonPath('data.restricted', false)->assertJsonPath('data.show_more', null);
        $this->assertSame(today()->toDateString(), $res->json('data.entries.0.entry_date'));
        $this->assertSame('09:15', $res->json('data.entries.0.entry_time'));
        $this->assertStringStartsWith('http', $res->json('data.entries.0.image_url'));

        $this->app['auth']->forgetGuards();
        $this->apiSignIn($this->trader(false))->getJson("/api/categories/{$this->nifty->id}/entries")->assertOk()
            ->assertJsonCount(3, 'data.entries')
            ->assertJsonPath('data.restricted', true)
            ->assertJsonPath('data.show_more.email', 'ganndecoder@gmail.com')
            // D-106/D-107: expired 2 days ago → the 5th–7th of the latest 7 on that day (days 2–8), newest first.
            ->assertJsonPath('data.entries.*.entry_date', array_map(fn ($d) => today()->subDays($d)->toDateString(), [6, 7, 8]));
    }

    public function test_entries_of_inactive_or_missing_category_is_404(): void
    {
        $hidden = Category::factory()->inactive()->create();
        $this->apiSignIn($this->trader())->getJson("/api/categories/{$hidden->id}/entries")->assertNotFound()->assertJsonPath('errors.code', 'not_found');
        $this->getJson('/api/categories/99999/entries')->assertNotFound();
    }

    public function test_subscription_endpoint_is_for_users_only(): void
    {
        $this->apiSignIn($this->trader())->getJson('/api/subscription')->assertOk()->assertJsonPath('data.is_active', true);
        $this->app['auth']->forgetGuards();
        $this->apiSignIn($this->admin)->getJson('/api/subscription')->assertForbidden()->assertJsonPath('errors.code', 'forbidden');
    }

    public function test_notifications_with_unread_count_and_mark_read(): void
    {
        $u = $this->trader();
        $n = UserNotification::factory()->count(3)->create(['user_id' => $u->id, 'category_id' => $this->nifty->id]);
        UserNotification::factory()->create(); // someone else's

        $this->apiSignIn($u);
        $this->getJson('/api/notifications')->assertOk()
            ->assertJsonCount(3, 'data.items')->assertJsonPath('data.unread_count', 3)
            ->assertJsonPath('data.items.0.category_name', 'Nifty 50')
            ->assertJsonStructure(['data' => ['meta' => ['current_page', 'last_page', 'total']]]);

        $this->postJson("/api/notifications/{$n[0]->id}/read")->assertOk()->assertJsonPath('data.unread_count', 2);
        $this->postJson('/api/notifications/read-all')->assertOk();
        $this->assertSame(0, $u->appNotifications()->unread()->count());

        $other = UserNotification::where('user_id', '!=', $u->id)->first();
        $this->postJson("/api/notifications/{$other->id}/read")->assertNotFound();
    }

    // ---- admin entry ---------------------------------------------------------------------------

    public function test_admin_can_post_entry_and_trader_cannot(): void
    {
        Storage::fake('uploads');
        $payload = fn () => ['category_id' => $this->nifty->id, 'entry_date' => today()->toDateString(), 'entry_time' => '11:05',
            'image' => UploadedFile::fake()->image('a.jpg')];

        $this->apiSignIn($this->admin)->post('/api/entries', $payload())->assertCreated()
            ->assertJsonPath('data.entry_time', '11:05')->assertJsonPath('data.category_id', $this->nifty->id);

        $this->app['auth']->forgetGuards();
        $this->apiSignIn($this->trader())->post('/api/entries', $payload())->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->apiSignIn($this->admin)->post('/api/entries', ['category_id' => $this->nifty->id])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['entry_date', 'entry_time', 'image']]);
    }

    // ---- super admin --------------------------------------------------------------------------

    public function test_super_admin_creates_and_manages_users_and_admins(): void
    {
        $this->apiSignIn(User::factory()->superAdmin()->create());

        $id = $this->postJson('/api/admin/users', ['name' => 'T', 'email' => 't@x.test', 'password' => 'password1', 'password_confirmation' => 'password1'])
            ->assertCreated()->assertJsonPath('data.role.kind', 'user')->json('data.id');
        $this->patchJson("/api/admin/users/{$id}", ['status' => 'inactive'])->assertOk()->assertJsonPath('data.status', 'inactive');
        $this->getJson('/api/admin/users')->assertOk()->assertJsonPath('data.meta.total', 1);

        $adminId = $this->postJson('/api/admin/admins', ['name' => 'A', 'email' => 'a@x.test', 'password' => 'password1', 'password_confirmation' => 'password1', 'permissions' => [P::LOGS_VIEW]])
            ->assertCreated()->assertJsonPath('data.role.slug', 'admin')->json('data.id');
        $this->assertTrue(User::find($adminId)->hasPermission(P::LOGS_VIEW));

        // A user id on the admins endpoint is not found.
        $this->patchJson("/api/admin/admins/{$id}", ['name' => 'x'])->assertNotFound();
    }

    public function test_super_admin_subscription_actions(): void
    {
        $this->apiSignIn(User::factory()->superAdmin()->create());
        $u = User::factory()->create();

        $this->postJson('/api/admin/subscription', ['user_id' => $u->id, 'action' => 'activate', 'end_date' => today()->addDays(30)->toDateString(),
            'payment' => ['amount' => 999, 'transaction_ref' => 'X1']])
            ->assertOk()->assertJsonPath('data.status', 'active')->assertJsonPath('data.days_left', 30);
        $this->assertDatabaseHas('subscription_payments', ['user_id' => $u->id, 'transaction_ref' => 'X1']);

        $this->postJson('/api/admin/subscription', ['user_id' => $u->id, 'action' => 'extend', 'end_date' => today()->addDays(60)->toDateString()])
            ->assertOk()->assertJsonPath('data.end_date', today()->addDays(60)->toDateString());

        $this->postJson('/api/admin/subscription', ['user_id' => $u->id, 'action' => 'expire'])->assertOk()->assertJsonPath('data.is_active', false);

        $this->postJson('/api/admin/subscription', ['user_id' => $this->admin->id, 'action' => 'expire'])->assertStatus(422);
        $this->postJson('/api/admin/subscription', ['user_id' => $u->id, 'action' => 'delete'])->assertStatus(422);
    }

    public function test_admin_endpoints_are_forbidden_without_permission(): void
    {
        foreach ([$this->admin, $this->trader()] as $user) {
            $this->app['auth']->forgetGuards();
            $this->apiSignIn($user);
            $this->getJson('/api/admin/users')->assertForbidden();
            $this->postJson('/api/admin/users', [])->assertForbidden();
            $this->postJson('/api/admin/admins', [])->assertForbidden();
            $this->postJson('/api/admin/subscription', [])->assertForbidden();
        }
    }

    public function test_custom_staff_role_gets_matching_api_access(): void
    {
        $desk = User::factory()->staffWith(P::SUBSCRIPTIONS_MANAGE)->create();
        $u = User::factory()->create();

        $this->apiSignIn($desk);
        $this->postJson('/api/admin/subscription', ['user_id' => $u->id, 'action' => 'activate', 'end_date' => today()->addDays(5)->toDateString()])->assertOk();
        $this->getJson('/api/admin/users')->assertForbidden();
    }

    public function test_entry_edit_and_delete_via_api_respect_permissions(): void
    {
        Storage::fake('uploads');
        $entry = Entry::where('category_id', $this->nifty->id)->first();
        $edit = ['category_id' => $this->nifty->id, 'entry_date' => today()->toDateString(), 'entry_time' => '12:00'];

        $this->apiSignIn($this->admin)->postJson("/api/entries/{$entry->id}", $edit)->assertForbidden();
        $this->deleteJson("/api/entries/{$entry->id}")->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->apiSignIn(User::factory()->superAdmin()->create());
        $this->postJson("/api/entries/{$entry->id}", $edit)->assertOk()->assertJsonPath('data.entry_time', '12:00');
        $this->deleteJson("/api/entries/{$entry->id}")->assertOk();
        $this->assertNull($entry->fresh());
    }
}
