<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entries have no updated_at. Edit/delete need entries.edit / entries.delete (DECISIONS D-71).
 */
class Entry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['category_id', 'admin_id', 'image_path', 'thumb_path', 'entry_date', 'entry_time'];

    protected function casts(): array
    {
        return [
            'entry_date' => 'date',
        ];
    }

    /** Newest first by the entry's own date/time (DECISIONS D-26). */
    public function scopeLatestFirst(Builder $query): Builder
    {
        return $query->orderByDesc('entry_date')->orderByDesc('entry_time')->orderByDesc('id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    /** Full image (zoom). Absolute URL built from APP_URL / ASSET_URL — never hard-coded (D-90). */
    public function imageUrl(): string
    {
        return asset($this->image_path);
    }

    /** Small preview for lists and app cards (D-91); falls back to the full image for old entries. */
    public function thumbUrl(): string
    {
        return asset($this->thumb_path ?: $this->image_path);
    }

    /** entry_time as HH:MM. */
    public function timeLabel(): string
    {
        return substr((string) $this->entry_time, 0, 5);
    }
}
