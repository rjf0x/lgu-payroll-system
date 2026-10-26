<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PayrollRuleVersion;
use App\Models\User;

class PayrollRuleVersionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, PayrollRuleVersion $version): bool
    {
        return $user->is_active && ($version->activated_at !== null || $user->role === Role::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === Role::Administrator;
    }

    public function update(User $user, PayrollRuleVersion $version): bool
    {
        return $this->create($user);
    }
}
