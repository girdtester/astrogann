<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Writes activity_logs rows ("View Reports / Logs"). DECISIONS D-24.
 */
class ActivityLogService
{
    public function log(string $action, ?Model $subject = null, ?string $description = null, ?User $user = null): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'user_id' => $user?->id ?? $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'description' => $description ? Str::limit($description, 497) : null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 509) : null,
        ]);
    }
}
