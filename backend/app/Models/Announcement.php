<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Manual notification from the Super Admin to users (D-100). audience: all | active | inactive.
 */
class Announcement extends Model
{
    public const AUDIENCES = [
        'all' => 'All users',
        'active' => 'Only users with an active subscription',
        'inactive' => 'Only users without an active subscription',
    ];

    protected $fillable = ['title', 'message', 'audience', 'recipients', 'created_by'];

    protected function casts(): array
    {
        return ['recipients' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function audienceLabel(): string
    {
        return self::AUDIENCES[$this->audience] ?? $this->audience;
    }
}
