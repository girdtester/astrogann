<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\RoleKind;
use App\Support\Permissions;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * role_id and status are set only by AccountService from validated Super Admin / staff forms.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id',
        'status',
        'phone',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $with = ['role'];

    /** @var list<string>|null effective permission keys, memoised per request */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'status' => ActiveStatus::class,
        ];
    }

    // ---- role & permissions (DECISIONS D-42) -------------------------------------------

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Extra permissions granted to this user on top of the role. */
    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function kind(): RoleKind
    {
        return $this->role->kind;
    }

    public function isSuperAdmin(): bool
    {
        return $this->kind() === RoleKind::SuperAdmin;
    }

    public function isStaff(): bool
    {
        return $this->kind() === RoleKind::Staff;
    }

    public function isTrader(): bool
    {
        return $this->kind() === RoleKind::User;
    }

    public function hasKind(RoleKind|string ...$kinds): bool
    {
        foreach ($kinds as $kind) {
            if ($this->kind() === ($kind instanceof RoleKind ? $kind : RoleKind::from($kind))) {
                return true;
            }
        }

        return false;
    }

    public function homeRoute(): string
    {
        return $this->kind()->homeRoute();
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->permissionCache !== null) {
            return $this->permissionCache;
        }

        if ($this->isSuperAdmin()) {
            return $this->permissionCache = Permissions::keys();
        }
        if ($this->isTrader()) {
            return $this->permissionCache = [];
        }

        $fromRole = $this->role->permissions()->pluck('key')->all();
        $direct = $this->directPermissions()->pluck('key')->all();

        return $this->permissionCache = array_values(array_unique([...$fromRole, ...$direct]));
    }

    public function hasPermission(string $key): bool
    {
        return in_array($key, $this->permissionKeys(), true);
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('role');
    }

    public function scopeOfKind(Builder $query, RoleKind ...$kinds): Builder
    {
        // whereIn on an indexed column scales better than whereHas (EXISTS) for lakhs of users (D-75).
        return $query->whereIn('users.role_id', Role::query()->ofKind(...$kinds)->select('id'));
    }

    // ---- status ---------------------------------------------------------------------------

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    // ---- relations ------------------------------------------------------------------------

    public function entries(): HasMany
    {
        return $this->hasMany(Entry::class, 'admin_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function userSessions(): HasMany
    {
        return $this->hasMany(UserSession::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(UserNotification::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }
}
