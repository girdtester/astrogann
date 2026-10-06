<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Inserts missing default settings. Safe for production (never overwrites existing values).
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('astrogann.settings_defaults') as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
