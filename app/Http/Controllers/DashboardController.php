<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
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
            $today = today()->toDateString();
            $metrics['Active employees'] = Employee::whereHas('employmentRecords', fn ($query) => $query
                ->where('employment_status', EmploymentRecord::STATUS_ACTIVE)
                ->whereDate('start_date', '<=', $today)
                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today)))->count();
            $metrics['Departments'] = Department::count();
        }
        if ($user->can('users.manage')) {
            $metrics['Active accounts'] = User::where('account_status', User::STATUS_ACTIVE)->count();
        }
        $runs = PayrollRun::query()->with('payrollPeriod')->withCount('details');
        $nextActions = [];
        $incompleteInputs = fn (Builder $query): Builder => $query->whereNull('payable_days')
            ->orWhereNull('input_confirmed_at')->orWhereNull('input_reference')->orWhere('input_reference', '');
        if ($user->can('payroll.view') && $user->can('payroll.prepare')) {
            $metrics['Pending payroll tasks'] = PayrollRun::whereIn('status', [PayrollRun::STATUS_DRAFT, PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_REJECTED])->count();
            $preparation = PayrollRun::where('status', PayrollRun::STATUS_DRAFT);
            if ($user->can('payroll.submit')) {
                $preparation->where(fn (Builder $query) => $query->whereDoesntHave('details')->orWhereHas('details', $incompleteInputs));
            }
            $count = (clone $preparation)->count();
            $next = $preparation->oldest('updated_at')->orderBy('payroll_run_id')->first();
            $nextActions[] = ['title' => 'Payroll preparation', 'count' => $count, 'description' => $user->can('payroll.submit') ? 'Draft runs needing computation or confirmed payroll inputs.' : 'Draft runs available for preparation and input review.', 'href' => $next ? route('payroll-runs.show', $next) : route('payroll-runs.create'), 'label' => $next ? 'Continue preparation' : 'Prepare payroll'];

            $corrections = PayrollRun::whereIn('status', [PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_REJECTED]);
            $count = (clone $corrections)->count();
            $next = $corrections->oldest('updated_at')->orderBy('payroll_run_id')->first();
            $nextActions[] = ['title' => 'Payroll corrections', 'count' => $count, 'description' => 'Returned and rejected runs needing changes before resubmission.', 'href' => $next ? route('payroll-runs.show', $next) : route('payroll-runs.index', ['status' => PayrollRun::STATUS_RETURNED]), 'label' => $next ? 'Correct next payroll' : 'View returned payroll'];
        }
        if ($user->can('payroll.view') && $user->can('payroll.submit')) {
            $ready = PayrollRun::where('status', PayrollRun::STATUS_DRAFT)->has('details')->whereDoesntHave('details', $incompleteInputs);
            $count = (clone $ready)->count();
            $next = $ready->oldest('updated_at')->orderBy('payroll_run_id')->first();
            $nextActions[] = ['title' => 'Ready to submit', 'count' => $count, 'description' => 'Draft runs with confirmed inputs for every employee. Check totals before submitting.', 'href' => $next ? route('payroll-runs.show', $next) : route('payroll-runs.index', ['status' => PayrollRun::STATUS_DRAFT]), 'label' => $next ? 'Review and submit payroll' : 'View draft payroll'];
        }
        if ($user->can('payroll.view') && ($user->can('payroll.approve') || $user->can('payroll.review') || $user->can('payroll.reject'))) {
            $reviewable = PayrollRun::where('status', PayrollRun::STATUS_PENDING_APPROVAL)
                ->where('created_by', '!=', $user->user_id)
                ->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->user_id));
            $count = (clone $reviewable)->count();
            $next = $reviewable->orderBy('submitted_at')->orderBy('payroll_run_id')->first();
            $nextActions[] = ['title' => 'Independent review', 'count' => $count, 'description' => 'Submitted runs you neither prepared nor submitted. The oldest submission comes first.', 'href' => $next ? route('payroll-runs.show', $next) : route('payroll-runs.index', ['status' => PayrollRun::STATUS_PENDING_APPROVAL]), 'label' => $next ? 'Review next payroll' : 'Pending approvals'];
        }
        if ($user->can('payroll.view') && $user->can('payroll.finalize')) {
            $approved = PayrollRun::where('status', PayrollRun::STATUS_APPROVED);
            $count = (clone $approved)->count();
            $metrics['Pending payroll tasks'] = ($metrics['Pending payroll tasks'] ?? 0) + $count;
            $next = $approved->orderBy('updated_at')->first();
            $nextActions[] = ['title' => 'Ready to finalize', 'count' => $count, 'description' => 'Approved runs awaiting finalization.', 'href' => $next ? route('payroll-runs.show', $next) : route('payroll-runs.index', ['status' => PayrollRun::STATUS_APPROVED]), 'label' => $next ? 'Review approved payroll' : 'View approved payroll'];
        }
        if ($dashboardRole === Role::APPROVER) {
            $runs->where('status', PayrollRun::STATUS_PENDING_APPROVAL)
                ->where('created_by', '!=', $user->user_id)
                ->where(fn ($query) => $query->whereNull('submitted_by')->orWhere('submitted_by', '!=', $user->user_id));
        } elseif ($dashboardRole === Role::VIEWER) {
            $runs->where('status', PayrollRun::STATUS_FINALIZED);
        }

        if ($user->can('payroll.view')) {
            $metrics['Finalized runs'] = PayrollRun::where('status', PayrollRun::STATUS_FINALIZED)->count();
        }

        return view('dashboard', [
            'dashboardRole' => $dashboardRole,
            'nextActions' => $nextActions,
            'metrics' => $metrics,
            'totalUniqueUsers' => $user->can('users.manage') ? User::count() : null,
            'runs' => $user->can('payroll.view') ? $runs
                ->orderByRaw('COALESCE(finalized_at, processed_at, created_at) DESC')
                ->orderByDesc('payroll_run_id')->limit(5)->get() : collect(),
            'roles' => $user->can('users.manage') ? Role::withCount('users')->orderBy('role_name')->get() : collect(),
        ]);
    }
}
