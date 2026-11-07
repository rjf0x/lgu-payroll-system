<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeRequest;
use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmployeeEarningAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();

        $employees = Employee::query()
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('employee_number', 'like', "%{$search}%")
                        ->orWhere('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->withCount('employmentRecords')
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(15)
            ->withQueryString();

        return view('employees.index', compact('employees', 'search'));
    }

    public function create(): View
    {
        return view('employees.create', ['employee' => new Employee]);
    }

    public function store(EmployeeRequest $request): RedirectResponse
    {
        $employee = Employee::create($request->validated());

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', 'Employee created.');
    }

    public function show(Request $request, Employee $employee): View
    {
        $employee->load([
            'employmentRecords.department',
            'employmentRecords.position',
            'employmentRecords.employmentType',
        ]);

        $employmentIds = $employee->employmentRecords->modelKeys();
        $compensation = CompensationProfile::with(['employmentRecord.position', 'gradeStep'])->whereIn('employment_id', $employmentIds)->orderByDesc('effective_from')->limit(5)->get();
        $earnings = EmployeeEarningAssignment::with(['earningType', 'employmentRecord.position'])->whereIn('employment_id', $employmentIds)->orderByDesc('effective_from')->limit(5)->get();
        $deductions = EmployeeDeductionAssignment::with(['deductionType', 'employmentRecord.position'])->whereIn('employment_id', $employmentIds)->orderByDesc('effective_from')->limit(5)->get();
        $attendance = $request->user()->can('attendance.manage') ? $employee->attendances()->orderByDesc('attendance_date')->limit(5)->get() : collect();
        $leave = $request->user()->can('leave.manage') ? $employee->leaveApplications()->with('leaveType')->orderByDesc('start_date')->limit(5)->get() : collect();
        $leaveBalances = $request->user()->can('leave.manage') ? $employee->leaveBalances()->with('leaveType')->where('leave_year', now()->year)->get() : collect();
        $directoryContext = $request->only(['q', 'page']);

        return view('employees.show', compact('employee', 'compensation', 'earnings', 'deductions', 'attendance', 'leave', 'leaveBalances', 'directoryContext'));
    }

    public function edit(Employee $employee): View
    {
        return view('employees.edit', compact('employee'));
    }

    public function update(EmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $employee->update($request->validated());

        return redirect()
            ->route('employees.show', $employee)
            ->with('status', 'Employee updated.');
    }

    public function destroy(Employee $employee): RedirectResponse
    {
        $employee->delete();

        return redirect()
            ->route('employees.index')
            ->with('status', 'Employee archived.');
    }
}
