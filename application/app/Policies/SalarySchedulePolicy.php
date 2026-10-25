<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\SalarySchedule;
use App\Models\User;

class SalarySchedulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    public function view(User $user, SalarySchedule $schedule): bool
    {
        return $user->is_active && ($schedule->published_at !== null || $user->role === Role::Administrator);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === Role::Administrator;
    }

    public function update(User $user, SalarySchedule $schedule): bool
    {
        return $this->create($user);
    }
}
