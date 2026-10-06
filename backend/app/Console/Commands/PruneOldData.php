<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Models\UserNotification;
use App\Models\UserSession;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Daily housekeeping so tables stay fast with lakhs of users (DECISIONS D-75). Deletes in small batches so a run
 * never locks a table for long on shared hosting. Retention is configurable in config/astrogann.php (retention).
 */
class PruneOldData extends Command
{
    protected $signature = 'astrogann:prune';

    protected $description = 'Delete old activity logs, old read notifications and ended login sessions';

    public function handle(): int
    {
        $r = config('astrogann.retention');

        $logs = $this->deleteInBatches(ActivityLog::query()->where('created_at', '<', now()->subDays($r['activity_logs_days'])));
        $notes = $this->deleteInBatches(UserNotification::query()->where('is_read', true)
            ->where('created_at', '<', now()->subDays($r['read_notifications_days'])));
        $sessions = $this->deleteInBatches(UserSession::query()->where('is_active', false)
            ->where('updated_at', '<', now()->subDays($r['ended_sessions_days'])));

        $this->info("Pruned {$logs} log rows, {$notes} read notifications, {$sessions} ended sessions.");

        return self::SUCCESS;
    }

    private function deleteInBatches(Builder $query, int $batch = 2000): int
    {
        $total = 0;
        do {
            $ids = (clone $query)->limit($batch)->pluck('id');
            $deleted = $ids->isEmpty() ? 0 : $query->getModel()->newQuery()->whereKey($ids)->delete();
            $total += $deleted;
        } while ($deleted === $batch);

        return $total;
    }
}
