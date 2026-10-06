<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;

/**
 * Development driver: writes pushes to the log instead of sending them.
 */
class LogPushService implements PushService
{
    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        if ($tokens !== []) {
            Log::info('[push:log] '.count($tokens).' device(s)', ['title' => $title, 'body' => $body, 'data' => $data]);
        }

        return [];
    }
}
