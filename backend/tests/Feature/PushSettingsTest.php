<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Push\FcmPushService;
use App\Services\Push\LogPushService;
use App\Services\Push\PushSettingsService;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * D-89: Firebase key upload, check connection, test push, switching push on/off — all from Super Admin.
 */
class PushSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $sa;

    protected function setUp(): void
    {
        parent::setUp();
        // Never touch a real uploaded key: tests use a temporary location.
        config(['astrogann.push.key_path' => sys_get_temp_dir().'/astrogann-test-firebase/service-account.json']);
        $this->seed(SettingsSeeder::class);
        $this->sa = User::factory()->superAdmin()->create();
        $this->signIn($this->sa);
        @unlink(app(PushSettingsService::class)->path());
    }

    protected function tearDown(): void
    {
        @unlink(app(PushSettingsService::class)->path());
        parent::tearDown();
    }

    private function keyFile(array $override = []): UploadedFile
    {
        $json = $override + [
            'type' => 'service_account',
            'project_id' => 'astrogann-test',
            'client_email' => 'push@astrogann-test.iam.gserviceaccount.com',
            'private_key' => file_get_contents(base_path('tests/Fixtures/fcm-test-private-key.pem')),
        ];

        return UploadedFile::fake()->createWithContent('astrogann-firebase.json', json_encode($json));
    }

    public function test_settings_page_shows_push_card_not_set_up(): void
    {
        $this->get(route('superadmin.settings.edit'))->assertOk()->assertSee('Push notifications (Firebase)')->assertSee('Not set up yet');
        $this->assertInstanceOf(LogPushService::class, app(PushSettingsService::class)->driver());
    }

    public function test_upload_valid_key_turns_push_on_and_uses_fcm(): void
    {
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile()])->assertSessionHas('success');

        $push = app(PushSettingsService::class);
        $this->assertTrue($push->enabled());
        $this->assertSame('astrogann-test', $push->info()['project_id']);
        $this->assertInstanceOf(FcmPushService::class, $push->driver());
        $this->assertStringNotContainsString(public_path(), $push->path()); // never under public/
        $this->get(route('superadmin.settings.edit'))->assertSee('astrogann-test')->assertSee('ON');
        $this->assertDatabaseHas('activity_logs', ['action' => 'push.configured']);
    }

    public function test_invalid_key_files_are_rejected(): void
    {
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile(['type' => 'authorized_user'])])
            ->assertSessionHas('error');
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile(['private_key' => 'not a key'])])
            ->assertSessionHas('error');
        $this->assertNull(app(PushSettingsService::class)->info());
    }

    public function test_check_connection_and_test_push(): void
    {
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile()]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.ok']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/astrogann-test/messages/1']),
        ]);

        $this->post(route('superadmin.settings.push.check'))->assertSessionHas('success');

        $trader = User::factory()->create(['email' => 'phone@example.com']);
        $this->post(route('superadmin.settings.push.test'), ['email' => 'phone@example.com'])->assertSessionHas('error'); // no device yet

        $trader->userSessions()->create(['device_type' => 'mobile', 'is_active' => true, 'fcm_token' => 'device-123']);
        $this->post(route('superadmin.settings.push.test'), ['email' => 'phone@example.com'])->assertSessionHas('success');
        Http::assertSent(fn ($r) => str_contains($r->url(), '/v1/projects/astrogann-test/messages:send') && $r['message']['token'] === 'device-123');
    }

    public function test_check_connection_reports_a_refused_key(): void
    {
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile()]);
        Http::fake(['oauth2.googleapis.com/*' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Invalid JWT Signature.'], 400)]);

        $this->post(route('superadmin.settings.push.check'))->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid JWT Signature'));
    }

    public function test_switch_off_and_remove(): void
    {
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile()]);

        $this->post(route('superadmin.settings.push.toggle'), ['push_enabled' => '0', 'push_expired_users' => '1']);
        $this->assertFalse(app(PushSettingsService::class)->enabled());

        $this->delete(route('superadmin.settings.push.remove'))->assertSessionHas('success');
        $this->assertNull(app(PushSettingsService::class)->info());
        $this->assertFileDoesNotExist(app(PushSettingsService::class)->path());
    }

    public function test_entry_image_is_sent_with_the_push(): void
    {
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile()]);
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'ya29.ok']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'x']),
        ]);

        app(PushSettingsService::class)->driver()->send(['tok'], 'Nifty 50', 'New entry', ['entry_id' => 5, 'image' => 'https://x/img.png']);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'messages:send')
            && $r['message']['notification']['image'] === 'https://x/img.png'
            && ! isset($r['message']['data']['image'])
            && $r['message']['data']['entry_id'] === '5');
    }

    public function test_only_settings_permission_can_manage_push(): void
    {
        $this->flushSession();
        $this->signIn(User::factory()->admin()->create());
        $this->post(route('superadmin.settings.push.upload'), ['firebase_key' => $this->keyFile()])->assertForbidden();
        $this->post(route('superadmin.settings.push.check'))->assertForbidden();
    }
}
