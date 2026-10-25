<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\User;

class EmployeePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, [Role::PayrollOfficer, Role::Approver, Role::Viewer], true);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === Role::PayrollOfficer;
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->create($user);
    }
}
