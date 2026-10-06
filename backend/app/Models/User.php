<?php

namespace App\Models;

use App\Enums\ActiveStatus;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
     * role and status are set only from validated Super Admin forms / seeders.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'status' => ActiveStatus::class,
        ];
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    public function isTrader(): bool
    {
        return $this->role === Role::User;
    }

    public function hasRole(Role|string ...$roles): bool
    {
        foreach ($roles as $role) {
            if ($this->role === ($role instanceof Role ? $role : Role::from($role))) {
                return true;
            }
        }

        return false;
    }

    public function isActive(): bool
    {
        return $this->status === ActiveStatus::Active;
    }

    public function scopeRole(Builder $query, Role $role): Builder
    {
        return $query->where('role', $role->value);
    }

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
