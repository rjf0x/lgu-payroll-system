<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompensationProfileRequest;
use App\Models\CompensationProfile;
use App\Models\EmploymentRecord;
use App\Models\SalaryGradeStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CompensationProfileController extends Controller
{
    public function index(): View
    {
        $profiles = CompensationProfile::query()
            ->with(['employmentRecord.employee', 'employmentRecord.position', 'gradeStep.salarySchedule'])
            ->orderByDesc('effective_from')
            ->paginate(15);

        return view('compensation-profiles.index', compact('profiles'));
    }

    public function create(): View
    {
        return view('compensation-profiles.create', $this->formData(new CompensationProfile([
            'basis' => 'GRADE_STEP',
            'pay_basis' => CompensationProfile::BASIS_MONTHLY,
            'service_fraction' => 1.0000,
            'effective_from' => now()->toDateString(),
        ])));
    }

    public function store(CompensationProfileRequest $request): RedirectResponse
    {
        $profile = CompensationProfile::create($this->payload($request));

        return redirect()
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

        return redirect()
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
        ];
    }
}
