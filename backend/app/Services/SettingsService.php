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
