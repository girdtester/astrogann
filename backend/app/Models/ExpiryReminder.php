<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One saved "subscription expires in N days" reminder (D-88). status: pending → sent | failed.
 */
class ExpiryReminder extends Model
{
    public const PENDING = 'pending';

    public const SENT = 'sent';

    public const FAILED = 'failed';

    public const MAX_ATTEMPTS = 3;

    protected $fillable = ['subscription_id', 'user_id', 'end_date', 'days_left', 'status', 'attempts', 'last_error', 'sent_at', 'notified_at'];

    protected function casts(): array
    {
        return [
            'end_date' => 'date',
            'sent_at' => 'datetime',
            'notified_at' => 'datetime',
            'attempts' => 'integer',
            'days_left' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }
}
