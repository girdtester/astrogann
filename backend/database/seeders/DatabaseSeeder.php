<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([AccessSeeder::class, SettingsSeeder::class]);

        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
