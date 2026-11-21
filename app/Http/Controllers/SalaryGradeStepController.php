<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryGradeStepRequest;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalaryGradeStepController extends Controller
{
    public function index(Request $request): View
    {
        $scheduleId = $request->integer('salary_schedule_id') ?: null;

        $gradeSteps = SalaryGradeStep::query()
            ->with('salarySchedule')
            ->when($scheduleId !== null, fn ($query) => $query->where('salary_schedule_id', $scheduleId))
            ->orderBy('salary_schedule_id')
            ->orderBy('grade_number')
            ->orderBy('step_number')
            ->paginate(25)
            ->withQueryString();

        return view('salary-grade-steps.index', [
            'gradeSteps' => $gradeSteps,
            'schedules' => SalarySchedule::orderByDesc('effective_from')->get(),
            'scheduleId' => $scheduleId,
        ]);
    }

    public function create(Request $request): View
    {
        return view('salary-grade-steps.create', [
            'gradeStep' => new SalaryGradeStep([
                'salary_schedule_id' => $request->integer('salary_schedule_id') ?: null,
            ]),
            'schedules' => SalarySchedule::orderByDesc('effective_from')->get(),
        ]);
    }

    public function store(SalaryGradeStepRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new SalaryGradeStep, $request->validated(), $request->user());

        return redirect()
            ->route('salary-grade-steps.index', ['salary_schedule_id' => $request->input('salary_schedule_id')])
            ->with('status', 'Grade step created.');
    }

    public function show(SalaryGradeStep $salaryGradeStep): View
    {
        $salaryGradeStep->load('salarySchedule');

        return view('salary-grade-steps.show', ['gradeStep' => $salaryGradeStep]);
    }

    public function edit(SalaryGradeStep $salaryGradeStep): View
    {
        return view('salary-grade-steps.edit', [
            'gradeStep' => $salaryGradeStep,
            'schedules' => SalarySchedule::orderByDesc('effective_from')->get(),
        ]);
    }

    public function update(SalaryGradeStepRequest $request, SalaryGradeStep $salaryGradeStep, MasterDataMutationService $mutations): RedirectResponse
    {
        $salaryGradeStep = $mutations->update($salaryGradeStep, $request->validated(), $request->user());

        return redirect()
            ->route('salary-grade-steps.index', ['salary_schedule_id' => $request->input('salary_schedule_id')])
            ->with('status', 'Grade step updated.');
    }

    public function destroy(SalaryGradeStep $salaryGradeStep, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->delete($salaryGradeStep, auth()->user());

        return redirect()
            ->route('salary-grade-steps.index')
            ->with('status', 'Grade step deleted.');
    }
}
