<?php

namespace App\Services;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class AccountAccessService
{
    /** @return Collection<int, User> */
    public function lockAdministrators(): Collection
    {
        return User::where('account_status', User::STATUS_ACTIVE)
            ->whereHas('roles.permissions', fn ($query) => $query->where('permission_name', 'users.manage'))
            ->orderBy('user_id')->lockForUpdate()->get();
    }

    /** @param Collection<int, User> $administrators
     * @param  array<int, int|string>  $roleIds
     */
    public function ensureAdministratorRemains(Collection $administrators, User $target, string $status, array $roleIds): void
    {
        $willManage = $status === User::STATUS_ACTIVE && Role::whereIn('role_id', $roleIds)
            ->whereHas('permissions', fn ($query) => $query->where('permission_name', 'users.manage'))->exists();

        if ($administrators->count() === 1 && $administrators->contains('user_id', $target->user_id) && ! $willManage) {
            throw ValidationException::withMessages(['account_status' => 'Keep at least one active account with user-management permission.']);
        }
    }

    /** @return array{username: string, email: ?string, employee_id: ?int, account_status: string, roles: array<int, int>} */
    public function auditValues(User $user): array
    {
        return [
            'username' => $user->username, 'email' => $user->email, 'employee_id' => $user->employee_id,
            'account_status' => $user->account_status, 'roles' => $user->roles()->orderBy('roles.role_id')->pluck('roles.role_id')->all(),
        ];
    }
}
