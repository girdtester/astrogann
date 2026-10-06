<?php

namespace App\Models;

use App\Enums\DeviceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per login. At most one active row per user (single session, DECISIONS D-23).
 */
class UserSession extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'device_type', 'fcm_token', 'is_active', 'ended_reason', 'token_id', 'ip_address', 'user_agent', 'last_seen_at'];

    protected function casts(): array
    {
        return [
            'device_type' => DeviceType::class,
            'is_active' => 'boolean',
            'last_seen_at' => 'datetime',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
