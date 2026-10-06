<?php

namespace Database\Seeders;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Category;
use App\Models\Entry;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\User;
use App\Support\PlaceholderImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * LOCAL DEMO DATA ONLY (docs/TEST_ACCOUNTS.md). Refuses to run in production.
 */
class DemoSeeder extends Seeder
{
    public const ENTRIES_PER_CATEGORY = 12;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder refused: APP_ENV=production.');

            return;
        }

        $superAdmin = User::factory()->superAdmin()->create([
            'name' => 'Super Admin', 'email' => 'superadmin@astrogann.test',
        ]);
        $admin = User::factory()->admin()->create([
            'name' => 'Data Admin', 'email' => 'admin@astrogann.test',
        ]);
        $active = User::factory()->trader()->create([
            'name' => 'Active Trader', 'email' => 'user.active@astrogann.test', 'phone' => '+91 90000 00001',
        ]);
        $expired = User::factory()->trader()->create([
            'name' => 'Expired Trader', 'email' => 'user.expired@astrogann.test', 'phone' => '+91 90000 00002',
        ]);

        $sub = Subscription::create([
            'user_id' => $active->id, 'start_date' => today()->subDays(5), 'end_date' => today()->addDays(30),
            'status' => SubscriptionStatus::Active, 'created_by' => $superAdmin->id, 'notes' => 'Demo monthly plan',
        ]);
        SubscriptionPayment::create([
            'user_id' => $active->id, 'subscription_id' => $sub->id, 'amount' => 1999, 'payment_date' => today()->subDays(5),
            'transaction_ref' => 'DEMO-0001', 'status' => PaymentStatus::Paid, 'recorded_by' => $superAdmin->id,
        ]);
        Subscription::create([
            'user_id' => $expired->id, 'start_date' => today()->subDays(40), 'end_date' => today()->subDays(10),
            'status' => SubscriptionStatus::Expired, 'created_by' => $superAdmin->id, 'notes' => 'Demo expired plan',
        ]);

        $categories = [
            ['Nifty 50', 'NIFTY50', 1], ['NSE', 'NSE', 2], ['Bank Nifty', 'BANKNIFTY', 3],
            ['Sensex', 'SENSEX', 4], ['Index', 'INDEX', 5],
        ];
        $dir = public_path('uploads/'.config('astrogann.entry_upload_dir'));

        foreach ($categories as $i => [$name, $code, $order]) {
            $category = Category::create(['name' => $name, 'code' => $code, 'status' => 'active', 'sort_order' => $order]);

            for ($n = 0; $n < self::ENTRIES_PER_CATEGORY; $n++) {
                $at = Carbon::today()->subDays($n)->setTime(9 + ($n % 6), 15);
                $file = 'seed-'.strtolower($code).'-'.($n + 1).'.png';
                PlaceholderImage::make($dir.DIRECTORY_SEPARATOR.$file, [$name, $at->format('d M Y'), $at->format('H:i'), '#'.($n + 1)], $i * 70);

                Entry::create([
                    'category_id' => $category->id, 'admin_id' => $admin->id,
                    'image_path' => 'uploads/'.config('astrogann.entry_upload_dir').'/'.$file,
                    'entry_date' => $at->toDateString(), 'entry_time' => $at->format('H:i:s'),
                ]);
            }
        }

        // One inactive category to prove tabs only show active ones.
        Category::create(['name' => 'Commodities', 'code' => 'COMMODITIES', 'status' => 'inactive', 'sort_order' => 6]);
    }
}
