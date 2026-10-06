<?php

namespace Tests\Feature;

use App\Mail\SubscriptionExpiringMail;
use App\Models\Category;
use App\Models\Subscription;
use App\Models\User;
use Database\Seeders\AccessSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Spec 3.3 end-to-end, step by step, through the real HTTP endpoints (web for staff, API for the trader).
 */
class EndToEndFlowTest extends TestCase
{
    use RefreshDatabase;

    /** Between web and API steps: drop the bearer header and the guard chosen by auth:sanctum. */
    private function resetAuth(): void
    {
        $this->flushHeaders();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    public function test_full_flow(): void
    {
        config(['queue.default' => 'sync']);
        Storage::fake('uploads');
        Mail::fake();
        $this->seed([AccessSeeder::class, SettingsSeeder::class]);
        $sa = User::factory()->superAdmin()->create(['email' => 'sa@x.test']);

        // 1. Super Admin creates Admin.
        $this->post('/login', ['email' => 'sa@x.test', 'password' => 'password'])->assertRedirect('/superadmin');
        $this->post(route('superadmin.admins.store'), ['name' => 'Admin', 'email' => 'admin@x.test', 'password' => 'password1', 'password_confirmation' => 'password1'])
            ->assertRedirect(route('superadmin.admins.index'));

        // 2. Super Admin manages categories.
        $this->post(route('superadmin.categories.store'), ['name' => 'Nifty 50', 'code' => 'NIFTY50', 'status' => 'active', 'sort_order' => 1])->assertRedirect();
        $this->post(route('superadmin.categories.store'), ['name' => 'NSE', 'code' => 'NSE', 'status' => 'active', 'sort_order' => 2])->assertRedirect();
        $nifty = Category::where('code', 'NIFTY50')->sole();

        // 3. Super Admin creates end user and activates a subscription ending in 3 days.
        $this->post(route('superadmin.users.store'), ['name' => 'Trader', 'email' => 'trader@x.test', 'password' => 'password1', 'password_confirmation' => 'password1'])->assertRedirect();
        $trader = User::where('email', 'trader@x.test')->sole();
        $this->post(route('superadmin.subscriptions.action', $trader), ['action' => 'activate', 'end_date' => today()->addDays(3)->toDateString()])->assertSessionHas('success');
        $this->post('/logout');

        // The trader logs in on the phone (API) — tabs come from the DB.
        $token = $this->postJson('/api/auth/login', ['email' => 'trader@x.test', 'password' => 'password1', 'device_type' => 'mobile', 'fcm_token' => 'phone-1'])
            ->assertOk()->json('data.token');
        $this->assertSame(['Nifty 50', 'NSE'], $this->withToken($token)->getJson('/api/categories')->json('data.*.name'));
        $this->resetAuth();

        // 4. Admin adds entries (category, date, time, image).
        $this->post('/login', ['email' => 'admin@x.test', 'password' => 'password1'])->assertRedirect('/admin');
        for ($i = 0; $i < 9; $i++) {
            $this->post(route('admin.entries.store'), [
                'category_id' => $nifty->id, 'entry_date' => today()->subDays($i)->toDateString(), 'entry_time' => '10:00',
                'image' => UploadedFile::fake()->image("e{$i}.png"),
            ])->assertRedirect();
        }
        $this->post('/logout');

        // 5. System notified the subscribed user (queue ran synchronously here).
        $this->assertSame(9, $trader->appNotifications()->where('type', 'new_entry')->count());

        // 6. User views the latest 7 entries (active subscription) and sees the notifications.
        $this->resetAuth();
        $this->withToken($token)->getJson("/api/categories/{$nifty->id}/entries")->assertJsonCount(7, 'data.entries')->assertJsonPath('data.restricted', false);
        $this->withToken($token)->getJson('/api/notifications')->assertJsonPath('data.unread_count', 9);

        // 7. Subscription ends in 3 days → reminder e-mail (once).
        $this->artisan('astrogann:send-expiry-reminders');
        $this->artisan('astrogann:send-expiry-reminders');
        Mail::assertQueued(SubscriptionExpiringMail::class, 1);

        // 8. Subscription expires → still logs in, sees 3 + Show More; no more notifications.
        $this->travelTo(today()->addDays(4)->setTime(1, 0));
        $this->artisan('astrogann:expire-subscriptions');
        $this->assertSame('expired', Subscription::where('user_id', $trader->id)->latest('id')->first()->status->value);
        $this->resetAuth();
        $this->withToken($token)->getJson("/api/categories/{$nifty->id}/entries")
            ->assertJsonCount(3, 'data.entries')->assertJsonPath('data.restricted', true)
            ->assertJsonPath('data.show_more.email', 'ganndecoder@gmail.com');

        // 9. Super Admin renews → full access (7) again.
        $this->resetAuth();
        $this->post('/login', ['email' => 'sa@x.test', 'password' => 'password']);
        $this->post(route('superadmin.subscriptions.action', $trader), ['action' => 'extend', 'end_date' => today()->addMonth()->toDateString()])->assertSessionHas('success');
        $this->resetAuth();
        $this->withToken($token)->getJson("/api/categories/{$nifty->id}/entries")->assertJsonCount(7, 'data.entries')->assertJsonPath('data.restricted', false);

        // Single session: logging in on the web kicks the phone.
        $this->resetAuth();
        $this->post('/login', ['email' => 'trader@x.test', 'password' => 'password1'])->assertRedirect('/user');
        $this->resetAuth();
        $this->withToken($token)->getJson('/api/categories')->assertStatus(401)->assertJsonPath('errors.code', 'session_replaced');
    }
}
