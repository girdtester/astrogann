<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserSession;
use App\Services\Push\FcmPushService;
use App\Services\Push\PushSettingsService;
use App\Services\SettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Super Admin → Settings → Push notifications (DECISIONS D-89): upload the Firebase key, check it, send a test push.
 */
class PushSettingsController extends Controller
{
    public function __construct(private readonly PushSettingsService $push) {}

    public function upload(Request $request): RedirectResponse
    {
        $request->validate(['firebase_key' => ['required', 'file', 'max:64', 'mimetypes:application/json,text/plain']], [
            'firebase_key.mimetypes' => 'Upload the .json file downloaded from Firebase.',
        ]);

        try {
            $info = $this->push->store($request->file('firebase_key'), $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Firebase key saved for project \"{$info['project_id']}\". Push notifications are ON. Use \"Check connection\" to confirm.");
    }

    public function toggle(Request $request, SettingsService $settings): RedirectResponse
    {
        $settings->update([
            'push_enabled' => $request->boolean('push_enabled') ? '1' : '0',
            'push_expired_users' => $request->boolean('push_expired_users') ? '1' : '0',
        ]);

        return back()->with('success', 'Push settings saved.');
    }

    public function check(): RedirectResponse
    {
        $driver = $this->push->driver();
        if (! $driver instanceof FcmPushService) {
            return back()->with('error', 'Push is not set up yet: upload the Firebase key first (and keep push switched on).');
        }

        try {
            $driver->checkConnection();
        } catch (Throwable $e) {
            return back()->with('error', 'Connection failed: '.$e->getMessage());
        }

        return back()->with('success', 'Connection OK — Google accepted the Firebase key. New entries will be pushed to users\' phones.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);
        $driver = $this->push->driver();
        if (! $driver instanceof FcmPushService) {
            return back()->with('error', 'Push is not set up yet: upload the Firebase key first.');
        }

        $user = User::where('email', strtolower($data['email']))->first();
        $token = $user ? UserSession::where('user_id', $user->id)->where('is_active', true)->whereNotNull('fcm_token')->value('fcm_token') : null;
        if (! $token) {
            return back()->withInput()->with('error', 'This user is not logged in on the app with notifications allowed (no device token yet). Ask them to open the app and log in.');
        }

        try {
            $invalid = $driver->send([$token], 'ASTRO GANN test', 'Push notifications are working on this phone.', ['type' => 'test']);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Sending failed: '.$e->getMessage());
        }

        if ($invalid !== []) {
            UserSession::where('fcm_token', $token)->update(['fcm_token' => null]);

            return back()->withInput()->with('error', 'Firebase says this phone\'s token is no longer valid (app removed or reinstalled). It was cleared; the user just needs to open the app again (it re-registers by itself).');
        }
        if ($driver->lastError !== null) {
            return back()->withInput()->with('error', 'Firebase did not accept the message: '.$driver->lastError);
        }

        return back()->with('success', "Test push sent to {$user->email}'s phone.");
    }

    public function remove(Request $request): RedirectResponse
    {
        $this->push->remove($request->user());

        return back()->with('success', 'Firebase key removed. Push notifications are OFF (users still see in-app notifications).');
    }
}
