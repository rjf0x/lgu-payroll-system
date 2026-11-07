<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeDeductionAssignmentRequest;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmploymentRecord;
use App\Services\EmployeeWorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeDeductionAssignmentController extends Controller
{
    public function __construct(private readonly EmployeeWorkspaceContext $workspace) {}

    public function index(): View
    {
        $employeeId = $this->workspace->employeeId();
        $filterEmployee = $employeeId !== null ? Employee::findOrFail($employeeId) : null;

        $assignments = EmployeeDeductionAssignment::query()
            ->with(['employmentRecord.employee', 'employmentRecord.position', 'deductionType'])
            ->when($employeeId !== null, fn ($query) => $query->whereHas('employmentRecord', fn ($employment) => $employment->where('employee_id', $employeeId)))
            ->orderByDesc('effective_from')
            ->paginate(15)->withQueryString();

        return view('employee-deduction-assignments.index', compact('assignments', 'filterEmployee'));
    }

    public function create(): View
    {
        return view('employee-deduction-assignments.create', $this->formData(new EmployeeDeductionAssignment([
            'employment_id' => $this->workspace->employmentId(),
            'status' => 'ACTIVE',
            'effective_from' => now()->toDateString(),
        ])));
    }

    public function store(EmployeeDeductionAssignmentRequest $request): RedirectResponse
    {
        $assignment = EmployeeDeductionAssignment::create($request->validated());

        return $this->workspace->redirect((int) $assignment->employmentRecord->employee_id, 'deductions', 'Deduction assignment created.') ?? redirect()->route('employee-deduction-assignments.index')->with('status', 'Deduction assignment created.');
    }

    public function edit(EmployeeDeductionAssignment $employeeDeductionAssignment): View
    {
        return view('employee-deduction-assignments.edit', $this->formData($employeeDeductionAssignment));
    }

    public function update(EmployeeDeductionAssignmentRequest $request, EmployeeDeductionAssignment $employeeDeductionAssignment): RedirectResponse
    {
        $employeeDeductionAssignment->update($request->validated());

        return $this->workspace->redirect((int) $employeeDeductionAssignment->employmentRecord->employee_id, 'deductions', 'Deduction assignment updated.') ?? redirect()->route('employee-deduction-assignments.index')->with('status', 'Deduction assignment updated.');
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
        ] + $this->workspace->viewData(employmentId: $assignment->employment_id);
    }
}
