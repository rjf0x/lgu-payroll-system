<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeEarningAssignmentRequest;
use App\Models\EarningType;
use App\Models\EmployeeEarningAssignment;
use App\Models\EmploymentRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeEarningAssignmentController extends Controller
{
    public function index(): View
    {
        $assignments = EmployeeEarningAssignment::query()
            ->with(['employmentRecord.employee', 'employmentRecord.position', 'earningType'])
            ->orderByDesc('effective_from')
            ->paginate(15);

        return view('employee-earning-assignments.index', compact('assignments'));
    }

    public function create(): View
    {
        return view('employee-earning-assignments.create', $this->formData(new EmployeeEarningAssignment([
            'frequency' => 'MONTHLY',
            'effective_from' => now()->toDateString(),
        ])));
    }

    public function store(EmployeeEarningAssignmentRequest $request): RedirectResponse
    {
        EmployeeEarningAssignment::create($request->validated());

        return redirect()->route('employee-earning-assignments.index')->with('status', 'Earning assignment created.');
    }

    public function edit(EmployeeEarningAssignment $employeeEarningAssignment): View
    {
        return view('employee-earning-assignments.edit', $this->formData($employeeEarningAssignment));
    }

    public function update(EmployeeEarningAssignmentRequest $request, EmployeeEarningAssignment $employeeEarningAssignment): RedirectResponse
    {
        $employeeEarningAssignment->update($request->validated());

        return redirect()->route('employee-earning-assignments.index')->with('status', 'Earning assignment updated.');
    }

    public function destroy(EmployeeEarningAssignment $employeeEarningAssignment): RedirectResponse
    {
        $employeeEarningAssignment->delete();

        return redirect()->route('employee-earning-assignments.index')->with('status', 'Earning assignment deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(EmployeeEarningAssignment $assignment): array
    {
        return [
            'assignment' => $assignment,
            'employments' => EmploymentRecord::with(['employee', 'position'])->get(),
            'earningTypes' => EarningType::orderBy('earning_name')->get(),
        ];
    }
}
