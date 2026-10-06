<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value system settings with config fall-backs (DECISIONS D-25). Nothing user-visible is hard-coded.
 */
class SettingsService
{
    private const CACHE_KEY = 'astrogann.settings';

    /** @return array<string, string|null> */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(config('astrogann.settings_defaults'), $stored);
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default;
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /** Whitelisted keys served by GET /api/settings and shared with all web views. */
    public function public(): array
    {
        return array_intersect_key($this->all(), array_flip(config('astrogann.public_settings')));
    }

    /** @param array<string, string|null> $values */
    public function update(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Comma-separated setting as a clean list, e.g. two phone numbers.
     *
     * @return list<string>
     */
    public function list(string $key): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->get($key)))));
    }

    /**
     * Contact-admin details for "Show More" (web + API), one entry per number.
     *
     * @return array{text: ?string, phones: list<array{label: string, tel: string}>, whatsapp: list<array{label: string, url: string}>, email: ?string}
     */
    public function contact(): array
    {
        return [
            'text' => $this->get('show_more_text'),
            'phones' => array_map(fn ($p) => ['label' => $p, 'tel' => preg_replace('/[^\d+]/', '', $p)], $this->list('contact_phone')),
            'whatsapp' => array_map(fn ($w) => ['label' => $w, 'url' => 'https://wa.me/'.preg_replace('/\D+/', '', $w)], $this->list('contact_whatsapp')),
            'email' => $this->get('contact_email') ?: null,
        ];
    }

    public function entriesLimitActive(): int
    {
        return max(1, $this->int('entries_limit_active'));
    }

    public function entriesLimitExpired(): int
    {
        return max(0, $this->int('entries_limit_expired'));
    }

    public function reminderDays(): int
    {
        return max(1, $this->int('reminder_days'));
    }
}
