<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeeEarningAssignmentRequest;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\EmployeeEarningAssignment;
use App\Models\EmploymentRecord;
use App\Services\EmployeeWorkspaceContext;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class EmployeeEarningAssignmentController extends Controller
{
    public function __construct(private readonly EmployeeWorkspaceContext $workspace) {}

    public function index(): View
    {
        $employeeId = $this->workspace->employeeId();
        $filterEmployee = $employeeId !== null ? Employee::findOrFail($employeeId) : null;

        $assignments = EmployeeEarningAssignment::query()
            ->with(['employmentRecord.employee', 'employmentRecord.position', 'earningType'])
            ->when($employeeId !== null, fn ($query) => $query->whereHas('employmentRecord', fn ($employment) => $employment->where('employee_id', $employeeId)))
            ->orderByDesc('effective_from')
            ->paginate(15)->withQueryString();

        return view('employee-earning-assignments.index', compact('assignments', 'filterEmployee'));
    }

    public function create(): View
    {
        return view('employee-earning-assignments.create', $this->formData(new EmployeeEarningAssignment([
            'employment_id' => $this->workspace->employmentId(),
            'frequency' => 'MONTHLY',
            'effective_from' => now()->toDateString(),
        ])));
    }

    public function store(EmployeeEarningAssignmentRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $assignment = $mutations->create(new EmployeeEarningAssignment, $request->validated(), $request->user());

        return $this->workspace->redirect((int) $assignment->employmentRecord->employee_id, 'earnings', 'Earning assignment created.') ?? redirect()->route('employee-earning-assignments.index')->with('status', 'Earning assignment created.');
    }

    public function edit(EmployeeEarningAssignment $employeeEarningAssignment): View
    {
        return view('employee-earning-assignments.edit', $this->formData($employeeEarningAssignment));
    }

    public function update(EmployeeEarningAssignmentRequest $request, EmployeeEarningAssignment $employeeEarningAssignment, MasterDataMutationService $mutations): RedirectResponse
    {
        $employeeEarningAssignment = $mutations->update($employeeEarningAssignment, $request->validated(), $request->user());

        return $this->workspace->redirect((int) $employeeEarningAssignment->employmentRecord->employee_id, 'earnings', 'Earning assignment updated.') ?? redirect()->route('employee-earning-assignments.index')->with('status', 'Earning assignment updated.');
    }

    public function destroy(EmployeeEarningAssignment $employeeEarningAssignment, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($employeeEarningAssignment, auth()->user());

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
            'earningTypes' => EarningType::where(fn ($query) => $query->where(fn ($query) => $query->where('status', 'ACTIVE')->where('is_recurring', true))
                ->when($assignment->exists, fn ($query) => $query->orWhere('earning_type_id', $assignment->earning_type_id)))
                ->orderBy('earning_name')->get(),
        ] + $this->workspace->viewData(employmentId: $assignment->employment_id);
    }
}
