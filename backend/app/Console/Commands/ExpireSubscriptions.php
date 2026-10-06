<?php

namespace App\Console\Commands;

use App\Services\ActivityLogService;
use App\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Daily: mark active subscriptions whose end_date has passed as expired (spec 3.4).
 * (Access is already correct before this runs — checks also compare end_date — this keeps the data tidy.)
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'astrogann:expire-subscriptions';

    protected $description = 'Mark subscriptions past their end date as expired';

    public function handle(SubscriptionService $subscriptions, ActivityLogService $logs): int
    {
        $count = $subscriptions->expireOverdue();

        if ($count > 0) {
            $logs->log('subscription.auto_expired', null, "{$count} subscription(s) marked expired by the daily job");
        }
        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
