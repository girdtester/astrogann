<?php

namespace App\Jobs;

use App\Models\UserSession;
use App\Services\ActivityLogService;
use App\Services\Push\PushService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Sends a push to the active session (single session ⇒ at most one device) of each given user.
 * Tokens the provider reports as invalid are cleared.
 */
class SendPushToUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 50;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  list<int>  $userIds
     * @param  array<string, string>  $data
     */
    public function __construct(
        public readonly array $userIds,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}

    public function handle(PushService $push): void
    {
        $tokens = UserSession::query()
            ->whereIn('user_id', $this->userIds)
            ->where('is_active', true)
            ->whereNotNull('fcm_token')
            ->pluck('fcm_token')
            ->all();

        if ($tokens === []) {
            return; // nobody in this batch has the app with notifications allowed
        }

        $invalid = $push->send($tokens, $this->title, $this->body, $this->data);

        if ($invalid !== []) {
            UserSession::query()->whereIn('fcm_token', $invalid)->update(['fcm_token' => null]);
        }

        app(ActivityLogService::class)->log('push.sent', null, sprintf('"%s" → %d device(s)%s%s', $this->title, count($tokens),
            $invalid ? ', '.count($invalid).' old token(s) removed' : '', ($this->data['renew'] ?? null) ? ' [renew text]' : ''));
    }

    public function failed(Throwable $e): void
    {
        app(ActivityLogService::class)->log('job.failed', null, 'SendPushToUsers ('.count($this->userIds).' users): '.$e->getMessage());
    }
}
