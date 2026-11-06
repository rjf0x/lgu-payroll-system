<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeDeductionAssignmentRequest;
use App\Models\DeductionType;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmploymentRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeDeductionAssignmentController extends Controller
{
    public function index(): View
    {
        $assignments = EmployeeDeductionAssignment::query()
            ->with(['employmentRecord.employee', 'employmentRecord.position', 'deductionType'])
            ->orderByDesc('effective_from')
            ->paginate(15);

        return view('employee-deduction-assignments.index', compact('assignments'));
    }

    public function create(): View
    {
        return view('employee-deduction-assignments.create', $this->formData(new EmployeeDeductionAssignment([
            'status' => 'ACTIVE',
            'effective_from' => now()->toDateString(),
        ])));
    }

    public function store(EmployeeDeductionAssignmentRequest $request): RedirectResponse
    {
        EmployeeDeductionAssignment::create($request->validated());

        return redirect()->route('employee-deduction-assignments.index')->with('status', 'Deduction assignment created.');
    }

    public function edit(EmployeeDeductionAssignment $employeeDeductionAssignment): View
    {
        return view('employee-deduction-assignments.edit', $this->formData($employeeDeductionAssignment));
    }

    public function update(EmployeeDeductionAssignmentRequest $request, EmployeeDeductionAssignment $employeeDeductionAssignment): RedirectResponse
    {
        $employeeDeductionAssignment->update($request->validated());

        return redirect()->route('employee-deduction-assignments.index')->with('status', 'Deduction assignment updated.');
    }

    public function destroy(EmployeeDeductionAssignment $employeeDeductionAssignment): RedirectResponse
    {
        $employeeDeductionAssignment->delete();

        return redirect()->route('employee-deduction-assignments.index')->with('status', 'Deduction assignment deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(EmployeeDeductionAssignment $assignment): array
    {
        return [
            'assignment' => $assignment,
            'employments' => EmploymentRecord::with(['employee', 'position'])->get(),
            'deductionTypes' => DeductionType::orderBy('deduction_name')->get(),
        ];
    }
}
