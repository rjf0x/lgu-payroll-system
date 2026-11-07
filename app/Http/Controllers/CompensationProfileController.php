<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompensationProfileRequest;
use App\Models\CompensationProfile;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\SalaryGradeStep;
use App\Services\EmployeeWorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompensationProfileController extends Controller
{
    public function __construct(private readonly EmployeeWorkspaceContext $workspace) {}

    public function index(): View
    {
        $employeeId = $this->workspace->employeeId();
        $filterEmployee = $employeeId !== null ? Employee::findOrFail($employeeId) : null;

        $profiles = CompensationProfile::query()
            ->with(['employmentRecord.employee', 'employmentRecord.position', 'gradeStep.salarySchedule'])
            ->when($employeeId !== null, fn ($query) => $query->whereHas('employmentRecord', fn ($employment) => $employment->where('employee_id', $employeeId)))
            ->orderByDesc('effective_from')
            ->paginate(15)->withQueryString();

        return view('compensation-profiles.index', compact('profiles', 'filterEmployee'));
    }

    public function create(): View
    {
        return view('compensation-profiles.create', $this->formData(new CompensationProfile([
            'employment_id' => $this->workspace->employmentId(),
            'basis' => 'GRADE_STEP',
            'pay_basis' => CompensationProfile::BASIS_MONTHLY,
            'service_fraction' => 1.0000,
            'effective_from' => now()->toDateString(),
        ])));
    }

    public function store(CompensationProfileRequest $request): RedirectResponse
    {
        $profile = CompensationProfile::create($this->payload($request));

        return $this->workspace->redirect((int) $profile->employmentRecord->employee_id, 'compensation', 'Compensation profile created.') ?? redirect()
            ->route('compensation-profiles.show', $profile)
            ->with('status', 'Compensation profile created.');
    }

    public function show(CompensationProfile $compensationProfile): View
    {
        $compensationProfile->load([
            'employmentRecord.employee',
            'employmentRecord.position',
            'gradeStep.salarySchedule',
        ]);

        return view('compensation-profiles.show', ['profile' => $compensationProfile]);
    }

    public function edit(CompensationProfile $compensationProfile): View
    {
        $compensationProfile->basis = $compensationProfile->grade_step_id !== null ? 'GRADE_STEP' : 'DIRECT_RATE';

        return view('compensation-profiles.edit', $this->formData($compensationProfile));
    }

    public function update(CompensationProfileRequest $request, CompensationProfile $compensationProfile): RedirectResponse
    {
        $compensationProfile->update($this->payload($request));

        return $this->workspace->redirect((int) $compensationProfile->employmentRecord->employee_id, 'compensation', 'Compensation profile updated.') ?? redirect()
            ->route('compensation-profiles.show', $compensationProfile)
            ->with('status', 'Compensation profile updated.');
    }

    public function destroy(CompensationProfile $compensationProfile): RedirectResponse
    {
        $compensationProfile->delete();

        return redirect()
            ->route('compensation-profiles.index')
            ->with('status', 'Compensation profile deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CompensationProfileRequest $request): array
    {
        return $request->safe()->except('basis');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(CompensationProfile $profile): array
    {
        return [
            'profile' => $profile,
            'employments' => EmploymentRecord::with(['employee', 'position'])->get(),
            'gradeSteps' => SalaryGradeStep::with('salarySchedule')
                ->orderBy('salary_schedule_id')
                ->orderBy('grade_number')
                ->orderBy('step_number')
                ->get(),
        ] + $this->workspace->viewData(employmentId: $profile->employment_id);
    }
}
