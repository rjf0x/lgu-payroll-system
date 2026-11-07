<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmploymentRecordRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Services\EmployeeWorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmploymentRecordController extends Controller
{
    public function __construct(private readonly EmployeeWorkspaceContext $workspace) {}

    public function index(Request $request): View
    {
        $employeeId = $request->integer('employee_id') ?: null;

        $employmentRecords = EmploymentRecord::query()
            ->with(['employee', 'department', 'position', 'employmentType'])
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->orderByDesc('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('employment-records.index', compact('employmentRecords', 'employeeId'));
    }

    public function create(Request $request): View
    {
        return view('employment-records.create', $this->formData(
            new EmploymentRecord([
                'employee_id' => $this->workspace->employeeId(),
                'employment_status' => EmploymentRecord::STATUS_ACTIVE,
                'start_date' => now()->toDateString(),
            ]),
        ));
    }

    public function store(EmploymentRecordRequest $request): RedirectResponse
    {
        $record = EmploymentRecord::create($request->validated());

        return $this->workspace->redirect((int) $record->employee_id, 'employment', 'Employment record created.') ?? redirect()
            ->route('employment-records.show', $record)
            ->with('status', 'Employment record created.');
    }

    public function show(EmploymentRecord $employmentRecord): View
    {
        $employmentRecord->load([
            'employee',
            'department',
            'position',
            'employmentType',
            'compensationProfiles.gradeStep.salarySchedule',
            'history.recordedBy',
        ]);

        return view('employment-records.show', compact('employmentRecord'));
    }

    public function edit(EmploymentRecord $employmentRecord): View
    {
        return view('employment-records.edit', $this->formData($employmentRecord));
    }

    public function update(EmploymentRecordRequest $request, EmploymentRecord $employmentRecord): RedirectResponse
    {
        $employmentRecord->update($request->validated());

        return $this->workspace->redirect((int) $employmentRecord->employee_id, 'employment', 'Employment record updated.') ?? redirect()
            ->route('employment-records.show', $employmentRecord)
            ->with('status', 'Employment record updated.');
    }

    public function destroy(EmploymentRecord $employmentRecord): RedirectResponse
    {
        $employmentRecord->delete();

        return redirect()
            ->route('employment-records.index')
            ->with('status', 'Employment record deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(EmploymentRecord $employmentRecord): array
    {
        return [
            'employmentRecord' => $employmentRecord,
            'employees' => Employee::orderBy('last_name')->get(),
            'departments' => Department::orderBy('department_name')->get(),
            'positions' => Position::orderBy('position_title')->get(),
            'employmentTypes' => EmploymentType::orderBy('employment_type_name')->get(),
        ] + $this->workspace->viewData($employmentRecord->employee_id);
    }
}
