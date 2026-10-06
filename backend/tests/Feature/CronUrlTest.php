<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * D-86: https://your-domain/cron/run/{CRON_TOKEN}
 */
class CronUrlTest extends TestCase
{
    private const TOKEN = 'a1b2c3d4e5f6a7b8c9d0a1b2c3d4e5f6a7b8c9d0';

    public function test_wrong_short_or_missing_token_is_a_404(): void
    {
        config(['astrogann.cron_token' => self::TOKEN]);
        $this->get('/cron/run/wrong123')->assertNotFound();

        config(['astrogann.cron_token' => 'short']); // too short = disabled
        $this->get('/cron/run/short')->assertNotFound();

        config(['astrogann.cron_token' => null]);
        $this->get('/cron/run/'.self::TOKEN)->assertNotFound();
    }

    public function test_correct_token_runs_the_scheduler(): void
    {
        config(['astrogann.cron_token' => self::TOKEN]);
        Artisan::shouldReceive('call')->once()->with('schedule:run')->andReturn(0);
        Artisan::shouldReceive('output')->once()->andReturn('No scheduled commands are ready to run.');

        $this->get('/cron/run/'.self::TOKEN)
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertSee('OK ')
            ->assertSee('No scheduled commands are ready to run.');
    }
}
