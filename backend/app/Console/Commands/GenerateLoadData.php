<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * LOCAL LOAD TESTING ONLY (DECISIONS D-75). Bulk-inserts many entries, traders, subscriptions, notifications and
 * log rows so list screens, filters, sorting and the API can be timed with lakhs of rows. Refuses in production.
 * Use it on a separate database, e.g. DB_DATABASE=astrogann_loadtest.
 */
class GenerateLoadData extends Command
{
    protected $signature = 'astrogann:generate-load {--entries=100000} {--users=20000} {--notifications=5}';

    protected $description = 'Insert bulk fake data for performance testing (local only)';

    public function handle(): int
    {
        if (app()->environment('production')) {
            $this->error('Refused: APP_ENV=production.');

            return self::FAILURE;
        }

        $categories = Category::query()->active()->pluck('id')->all();
        $admins = User::query()->whereIn('role_id', Role::query()->whereIn('kind', ['staff', 'superadmin'])->select('id'))->pluck('id')->all();
        $images = DB::table('entries')->distinct()->pluck('image_path')->all() ?: ['uploads/entries/placeholder.png'];
        if (! $categories || ! $admins) {
            $this->error('Seed first (php artisan db:seed) so categories and admins exist.');

            return self::FAILURE;
        }
        $traderRole = Role::findBySlug(Role::USER)->id;
        $now = now();
        $password = Hash::make('password');

        $entries = (int) $this->option('entries');
        $this->info("Inserting {$entries} entries…");
        $bar = $this->output->createProgressBar($entries);
        for ($done = 0; $done < $entries; $done += 2000) {
            $rows = [];
            for ($i = 0; $i < min(2000, $entries - $done); $i++) {
                $day = $now->copy()->subDays(random_int(0, 1500));
                $rows[] = [
                    'category_id' => $categories[array_rand($categories)],
                    'admin_id' => $admins[array_rand($admins)],
                    'image_path' => $images[array_rand($images)],
                    'entry_date' => $day->toDateString(),
                    'entry_time' => sprintf('%02d:%02d:00', random_int(9, 15), random_int(0, 59)),
                    'created_at' => $day,
                ];
            }
            DB::table('entries')->insert($rows);
            $bar->advance(count($rows));
        }
        $bar->finish();
        $this->newLine();

        $users = (int) $this->option('users');
        $this->info("Inserting {$users} traders with subscriptions, notifications and log rows…");
        $bar = $this->output->createProgressBar($users);
        $startId = (int) DB::table('users')->max('id');
        for ($done = 0; $done < $users; $done += 1000) {
            $n = min(1000, $users - $done);
            $rows = [];
            for ($i = 0; $i < $n; $i++) {
                $k = $startId + $done + $i + 1;
                $rows[] = ['name' => "Load Trader {$k}", 'email' => "load{$k}@astrogann.test", 'password' => $password,
                    'role_id' => $traderRole, 'status' => random_int(1, 20) === 1 ? 'inactive' : 'active',
                    'phone' => '+91 9'.str_pad((string) $k, 9, '0', STR_PAD_LEFT), 'created_at' => $now->copy()->subDays(random_int(0, 900)), 'updated_at' => $now];
            }
            DB::table('users')->insert($rows);
            $ids = DB::table('users')->where('id', '>', $startId + $done)->orderBy('id')->limit($n)->pluck('id');

            $subs = $notes = $logs = [];
            foreach ($ids as $id) {
                $end = $now->copy()->addDays(random_int(-200, 120));
                $subs[] = ['user_id' => $id, 'start_date' => $end->copy()->subDays(30)->toDateString(), 'end_date' => $end->toDateString(),
                    'status' => $end->isPast() ? 'expired' : 'active', 'created_at' => $now, 'updated_at' => $now];
                for ($j = 0; $j < (int) $this->option('notifications'); $j++) {
                    $notes[] = ['user_id' => $id, 'category_id' => $categories[array_rand($categories)], 'type' => 'new_entry',
                        'message' => 'New entry added. Check latest update now!', 'is_read' => (bool) random_int(0, 1), 'created_at' => $now->copy()->subMinutes(random_int(0, 200000))];
                }
                $logs[] = ['user_id' => $id, 'action' => 'auth.login', 'description' => 'Load test login', 'created_at' => $now->copy()->subMinutes(random_int(0, 500000))];
            }
            DB::table('subscriptions')->insert($subs);
            foreach (array_chunk($notes, 2000) as $chunk) {
                DB::table('user_notifications')->insert($chunk);
            }
            DB::table('activity_logs')->insert($logs);
            $bar->advance($n);
        }
        $bar->finish();
        $this->newLine();

        foreach (['entries', 'users', 'subscriptions', 'user_notifications', 'activity_logs'] as $t) {
            $this->line(str_pad($t, 20).number_format(DB::table($t)->count()));
        }

        return self::SUCCESS;
    }
}
