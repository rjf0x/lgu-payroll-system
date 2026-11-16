<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollDetailRequest;
use App\Http\Requests\PayrollEligibilityRequest;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Services\AuditLogger;
use App\Services\PayrollComputationService;
use App\Services\PayrollEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollDetailController extends Controller
{
    public function __construct(
        private readonly PayrollComputationService $service,
        private readonly PayrollEligibilityService $eligibility,
    ) {}

    public function store(PayrollEligibilityRequest $request, PayrollRun $payrollRun): RedirectResponse
    {
        DB::transaction(function () use ($request, $payrollRun): void {
            $run = PayrollRun::whereKey($payrollRun->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($run->isEditable(), 403, 'Return this payroll for correction before changing employee inclusion.');
            $employment = EmploymentRecord::with('employee')->findOrFail($request->integer('employment_id'));
            abort_if($run->details()->where('employment_id', $employment->getKey())->exists(), 422, 'This employment is already included.');
            $detail = $this->service->computeDetail($run, $employment, $run->payrollPeriod);
            $this->eligibility->resolve($detail, $request->user(), $request->validated('reason'));
        });

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', 'Employee included with documented eligibility. Enter and confirm current payroll inputs.');
    }

    public function destroy(PayrollEligibilityRequest $request, PayrollRun $payrollRun, PayrollDetail $payrollDetail): RedirectResponse
    {
        DB::transaction(function () use ($request, $payrollRun, $payrollDetail): void {
            $run = PayrollRun::whereKey($payrollRun->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($run->isEditable(), 403, 'Return this payroll for correction before changing employee inclusion.');
            $payrollDetail->refresh();
            $oldValues = ['detail' => $payrollDetail->toArray(), 'totals' => $this->eligibility->totals($run)];
            $payrollDetail->earnings()->delete();
            $payrollDetail->deductions()->delete();
            $payrollDetail->employerContributions()->delete();
            $payrollDetail->delete();
            app(AuditLogger::class)->log('PAYROLL_DETAIL_REMOVED', 'Payroll', $request->user()->getKey(), 'payroll_details', (string) $payrollDetail->getKey(), $oldValues, [
                'reason' => $request->validated('reason'), 'payroll_run_id' => $run->getKey(), 'totals' => $this->eligibility->totals($run),
            ]);
        });

        return redirect()->route('payroll-runs.show', $payrollRun)->with('status', 'Employee removed. Payroll totals recalculated.');
    }

    public function edit(PayrollRun $payrollRun, PayrollDetail $payrollDetail): View
    {
        abort_unless($payrollRun->isEditable(), 403, 'This payroll run can no longer be edited.');

        $payrollDetail->load(['employee', 'earnings', 'deductions.deductionType']);

        return view('payroll-details.edit', [
            'run' => $payrollRun,
            'detail' => $payrollDetail,
            'eligibilityIssue' => $this->eligibility->issue($payrollDetail, $payrollRun->payrollPeriod),
            'requiresEligibilityException' => $this->eligibility->requiresException($payrollDetail->employmentRecord),
            'earningTypes' => EarningType::where('status', 'ACTIVE')->orderBy('earning_name')->get(),
            'deductionTypes' => DeductionType::where('status', 'ACTIVE')
                ->where('is_statutory', false)
                ->where('deduction_category', '!=', 'LBP')
                ->orderBy('deduction_name')
                ->get(),
            'earningAmounts' => $payrollDetail->earnings->mapWithKeys(fn ($earning) => [$earning->earning_type_id => $earning->amount]),
            'deductionAmounts' => $payrollDetail->deductions
                ->filter(fn ($deduction) => ! $deduction->deductionType->is_statutory)
                ->mapWithKeys(fn ($deduction) => [$deduction->deduction_type_id => $deduction->amount]),
        ]);
    }

    public function confirm(Request $request, PayrollRun $payrollRun, PayrollDetail $payrollDetail): RedirectResponse
    {
        $request->validate(['inputs_confirmed' => ['required', 'accepted']]);

        DB::transaction(function () use ($request, $payrollRun, $payrollDetail): void {
            $run = PayrollRun::whereKey($payrollRun->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($run->isEditable(), 403, 'This payroll run can no longer be edited.');
            $payrollDetail->refresh();
            $payrollDetail->load('employmentRecord.employee');
            $period = $run->payrollPeriod;
            $issue = $this->eligibility->issue($payrollDetail, $period);
            if ($issue !== null) {
                throw ValidationException::withMessages(['eligibility_reason' => $issue]);
            }
            Validator::make(['payable_days' => $payrollDetail->payable_days], [
                'payable_days' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.$payrollDetail->maximumPayableDays($period)],
            ], ['payable_days.required' => 'Open this employee’s payroll detail and enter approved payable days before confirming.'])->validate();

            $oldValues = $payrollDetail->only(['payable_days', 'input_reference', 'input_recorded_by', 'input_confirmed_at', 'basic_pay', 'net_pay']);
            $payrollDetail->update([
                'input_recorded_by' => $request->user()->getKey(),
                'input_confirmed_at' => now(),
                'manual_adjustments' => true,
            ]);
            $this->service->computeDetail($run, $payrollDetail->employmentRecord, $period);
            $payrollDetail->refresh();
            app(AuditLogger::class)->log('PAYROLL_INPUTS_CONFIRMED', 'Payroll', $request->user()->getKey(), 'payroll_details', (string) $payrollDetail->getKey(), $oldValues, [
                ...$payrollDetail->only(['payable_days', 'input_reference', 'input_recorded_by', 'input_confirmed_at', 'basic_pay', 'net_pay']),
                'reason' => 'Confirmed existing payroll inputs from the employee breakdown and recomputed payroll.',
            ]);
        });

        return redirect()->route('payroll-runs.show', $payrollRun)
            ->with('status', $payrollDetail->employee_name_snapshot.': inputs confirmed, saved, and recomputed.');
    }

    public function update(PayrollDetailRequest $request, PayrollRun $payrollRun, PayrollDetail $payrollDetail): RedirectResponse
    {
        abort_unless($payrollRun->isEditable(), 403, 'This payroll run can no longer be edited.');

        DB::transaction(function () use ($request, $payrollRun, $payrollDetail): void {
            $locked = PayrollRun::whereKey($payrollRun->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->isEditable(), 403, 'This payroll run can no longer be edited.');
            $payrollDetail->refresh();
            $payrollDetail->load('employmentRecord.employee');
            if ($this->eligibility->requiresException($payrollDetail->employmentRecord)) {
                if (blank($request->validated('eligibility_reason'))) {
                    throw ValidationException::withMessages(['eligibility_reason' => 'Document why compensation remains payable for this archived or inactive employee.']);
                }
                $this->eligibility->resolve($payrollDetail, $request->user(), $request->validated('eligibility_reason'));
            }
            $oldValues = $payrollDetail->only(['payable_days', 'input_reference', 'basic_pay', 'net_pay', 'lbp_loan_deduction']);
            // Earnings.
            $payrollDetail->earnings()->delete();
            $earningTypes = EarningType::whereIn('earning_type_id', array_keys((array) $request->input('earnings', [])))
                ->get()->keyBy('earning_type_id');

            foreach ((array) $request->input('earnings', []) as $typeId => $amount) {
                $type = $earningTypes->get((int) $typeId);

                if ($type === null || ! is_numeric($amount) || (float) $amount <= 0) {
                    continue;
                }

                $payrollDetail->earnings()->create([
                    'earning_type_id' => $type->earning_type_id,
                    'earning_name_snapshot' => $type->earning_name,
                    'amount' => round((float) $amount, 2),
                    'is_taxable' => (bool) $type->is_taxable,
                ]);
            }

            // Manual (non-statutory) deductions.
            $payrollDetail->deductions()
                ->whereHas('deductionType', fn ($query) => $query->where('is_statutory', false))
                ->delete();

            $deductionTypes = DeductionType::whereIn('deduction_type_id', array_keys((array) $request->input('deductions', [])))
                ->where('is_statutory', false)
                ->where('status', 'ACTIVE')
                ->where('deduction_category', '!=', 'LBP')
                ->get()->keyBy('deduction_type_id');

            foreach ((array) $request->input('deductions', []) as $typeId => $amount) {
                $type = $deductionTypes->get((int) $typeId);

                if ($type === null || ! is_numeric($amount) || (float) $amount <= 0) {
                    continue;
                }

                $payrollDetail->deductions()->create([
                    'deduction_type_id' => $type->deduction_type_id,
                    'deduction_name_snapshot' => $type->deduction_name,
                    'amount' => round((float) $amount, 2),
                ]);
            }

            $payrollDetail->update([
                'lbp_loan_deduction' => round((float) $request->input('lbp_loan_deduction', 0), 2),
                'payable_days' => $request->validated('payable_days'),
                'input_reference' => $request->validated('input_reference'),
                'input_recorded_by' => $request->user()->getKey(),
                'input_confirmed_at' => now(),
                'manual_adjustments' => true,
            ]);

            $this->service->computeDetail($payrollRun, $payrollDetail->employmentRecord, $payrollRun->payrollPeriod);
            $payrollDetail->refresh();
            app(AuditLogger::class)->log('PAYROLL_DETAIL_UPDATED', 'Payroll', (int) auth()->id(), 'payroll_details', (string) $payrollDetail->getKey(), oldValues: $oldValues, newValues: [
                'payable_days' => $payrollDetail->payable_days,
                'input_reference' => $payrollDetail->input_reference,
                'lbp_loan_deduction' => $payrollDetail->lbp_loan_deduction,
                'basic_pay' => $payrollDetail->basic_pay,
                'net_pay' => $payrollDetail->net_pay,
            ]);
        });

        return redirect()
            ->route('payroll-runs.show', $payrollRun)
            ->with('status', 'Payroll detail updated.');
    }
}
