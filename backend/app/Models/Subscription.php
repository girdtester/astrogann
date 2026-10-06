<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'start_date', 'end_date', 'status', 'created_by', 'reminder_sent_at', 'notes'];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => SubscriptionStatus::class,
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * Active = status active AND end_date (inclusive) not passed. DECISIONS D-22.
     */
    public function isCurrentlyActive(): bool
    {
        return $this->status === SubscriptionStatus::Active
            && $this->end_date->endOfDay()->gte(now());
    }

    public function scopeCurrentlyActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active->value)
            ->where('end_date', '>=', today()->toDateString());
    }

    public function daysLeft(): int
    {
        return $this->isCurrentlyActive() ? (int) today()->diffInDays($this->end_date, false) : 0;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }
}
