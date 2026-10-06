<?php

namespace App\Models;

use App\Enums\RoleKind;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A role is data (DECISIONS D-42). System roles (superadmin, admin, user) cannot be deleted
 * and keep their slug/kind; custom roles are always of kind "staff".
 */
class Role extends Model
{
    use HasFactory;

    public const SUPERADMIN = 'superadmin';

    public const ADMIN = 'admin';

    public const USER = 'user';

    protected $fillable = ['name', 'slug', 'kind', 'description', 'is_system'];

    protected function casts(): array
    {
        return [
            'kind' => RoleKind::class,
            'is_system' => 'boolean',
        ];
    }

    public static function findBySlug(string $slug): self
    {
        return static::query()->where('slug', $slug)->firstOrFail();
    }

    public function scopeOfKind(Builder $query, RoleKind ...$kinds): Builder
    {
        return $query->whereIn('kind', array_map(fn (RoleKind $k) => $k->value, $kinds));
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Super Admin always has every permission; its permission list is not editable. */
    public function hasEditablePermissions(): bool
    {
        return $this->kind === RoleKind::Staff;
    }
}
