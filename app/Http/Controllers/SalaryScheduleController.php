<?php

namespace App\Http\Controllers;

use App\Http\Requests\SalaryScheduleRequest;
use App\Models\SalarySchedule;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SalaryScheduleController extends Controller
{
    public function index(): View
    {
        $schedules = SalarySchedule::query()
            ->withCount('gradeSteps')
            ->orderByDesc('effective_from')
            ->paginate(15);

        return view('salary-schedules.index', compact('schedules'));
    }

    public function create(): View
    {
        return view('salary-schedules.create', [
            'schedule' => new SalarySchedule([
                'status' => SalarySchedule::STATUS_ACTIVE,
                'effective_from' => now()->toDateString(),
            ]),
        ]);
    }

    public function store(SalaryScheduleRequest $request): RedirectResponse
    {
        $schedule = SalarySchedule::create($request->validated());

        return redirect()
            ->route('salary-schedules.show', $schedule)
            ->with('status', 'Salary schedule created.');
    }

    public function show(SalarySchedule $salarySchedule): View
    {
        $salarySchedule->load([
            'gradeSteps' => fn ($query) => $query->orderBy('grade_number')->orderBy('step_number'),
        ]);

        return view('salary-schedules.show', ['schedule' => $salarySchedule]);
    }

    public function edit(SalarySchedule $salarySchedule): View
    {
        return view('salary-schedules.edit', ['schedule' => $salarySchedule]);
    }

    public function update(SalaryScheduleRequest $request, SalarySchedule $salarySchedule): RedirectResponse
    {
        $salarySchedule->update($request->validated());

        return redirect()
            ->route('salary-schedules.show', $salarySchedule)
            ->with('status', 'Salary schedule updated.');
    }

    public function destroy(SalarySchedule $salarySchedule): RedirectResponse
    {
        $salarySchedule->delete();

        return redirect()
            ->route('salary-schedules.index')
            ->with('status', 'Salary schedule deleted.');
    }
}
