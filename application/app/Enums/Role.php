<?php

namespace App\Enums;

enum Role: string
{
    case Administrator = 'administrator';
    case PayrollOfficer = 'payroll_officer';
    case Approver = 'approver';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Administrator => 'Administrator',
            self::PayrollOfficer => 'Payroll Officer',
            self::Approver => 'Approver',
            self::Viewer => 'Viewer',
        };
    }

    public function workspace(): string
    {
        return match ($this) {
            self::Administrator => 'administration',
            self::PayrollOfficer => 'payroll-preparation',
            self::Approver => 'payroll-review',
            self::Viewer => 'payroll-records',
        };
    }
}
