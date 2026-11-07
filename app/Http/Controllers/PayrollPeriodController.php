<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollPeriodRequest;
use App\Models\PayrollPeriod;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollPeriodController extends Controller
{
    public function index(): View
    {
        $periods = PayrollPeriod::query()
            ->withCount('runs')
            ->orderByDesc('start_date')
            ->paginate(15);

        return view('payroll-periods.index', compact('periods'));
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

    public function store(PayrollPeriodRequest $request): RedirectResponse
    {
        PayrollPeriod::create($request->validated());

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
            app(AuditLogger::class)->log('PERIOD_UPDATED', 'Payroll', (int) auth()->id(), 'payroll_periods', (string) $locked->getKey(), $old, $locked->getAttributes());
        });

        return redirect()->route('payroll-periods.index')->with('status', 'Payroll period updated.');
    }

    public function destroy(PayrollPeriod $payrollPeriod): RedirectResponse
    {
        if ($payrollPeriod->runs()->exists()) {
            return back()->with('error', 'A period with payroll runs cannot be deleted.');
        }

        $payrollPeriod->delete();

        return redirect()->route('payroll-periods.index')->with('status', 'Payroll period deleted.');
    }
}
