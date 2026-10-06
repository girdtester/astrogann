<?php

namespace App\Services\Push;

/**
 * Push notifications behind one interface (DECISIONS D-28). Driver from PUSH_DRIVER: "log" (default) or "fcm".
 */
interface PushService
{
    /**
     * Send one notification to many device tokens.
     *
     * @param  list<string>  $tokens
     * @param  array<string, string>  $data  extra key/value payload for the app (e.g. category_id)
     * @return list<string> tokens the provider reported as invalid (to be cleared)
     */
    public function send(array $tokens, string $title, string $body, array $data = []): array;
}
