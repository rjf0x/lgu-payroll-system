<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\PayrollBatch;
use App\Models\User;

class PayrollBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && in_array($user->role, [Role::PayrollOfficer, Role::Approver, Role::Viewer], true);
    }

    public function view(User $user, PayrollBatch $batch): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->is_active && $user->role === Role::PayrollOfficer;
    }

    public function update(User $user, PayrollBatch $batch): bool
    {
        return $this->create($user) && $batch->status === 'draft';
    }
}
