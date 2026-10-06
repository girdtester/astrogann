<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'code', 'status', 'sort_order'];

    protected function casts(): array
    {
        return [
            'status' => ActiveStatus::class,
            'sort_order' => 'integer',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    /** Active categories in tab order. */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', ActiveStatus::Active->value);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class);
    }
}
