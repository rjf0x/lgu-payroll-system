<?php

namespace App\Http\Controllers;

use App\Http\Requests\LeaveApplicationRequest;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveBalance;
use App\Models\LeaveTransaction;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LeaveApplicationController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: null;
        $employeeId = $request->integer('employee_id') ?: null;

        $applications = LeaveApplication::query()
            ->with(['employee', 'leaveType', 'approvedBy'])
            ->when($status !== null, fn ($query) => $query->where('approval_status', $status))
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('start_date')
            ->paginate(20)
            ->withQueryString();

        return view('leave-applications.index', [
            'applications' => $applications,
            'employees' => Employee::orderBy('last_name')->get(),
            'status' => $status,
            'employeeId' => $employeeId,
        ]);
    }

    public function create(): View
    {
        return view('leave-applications.create', [
            'application' => new LeaveApplication([
                'start_date' => now()->toDateString(),
                'end_date' => now()->toDateString(),
                'total_days' => 1,
            ]),
            'employees' => Employee::orderBy('last_name')->get(),
            'leaveTypes' => LeaveType::orderBy('leave_name')->get(),
        ]);
    }

    public function store(LeaveApplicationRequest $request): RedirectResponse
    {
        $application = LeaveApplication::create(
            $request->validated() + ['approval_status' => LeaveApplication::STATUS_PENDING],
        );

        return redirect()
            ->route('leave-applications.show', $application)
            ->with('status', 'Leave application filed.');
    }

    public function show(LeaveApplication $leaveApplication): View
    {
        $leaveApplication->load(['employee', 'leaveType', 'approvedBy', 'transactions']);

        return view('leave-applications.show', ['application' => $leaveApplication]);
    }

    public function approve(LeaveApplication $leaveApplication): RedirectResponse
    {
        if ($leaveApplication->approval_status !== LeaveApplication::STATUS_PENDING) {
            return back()->with('error', 'This application has already been reviewed.');
        }

        DB::transaction(function () use ($leaveApplication): void {
            $year = (int) $leaveApplication->start_date->year;

            /** @var LeaveBalance $balance */
            $balance = LeaveBalance::firstOrCreate(
                [
                    'employee_id' => $leaveApplication->employee_id,
                    'leave_type_id' => $leaveApplication->leave_type_id,
                    'leave_year' => $year,
                ],
                [
                    'opening_balance' => 0,
                    'total_credits' => 0,
                    'total_used' => 0,
                    'available_balance' => 0,
                ],
            );

            $used = (float) $balance->total_used + (float) $leaveApplication->total_days;
            $available = (float) $balance->opening_balance + (float) $balance->total_credits - $used;

            $balance->update([
                'total_used' => $used,
                'available_balance' => $available,
            ]);

            LeaveTransaction::create([
                'leave_balance_id' => $balance->leave_balance_id,
                'leave_application_id' => $leaveApplication->leave_application_id,
                'transaction_type' => 'USED',
                'days' => $leaveApplication->total_days,
                'balance_after' => $available,
                'transaction_date' => now()->toDateString(),
                'created_by' => auth()->id(),
                'remarks' => 'Leave application approved',
            ]);

            $leaveApplication->update([
                'approval_status' => LeaveApplication::STATUS_APPROVED,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ]);
        });

        return redirect()
            ->route('leave-applications.show', $leaveApplication)
            ->with('status', 'Leave application approved.');
    }

    public function reject(LeaveApplication $leaveApplication): RedirectResponse
    {
        if ($leaveApplication->approval_status !== LeaveApplication::STATUS_PENDING) {
            return back()->with('error', 'This application has already been reviewed.');
        }

        $leaveApplication->update([
            'approval_status' => LeaveApplication::STATUS_REJECTED,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        return redirect()
            ->route('leave-applications.show', $leaveApplication)
            ->with('status', 'Leave application rejected.');
    }

    public function destroy(LeaveApplication $leaveApplication): RedirectResponse
    {
        if ($leaveApplication->approval_status === LeaveApplication::STATUS_APPROVED) {
            return back()->with('error', 'Approved applications cannot be deleted.');
        }

        $leaveApplication->delete();

        return redirect()
            ->route('leave-applications.index')
            ->with('status', 'Leave application deleted.');
    }
}
