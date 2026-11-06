<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserAccountSeeder extends Seeder
{
    /**
     * Seed one demonstration account per role.
     *
     * These are development credentials for the mock-defense build. Replace the
     * passwords and review the accounts before any production use.
     */
    public function run(): void
    {
        $accounts = [
            ['username' => 'admin', 'role' => Role::ADMIN],
            ['username' => 'officer', 'role' => Role::PAYROLL_OFFICER],
            ['username' => 'approver', 'role' => Role::APPROVER],
            ['username' => 'viewer', 'role' => Role::VIEWER],
        ];

        foreach ($accounts as $account) {
            /** @var User $user */
            $user = User::updateOrCreate(
                ['username' => $account['username']],
                [
                    'email' => $account['username'].'@lgu.gov.ph',
                    'password_hash' => 'password',
                    'account_status' => User::STATUS_ACTIVE,
                ],
            );

            $role = Role::where('role_name', $account['role'])->firstOrFail();

            $user->roles()->syncWithoutDetaching([
                $role->role_id => ['assigned_at' => now()],
            ]);
        }
    }
}
