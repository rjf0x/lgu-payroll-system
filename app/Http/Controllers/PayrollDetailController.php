<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollDetailRequest;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Services\AuditLogger;
use App\Services\PayrollComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PayrollDetailController extends Controller
{
    public function __construct(private readonly PayrollComputationService $service) {}

    public function edit(PayrollRun $payrollRun, PayrollDetail $payrollDetail): View
    {
        abort_unless($payrollRun->isEditable(), 403, 'This payroll run can no longer be edited.');

        $payrollDetail->load(['employee', 'earnings', 'deductions.deductionType']);

        return view('payroll-details.edit', [
            'run' => $payrollRun,
            'detail' => $payrollDetail,
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

    public function update(PayrollDetailRequest $request, PayrollRun $payrollRun, PayrollDetail $payrollDetail): RedirectResponse
    {
        abort_unless($payrollRun->isEditable(), 403, 'This payroll run can no longer be edited.');

        DB::transaction(function () use ($request, $payrollRun, $payrollDetail): void {
            $locked = PayrollRun::whereKey($payrollRun->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->isEditable(), 403, 'This payroll run can no longer be edited.');
            $payrollDetail->refresh();
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
