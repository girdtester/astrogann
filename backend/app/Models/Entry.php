<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Entries are immutable (DECISIONS D-19): no updated_at, no edit/delete routes.
 */
class Entry extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = ['category_id', 'admin_id', 'image_path', 'entry_date', 'entry_time'];

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

    public function imageUrl(): string
    {
        return asset($this->image_path);
    }

    /** entry_time as HH:MM. */
    public function timeLabel(): string
    {
        return substr((string) $this->entry_time, 0, 5);
    }
}
