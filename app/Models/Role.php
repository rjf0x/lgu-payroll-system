<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['role_name', 'description'])]
class Role extends Model
{
    use HasFactory;

    protected $primaryKey = 'role_id';

    public const ADMIN = 'Admin';

    public const PAYROLL_OFFICER = 'Payroll Officer';

    public const APPROVER = 'Approver';

    public const VIEWER = 'Viewer';

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles', 'role_id', 'user_id')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id')
            ->withPivot('created_at');
    }
}
