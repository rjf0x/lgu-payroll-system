<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['employee_id', 'username', 'email', 'password_hash', 'account_status', 'last_login_at'])]
#[Hidden(['password_hash', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $primaryKey = 'user_id';

    public const STATUS_ACTIVE = 'ACTIVE';

    public const STATUS_INACTIVE = 'INACTIVE';

    /**
     * Memoized permission names resolved for the current request.
     *
     * @var Collection<int, string>|null
     */
    protected ?Collection $resolvedPermissionNames = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'password_hash' => 'hashed',
        ];
    }

    /**
     * Authenticate against `password_hash` instead of Laravel's default `password` column.
     */
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    /**
     * Keep password rehashing pointed at the documented column name.
     */
    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function userRoles(): HasMany
    {
        return $this->hasMany(UserRole::class, 'user_id', 'user_id');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('role_name', $roleName)->exists();
    }

    /**
     * Permission names granted to the user through all assigned roles.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        if ($this->resolvedPermissionNames === null) {
            $this->resolvedPermissionNames = Permission::query()
                ->join('role_permissions', 'role_permissions.permission_id', '=', 'permissions.permission_id')
                ->join('user_roles', 'user_roles.role_id', '=', 'role_permissions.role_id')
                ->where('user_roles.user_id', $this->user_id)
                ->distinct()
                ->pluck('permissions.permission_name');
        }

        return $this->resolvedPermissionNames;
    }

    public function hasPermissionTo(string $permission): bool
    {
        return $this->permissionNames()->contains($permission);
    }

    public function isActive(): bool
    {
        return $this->account_status === self::STATUS_ACTIVE;
    }
}
