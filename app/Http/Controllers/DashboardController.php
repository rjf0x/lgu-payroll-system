<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing('roles');
        $dashboardRole = collect([Role::ADMIN, Role::PAYROLL_OFFICER, Role::APPROVER, Role::VIEWER])
            ->first(fn (string $role): bool => $user->roles->contains('role_name', $role));
        $metrics = [];
        if ($user->can('employees.view')) {
            $metrics['Employees'] = Employee::count();
            $metrics['Departments'] = Department::count();
        }
        if ($user->can('users.manage')) {
            $metrics['Active accounts'] = User::where('account_status', User::STATUS_ACTIVE)->count();
        }
        if ($user->can('payroll.view')) {
            $metrics['Finalized runs'] = PayrollRun::where('status', PayrollRun::STATUS_FINALIZED)->count();
        }
        $runs = PayrollRun::query()->with('payrollPeriod')->withCount('details');
        if ($dashboardRole === Role::APPROVER) {
            $runs->where('status', PayrollRun::STATUS_PENDING_APPROVAL)
                ->where('created_by', '!=', $user->user_id)
                ->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->user_id));
        } elseif ($dashboardRole === Role::VIEWER) {
            $runs->where('status', PayrollRun::STATUS_FINALIZED);
        }

        return view('dashboard', [
            'dashboardRole' => $dashboardRole,
            'metrics' => $metrics,
            'runs' => $user->can('payroll.view') ? $runs->orderByDesc('payroll_run_id')->limit(6)->get() : collect(),
            'roles' => $user->can('users.manage') ? Role::withCount('users')->orderBy('role_name')->get() : collect(),
        ]);
    }
}
