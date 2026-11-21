<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollPeriodRequest;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use App\Services\MasterDataMutationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollPeriodController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'year' => ['nullable', 'integer', 'between:1900,9999'],
            'payroll_type' => ['nullable', 'in:MONTHLY'],
            'status' => ['nullable', 'in:OPEN,CLOSED'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', Rule::when($request->filled('from'), 'after_or_equal:from')],
        ]);
        $periods = PayrollPeriod::query()
            ->withCount('runs')
            ->withCount(['runs as processing_runs_count' => fn ($query) => $query->where('status', 'PROCESSING'), 'runs as finalized_runs_count' => fn ($query) => $query->where('status', 'FINALIZED')])
            ->when($filters['q'] ?? null, fn ($query, $value) => $query->where('period_name', 'like', '%'.$value.'%'))
            ->when($filters['year'] ?? null, fn ($query, $value) => $query->whereYear('start_date', $value))
            ->when($filters['payroll_type'] ?? null, fn ($query, $value) => $query->where('payroll_type', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('end_date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('start_date', '<=', $value))
            ->orderByDesc('start_date')
            ->orderByDesc('end_date')
            ->orderByDesc('payroll_period_id')
            ->paginate(15)->withQueryString();

        $statistics = [
            'Total payroll periods' => PayrollPeriod::count(),
            'Open periods' => PayrollPeriod::where('status', 'OPEN')->count(),
            'Periods with payroll runs' => PayrollPeriod::has('runs')->count(),
        ];

        return view('payroll-periods.index', compact('periods', 'filters', 'statistics'));
    }

    public function show(PayrollPeriod $payrollPeriod): View
    {
        return view('payroll-periods.show', [
            'period' => $payrollPeriod->loadCount('runs'),
            'runs' => $payrollPeriod->runs()->orderByDesc('run_number')->paginate(15),
        ]);
    }

    public function close(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        DB::transaction(function () use ($payrollPeriod): void {
            $locked = PayrollPeriod::whereKey($payrollPeriod->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== 'OPEN') {
                throw ValidationException::withMessages(['status' => 'Only an open payroll period can be closed.']);
            }
            $old = $locked->getAttributes();
            $locked->update(['status' => 'CLOSED']);
            app(AuditLogger::class)->log('PERIOD_CLOSED', 'Payroll', (int) auth()->id(), 'payroll_periods', (string) $locked->getKey(), $old, [
                ...$locked->getAttributes(), 'audit_context' => app(AuditLogger::class)->context('Closed the payroll period to new runs.'),
            ]);
        });

        return back()->with('status', 'Payroll period closed. Existing payroll runs are preserved; new runs cannot be created.');
    }

    public function create(): View
    {
        return view('payroll-periods.create', [
            'period' => new PayrollPeriod([
                'payroll_type' => PayrollPeriod::TYPE_MONTHLY,
                'status' => 'OPEN',
                'start_date' => now()->startOfMonth()->toDateString(),
                'end_date' => now()->endOfMonth()->toDateString(),
                'pay_date' => now()->endOfMonth()->toDateString(),
            ]),
        ]);
    }

    public function store(PayrollPeriodRequest $request, MasterDataMutationService $mutations): RedirectResponse
    {
        $mutations->create(new PayrollPeriod, $request->validated(), $request->user());

        return redirect()->route('payroll-periods.index')->with('status', 'Payroll period created.');
    }

    public function edit(PayrollPeriod $payrollPeriod): View
    {
        return view('payroll-periods.edit', ['period' => $payrollPeriod]);
    }

    public function update(PayrollPeriodRequest $request, PayrollPeriod $payrollPeriod): RedirectResponse
    {
        DB::transaction(function () use ($request, $payrollPeriod): void {
            $locked = PayrollPeriod::whereKey($payrollPeriod->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->runs()->exists()) {
                foreach (['period_name', 'payroll_type', 'start_date', 'end_date', 'pay_date'] as $field) {
                    $old = in_array($field, ['start_date', 'end_date', 'pay_date'], true) ? $locked->{$field}->toDateString() : $locked->{$field};
                    $new = in_array($field, ['start_date', 'end_date', 'pay_date'], true) ? $request->date($field)->toDateString() : $request->validated($field);
                    if ($old !== $new) {
                        throw ValidationException::withMessages([$field => 'Period details cannot change once payroll runs exist. Create a new period instead.']);
                    }
                }
            }
            $old = $locked->getAttributes();
            $locked->update($request->validated());
            app(AuditLogger::class)->log('PERIOD_UPDATED', 'Payroll', (int) auth()->id(), 'payroll_periods', (string) $locked->getKey(), $old, [
                ...$locked->getAttributes(), 'audit_context' => app(AuditLogger::class)->context('Updated a payroll period.'),
            ]);
        });

        return redirect()->route('payroll-periods.index')->with('status', 'Payroll period updated.');
    }

    public function destroy(PayrollPeriod $payrollPeriod, MasterDataMutationService $mutations): RedirectResponse
    {
        if ($payrollPeriod->runs()->exists()) {
            return back()->with('error', 'A period with payroll runs cannot be deleted.');
        }

        $mutations->delete($payrollPeriod, auth()->user());

        return redirect()->route('payroll-periods.index')->with('status', 'Payroll period deleted.');
    }
}
