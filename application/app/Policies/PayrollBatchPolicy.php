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
        return $this->create($user) && in_array($batch->status, ['draft', 'returned'], true);
    }

    public function submit(User $user, PayrollBatch $batch): bool
    {
        return $this->update($user, $batch);
    }

    public function review(User $user, PayrollBatch $batch): bool
    {
        return $user->is_active && $user->role === Role::Approver && in_array($batch->status, ['submitted', 'approved'], true);
    }

    public function finalize(User $user, PayrollBatch $batch): bool
    {
        return $user->is_active && $user->role === Role::Approver && $batch->status === 'approved';
    }

    public function anchor(User $user, PayrollBatch $batch): bool
    {
        return $user->is_active && $user->role === Role::Approver && $batch->status === 'finalized';
    }
}
