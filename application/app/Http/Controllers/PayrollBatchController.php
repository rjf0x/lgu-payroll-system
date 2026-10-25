<?php

namespace App\Http\Controllers;

use App\Http\Requests\AddPayrollEmployeesRequest;
use App\Http\Requests\CreatePayrollBatchRequest;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollEntry;
use App\Services\PayrollPreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PayrollBatchController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', PayrollBatch::class);

        return view('payroll-batches.index', ['batches' => PayrollBatch::withCount('entries')->orderByDesc('period_start')->orderByDesc('id')->paginate(20)]);
    }

    public function create(): View
    {
        Gate::authorize('create', PayrollBatch::class);

        return view('payroll-batches.create');
    }

    public function store(CreatePayrollBatchRequest $request, PayrollPreparationService $service): RedirectResponse
    {
        return redirect()->route('payroll-batches.show', $service->create($request, $request->validated()))->with('status', 'Monthly draft created. Select the covered employees.');
    }

    public function show(PayrollBatch $batch, PayrollPreparationService $service): View
    {
        Gate::authorize('view', $batch);
        $entries = $batch->entries()->with('employee', 'salaryRecord.rate.schedule')->orderBy('employee_number')->paginate(20);
        $issues = $entries->mapWithKeys(fn ($entry) => [$entry->id => $service->entryIssue($entry, $batch)]);

        return view('payroll-batches.show', compact('batch', 'entries', 'issues'));
    }

    public function select(PayrollBatch $batch, PayrollPreparationService $service): View
    {
        Gate::authorize('update', $batch);
        $employees = Employee::where('is_active', true)->whereNotIn('id', $batch->entries()->select('employee_id'))
            ->orderBy('employee_number')->paginate(20);
        $sources = $employees->mapWithKeys(fn ($employee) => [$employee->id => $service->source($employee, $batch)]);

        return view('payroll-batches.select', compact('batch', 'employees', 'sources'));
    }

    public function add(AddPayrollEmployeesRequest $request, PayrollBatch $batch, PayrollPreparationService $service): RedirectResponse
    {
        $service->add($request, $batch, $request->validated());

        return redirect()->route('payroll-batches.show', $batch)->with('status', 'Selected employees and salary snapshots saved to the draft.');
    }

    public function remove(Request $request, PayrollBatch $batch, PayrollEntry $entry, PayrollPreparationService $service): RedirectResponse
    {
        Gate::authorize('update', $batch);
        $data = $request->validate(['record_version' => ['required', 'integer', 'min:1']]);
        $service->remove($request, $batch, $entry, (int) $data['record_version']);

        return redirect()->route('payroll-batches.show', $batch)->with('status', 'Employee removed from this draft. Salary history is preserved.');
    }
}
