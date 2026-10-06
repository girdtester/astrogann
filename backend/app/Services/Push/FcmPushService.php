<?php

namespace App\Services\Push;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Firebase Cloud Messaging HTTP v1 (works from shared hosting: plain HTTPS, no SDK, no extra package).
 *
 * Auth: OAuth2 access token from the service-account JSON (FCM_CREDENTIALS), obtained with a JWT signed
 * by PHP's openssl_sign (RS256) and cached for 50 minutes. v1 has no multicast, so tokens are sent in
 * concurrent batches of 25 with Http::pool.
 */
class FcmPushService implements PushService
{
    private const SCOPE = 'https://www.googleapis.com/auth/firebase.messaging';

    private const BATCH = 25;

    public function __construct(
        private readonly string $projectId,
        private readonly string $credentialsPath,
    ) {}

    public function send(array $tokens, string $title, string $body, array $data = []): array
    {
        $tokens = array_values(array_unique(array_filter($tokens)));
        if ($tokens === []) {
            return [];
        }

        $accessToken = $this->accessToken();
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
        $data = array_map('strval', $data);
        $invalid = [];

        foreach (array_chunk($tokens, self::BATCH) as $batch) {
            $responses = Http::pool(fn (Pool $pool) => array_map(
                fn (string $token) => $pool->as($token)->withToken($accessToken)->timeout(10)->post($url, [
                    'message' => [
                        'token' => $token,
                        'notification' => ['title' => $title, 'body' => $body],
                        'data' => $data,
                        'android' => ['priority' => 'high'],
                        'apns' => ['payload' => ['aps' => ['sound' => 'default']]],
                    ],
                ]),
                $batch,
            ));

            foreach ($responses as $token => $response) {
                if (! $response instanceof Response) {
                    Log::warning('[push:fcm] transport error', ['error' => $response instanceof \Throwable ? $response->getMessage() : 'unknown']);

                    continue;
                }
                if ($response->successful()) {
                    continue;
                }
                $status = $response->json('error.status');
                if (in_array($status, ['UNREGISTERED', 'NOT_FOUND', 'INVALID_ARGUMENT'], true) || $response->status() === 404) {
                    $invalid[] = (string) $token;
                } else {
                    Log::warning('[push:fcm] send failed', ['status' => $response->status(), 'error' => $response->json('error.message')]);
                }
            }
        }

        return $invalid;
    }

    private function accessToken(): string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(50), function () {
            $sa = json_decode((string) @file_get_contents($this->credentialsPath), true);
            if (! is_array($sa) || empty($sa['private_key']) || empty($sa['client_email'])) {
                throw new RuntimeException('FCM_CREDENTIALS does not point to a valid Firebase service-account JSON file.');
            }

            $now = time();
            $segments = [
                $this->b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->b64(json_encode([
                    'iss' => $sa['client_email'], 'scope' => self::SCOPE, 'aud' => 'https://oauth2.googleapis.com/token',
                    'iat' => $now, 'exp' => $now + 3600,
                ])),
            ];
            if (! openssl_sign(implode('.', $segments), $signature, $sa['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('Could not sign the FCM JWT (check the private key).');
            }
            $segments[] = $this->b64($signature);

            $response = Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

            return $response->throw()->json('access_token');
        });
    }

    private function b64(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
