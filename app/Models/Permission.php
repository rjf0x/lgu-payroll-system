<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['permission_name', 'description'])]
class Permission extends Model
{
    use HasFactory;

    protected $primaryKey = 'permission_id';

    public const USERS_MANAGE = 'users.manage';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const EMPLOYEES_VIEW = 'employees.view';

    public const EMPLOYEES_MANAGE = 'employees.manage';

    public const COMPENSATION_MANAGE = 'compensation.manage';

    public const PAYROLL_PREPARE = 'payroll.prepare';

    public const PAYROLL_SUBMIT = 'payroll.submit';

    public const PAYROLL_REVIEW = 'payroll.review';

    public const PAYROLL_APPROVE = 'payroll.approve';

    public const PAYROLL_REJECT = 'payroll.reject';

    public const PAYROLL_FINALIZE = 'payroll.finalize';

    public const PAYROLL_VIEW = 'payroll.view';

    public const REPORTS_GENERATE = 'reports.generate';

    public const REPORTS_EXPORT = 'reports.export';

    public const AUDIT_VIEW = 'audit.view';

    public const BLOCKCHAIN_VERIFY = 'blockchain.verify';

    /**
     * Every permission name recognized by the application.
     *
     * @return array<int, string>
     */
    public static function names(): array
    {
        return [
            self::USERS_MANAGE,
            self::SETTINGS_MANAGE,
            self::ROLES_MANAGE,
            self::EMPLOYEES_VIEW,
            self::EMPLOYEES_MANAGE,
            self::COMPENSATION_MANAGE,
            self::PAYROLL_PREPARE,
            self::PAYROLL_SUBMIT,
            self::PAYROLL_REVIEW,
            self::PAYROLL_APPROVE,
            self::PAYROLL_REJECT,
            self::PAYROLL_FINALIZE,
            self::PAYROLL_VIEW,
            self::REPORTS_GENERATE,
            self::REPORTS_EXPORT,
            self::AUDIT_VIEW,
            self::BLOCKCHAIN_VERIFY,
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id')
            ->withPivot('created_at');
    }
}
