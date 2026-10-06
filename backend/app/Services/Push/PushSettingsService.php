<?php

namespace App\Services\Push;

use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\SettingsService;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;

/**
 * Firebase push set-up from the Super Admin screen (DECISIONS D-89) — no SSH / .env editing needed on shared hosting.
 *
 * The service-account JSON is stored privately in storage/app/private/firebase/ (never under public/). Push is on when
 * a valid file is present and the "push_enabled" setting is on. .env (PUSH_DRIVER=fcm, FCM_*) still works as fallback.
 */
class PushSettingsService
{
    public function __construct(
        private readonly SettingsService $settings,
        private readonly ActivityLogService $logs,
    ) {}

    public function path(): string
    {
        return config('astrogann.push.key_path') ?: storage_path('app/private/firebase/service-account.json');
    }

    /** @return array{project_id: string, client_email: string}|null */
    public function info(): ?array
    {
        if (! is_file($this->path())) {
            return null;
        }
        $json = json_decode((string) file_get_contents($this->path()), true);

        return is_array($json) && ! empty($json['project_id']) && ! empty($json['client_email'])
            ? ['project_id' => $json['project_id'], 'client_email' => $json['client_email']]
            : null;
    }

    public function enabled(): bool
    {
        return $this->info() !== null && $this->settings->bool('push_enabled');
    }

    /** The PushService the app should use right now. */
    public function driver(): PushService
    {
        if ($this->enabled()) {
            return new FcmPushService($this->info()['project_id'], $this->path());
        }

        $cfg = config('astrogann.push');
        if ($cfg['driver'] === 'fcm' && $cfg['fcm_project_id'] && $cfg['fcm_credentials']) {
            return new FcmPushService($cfg['fcm_project_id'], $cfg['fcm_credentials']);
        }

        return new LogPushService;
    }

    /** Validate and store the uploaded Firebase service-account file, then switch push on. */
    public function store(UploadedFile $file, User $by): array
    {
        $json = json_decode((string) file_get_contents($file->getRealPath()), true);
        if (! is_array($json) || ($json['type'] ?? null) !== 'service_account'
            || empty($json['project_id']) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new InvalidArgumentException('This is not a Firebase service-account key. In Firebase console open Project settings → Service accounts → "Generate new private key" and upload that .json file.');
        }
        if (! openssl_pkey_get_private($json['private_key'])) {
            throw new InvalidArgumentException('The private key inside this file is not valid.');
        }

        if (! is_dir(dirname($this->path()))) {
            mkdir(dirname($this->path()), 0700, true);
        }
        file_put_contents($this->path(), json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        @chmod($this->path(), 0600);

        $this->settings->update(['push_enabled' => '1']);
        $this->logs->log('push.configured', null, "Firebase key uploaded for project {$json['project_id']} ({$json['client_email']})", $by);

        return ['project_id' => $json['project_id'], 'client_email' => $json['client_email']];
    }

    public function remove(User $by): void
    {
        if (is_file($this->path())) {
            unlink($this->path());
        }
        $this->settings->update(['push_enabled' => '0']);
        $this->logs->log('push.removed', null, 'Firebase key removed; push notifications off', $by);
    }
}
