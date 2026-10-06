<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Rows mirror App\Support\Permissions::catalog() (synced by PermissionService).
 */
class Permission extends Model
{
    protected $fillable = ['key', 'group', 'label', 'description', 'sort'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }
}
