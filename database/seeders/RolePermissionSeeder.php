<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the four system roles, their permissions, and the role-permission map.
     */
    public function run(): void
    {
        $permissions = [
            'users.manage' => 'Create, update, and deactivate user accounts.',
            'roles.manage' => 'Manage roles and their permissions.',
            'employees.view' => 'View employee and employment records.',
            'employees.manage' => 'Create and update employee and employment records.',
            'attendance.manage' => 'Record attendance and manage attendance adjustments.',
            'leave.manage' => 'Manage leave applications, balances, and transactions.',
            'compensation.manage' => 'Manage salary schedules, grade steps, and compensation profiles.',
            'payroll.prepare' => 'Prepare and compute a monthly payroll run.',
            'payroll.submit' => 'Submit a payroll run for independent approval.',
            'payroll.review' => 'Review a submitted payroll run.',
            'payroll.approve' => 'Approve a submitted payroll run.',
            'payroll.reject' => 'Reject a submitted payroll run.',
            'payroll.finalize' => 'Finalize an approved payroll run.',
            'payroll.view' => 'View payroll records and registers.',
            'reports.generate' => 'Generate pay slips and authorized reports.',
            'audit.view' => 'View audit trail records.',
            'blockchain.verify' => 'Anchor and verify payroll integrity proofs.',
        ];

        foreach ($permissions as $name => $description) {
            Permission::updateOrCreate(
                ['permission_name' => $name],
                ['description' => $description],
            );
        }

        $roleDefinitions = [
            Role::ADMIN => [
                'description' => 'Manages system configuration, user accounts, and access settings.',
                'permissions' => [
                    'users.manage', 'roles.manage', 'employees.view', 'employees.manage',
                    'attendance.manage', 'leave.manage', 'compensation.manage', 'payroll.view',
                    'reports.generate', 'audit.view', 'blockchain.verify',
                ],
            ],
            Role::PAYROLL_OFFICER => [
                'description' => 'Maintains payroll inputs, prepares payroll computations, and submits payroll for approval.',
                'permissions' => [
                    'employees.view', 'employees.manage', 'attendance.manage', 'leave.manage',
                    'compensation.manage', 'payroll.prepare', 'payroll.submit', 'payroll.view',
                    'reports.generate', 'blockchain.verify',
                ],
            ],
            Role::APPROVER => [
                'description' => 'Independently reviews, approves, rejects, or returns payroll.',
                'permissions' => [
                    'employees.view', 'payroll.review', 'payroll.approve', 'payroll.reject',
                    'payroll.finalize', 'payroll.view', 'reports.generate', 'blockchain.verify',
                ],
            ],
            Role::VIEWER => [
                'description' => 'Has read-only access to authorized records and reports.',
                'permissions' => [
                    'employees.view', 'payroll.view', 'reports.generate',
                ],
            ],
        ];

        foreach ($roleDefinitions as $roleName => $definition) {
            /** @var Role $role */
            $role = Role::updateOrCreate(
                ['role_name' => $roleName],
                ['description' => $definition['description']],
            );

            $permissionIds = Permission::whereIn('permission_name', $definition['permissions'])
                ->pluck('permission_id');

            $role->permissions()->sync($permissionIds);
        }
    }
}
