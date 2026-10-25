<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSalaryRecordRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Services\SalaryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class EmployeeSalaryController extends Controller
{
    private function queryDate(Request $request, string $key): string
    {
        $value = $request->query($key, today()->format('Y-m-d'));
        abort_if(Validator::make([$key => $value], [$key => ['bail', 'required', 'string', 'date_format:Y-m-d', 'after_or_equal:1000-01-01', 'before_or_equal:9999-12-31']])->fails(), 422, 'Use a valid date in YYYY-MM-DD format.');

        return $value;
    }

    public function index(Request $request, Employee $employee, SalaryService $service): View
    {
        Gate::authorize('view', $employee);
        $date = $this->queryDate($request, 'as_of');
        $current = $service->salaryAsOf($employee, $date);

        return view('employee-salaries.index', [
            'employee' => $employee, 'date' => $date, 'current' => $current,
            'review' => $service->stepReview($current, $date),
            'latestSchedule' => $current ? $service->applicableSchedule($employee->personnel_classification, $date, $current->is_demonstration) : null,
            'history' => EmployeeSalaryRecord::with('rate.schedule')->where('employee_id', $employee->id)->orderByDesc('effective_from')->paginate(20)->appends(['as_of' => $date]),
        ]);
    }

    public function create(Request $request, Employee $employee, SalaryService $service): View
    {
        Gate::authorize('update', $employee);
        $date = $this->queryDate($request, 'effective_from');
        $demo = $request->query('is_demonstration', '1');
        abort_unless(in_array($demo, ['0', '1'], true), 422, 'Select demonstration or externally authorized data.');
        $schedule = $service->applicableSchedule($employee->personnel_classification, $date, $demo === '1');

        return view('employee-salaries.form', [
            'employee' => $employee, 'date' => $date, 'demo' => $demo, 'schedule' => $schedule,
            'rates' => $schedule?->rates()->orderBy('grade')->orderBy('step')->get() ?? collect(),
        ]);
    }

    public function store(SaveSalaryRecordRequest $request, Employee $employee, SalaryService $service): RedirectResponse
    {
        $service->recordSalary($request, $employee, $request->validated());

        return redirect()->route('employee-salaries.index', ['employee' => $employee, 'as_of' => $request->validated('effective_from')])->with('status', 'Authorized salary change recorded. Previous history is preserved.');
    }
}
