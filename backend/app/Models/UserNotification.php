<?php

namespace App\Models;

use App\Enums\NotificationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The spec's "notifications" table, named user_notifications so it does not clash with
 * Laravel's database notification channel (DECISIONS D-21).
 */
class UserNotification extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'category_id', 'entry_id', 'announcement_id', 'type', 'message', 'is_read'];

    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'is_read' => 'boolean',
        ];
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function announcement(): BelongsTo
    {
        return $this->belongsTo(Announcement::class);
    }

    /** Heading shown above the message (web, app, toast): announcement title, category name or a type label. */
    public function title(): string
    {
        return match ($this->type) {
            NotificationType::Announcement => $this->announcement?->title ?? 'Announcement',
            NotificationType::Expiry, NotificationType::Subscription => 'Subscription',
            default => $this->category?->name ?? 'Notification',
        };
    }
}
