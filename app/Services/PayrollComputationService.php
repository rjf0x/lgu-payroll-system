<?php

namespace App\Services;

use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmployeeEarningAssignment;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\TaxBracket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Computes monthly payroll from compensation, approved payable-day inputs, and configured
 * earnings, deductions, tax brackets, and contribution rates.
 *
 * Monetary values use PHP floats rounded to 2 decimals at each step; the design
 * documents a consistent 2-decimal rounding rule for the mock-defense build.
 */
class PayrollComputationService
{
    private const PAYROLL_DAYS_PER_MONTH = 22.0;

    public function __construct(
        private readonly PayrollEligibilityService $eligibility,
        private readonly PayrollDuplicateGuard $duplicates,
    ) {}

    /**
     * Rebuild every detail for a run from the current source data.
     */
    public function computeRun(PayrollRun $run): void
    {
        $this->duplicates->transaction($run, 'computation', function () use ($run): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            abort_unless($run->isEditable(), 422, 'This payroll run can no longer be recomputed.');
            $period = $locked->payrollPeriod;
            $oldTotals = $this->eligibility->totals($locked);
            $employments = EmploymentRecord::query()
                ->with(['employee', 'department', 'position', 'employmentType'])
                ->where(function ($query) use ($locked, $period): void {
                    $query->whereIn('employment_id', $locked->details()->select('employment_id'));
                    if ($locked->run_type === 'REGULAR') {
                        $query->orWhere(function ($query) use ($period): void {
                            $query->where('employment_status', EmploymentRecord::STATUS_ACTIVE)
                                ->whereHas('employee', fn ($query) => $query->whereNull('employees.deleted_at'))
                                ->whereDate('start_date', '<=', $period->end_date)
                                ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->start_date));
                        });
                    }
                })->get();

            foreach ($employments as $employment) {
                if ($employment->employee !== null && $this->eligibility->overlaps($employment, $period)) {
                    $this->computeEditableDetail($locked, $employment, $period);
                }
            }
            $this->duplicates->validate($run, 'computation', preparing: true);

            $run->update([
                'status' => PayrollRun::STATUS_DRAFT,
                'processed_at' => now(),
            ]);
            app(AuditLogger::class)->log('PAYROLL_RECALCULATED', 'Payroll', auth()->id(), 'payroll_runs', (string) $run->getKey(), $oldTotals, [
                ...$this->eligibility->totals($locked), 'reason' => 'Recomputed from current compensation, statutory settings, and payroll inputs.',
            ]);
        }, serialize: false);
    }

    /**
     * Create a single employee detail with baseline earnings/deductions and totals.
     */
    public function computeDetail(PayrollRun $run, EmploymentRecord $employment, PayrollPeriod $period): PayrollDetail
    {
        return $this->duplicates->transaction($run, 'preparation', function () use ($run, $employment): PayrollDetail {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->getKey());
            abort_unless($locked->isEditable(), 422, 'This payroll run can no longer be recomputed.');

            $detail = $this->computeEditableDetail($locked, $employment, $locked->payrollPeriod);
            $this->duplicates->validate($locked, 'preparation', preparing: true);

            return $detail;
        }, serialize: false);
    }

    private function computeEditableDetail(PayrollRun $run, EmploymentRecord $employment, PayrollPeriod $period): PayrollDetail
    {
        $employment->load('employee');
        abort_unless($employment->employee !== null && $this->eligibility->overlaps($employment, $period), 422, 'Employment dates must overlap payroll coverage.');
        $compensationDate = $employment->end_date !== null && $employment->end_date->lt($period->end_date)
            ? $employment->end_date->toDateString()
            : $period->end_date->toDateString();
        $profile = $this->effectiveCompensation($employment, $compensationDate);

        $payBasis = $profile?->pay_basis ?? CompensationProfile::BASIS_MONTHLY;
        $serviceFraction = (float) ($profile?->service_fraction ?? 1.0);
        $gradeStep = $profile?->gradeStep;

        $detail = $run->details()->firstOrNew(['employment_id' => $employment->employment_id]);

        if ($run->run_type === 'REGULAR') {
            $detail->fill([
                'coverage_start' => $period->start_date->toDateString(),
                'coverage_end' => $period->end_date->toDateString(),
            ]);
        }

        $detail->fill([
            'employee_id' => $employment->employee_id,
            'employment_id' => $employment->employment_id,
            'employee_number_snapshot' => $employment->employee->employee_number,
            'employee_name_snapshot' => $employment->employee->full_name,
            'department_snapshot' => $employment->department?->department_name,
            'position_snapshot' => $employment->position?->position_title,
            'salary_grade_snapshot' => $gradeStep?->grade_number ?? $employment->position?->salary_grade,
            'salary_step_snapshot' => $gradeStep?->step_number,
            'salary_rate_snapshot' => $this->rateFor($profile),
            'pay_basis_snapshot' => $payBasis,
            'basic_pay' => match ($run->run_type) {
                'REGULAR' => $this->basicPay($profile, $payBasis, $serviceFraction, $detail->payable_days === null ? 0.0 : (float) $detail->payable_days),
                'ADJUSTMENT' => $detail->basic_pay ?? 0,
                default => 0,
            },
            'total_earnings' => 0,
            'gross_pay' => 0,
            'total_deductions' => 0,
            'nthp_before_lbp' => 0,
            'lbp_loan_deduction' => $detail->lbp_loan_deduction ?? 0,
            'net_pay' => 0,
            'payroll_status' => $detail->input_confirmed_at ? 'COMPUTED' : 'INPUT_REQUIRED',
        ]);

        $detail->save();
        $this->financialContext($detail, $period);
        if ($run->run_type === 'REGULAR' && ! $detail->manual_adjustments) {
            $detail->earnings()->delete();
            $detail->deductions()->delete();
            $this->seedEarnings($detail, $employment, $compensationDate);
            $this->seedDeductions($detail, $employment, $compensationDate);
        }
        foreach ($detail->earnings()->with(['earningType' => fn ($query) => $query->sharedLock()])->lockForUpdate()->get() as $earning) {
            if ($earning->earningType !== null) {
                $earning->update(['is_taxable' => $earning->earningType->is_taxable]);
            }
        }
        $this->recomputeTotals($detail, $period);
        $detail->refresh();
        $detail->forceFill(['computation_fingerprint' => $this->computationFingerprint($detail, $period)])->save();

        return $detail;
    }

    /**
     * Recompute statutory rows, tax, and totals for a detail that already has its
     * basic pay, earnings, and manual deductions in place. Used after manual edits.
     */
    public function recomputeTotals(PayrollDetail $detail, PayrollPeriod $period): void
    {
        DB::transaction(function () use ($detail, $period): void {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($detail->payroll_run_id);
            abort_unless($run->isEditable(), 422, 'This payroll run can no longer be recomputed.');
            $detail->refresh();
            $detail->forceFill(['computation_fingerprint' => null])->save();
            $this->recomputeEditableTotals($detail, $period);
        });
    }

    /**
     * Validate persisted calculations without changing payroll amounts or confirming inputs.
     * The caller holds the run lock in the submission transaction.
     */
    public function validateForSubmission(PayrollRun $run): void
    {
        $this->assertAccountingPolicy($run);
        $period = $run->payrollPeriod()->sharedLock()->firstOrFail();
        $errors = [];
        foreach ($run->details()->orderBy('payroll_detail_id')->lockForUpdate()->get() as $detail) {
            $issues = $this->financialIssues($detail, $period);
            if ($detail->input_confirmed_at === null || $detail->payable_days === null || $detail->payroll_status !== 'COMPUTED') {
                $issues[] = 'Confirm current inputs and recompute this detail before approval or finalization.';
            }
            if ($issues !== []) {
                $employee = "{$detail->employee_number_snapshot} ({$detail->employee_name_snapshot})";
                $errors['financial.'.$detail->getKey()] = [$employee.': '.implode(' ', $issues)];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function hasAccountingPolicy(PayrollRun $run): bool
    {
        return $run->run_type === 'REGULAR' || (filled(config('payroll.non_regular.'.$run->run_type.'.policy_reference'))
            && config('payroll.non_regular.'.$run->run_type.'.calculation_method') === 'existing_monthly');
    }

    public function assertAccountingPolicy(PayrollRun $run): void
    {
        if (! $this->hasAccountingPolicy($run)) {
            throw ValidationException::withMessages(['financial.policy' => 'Municipal Accounting Office policy confirmation is required for '.$run->run_type.' taxes and statutory contributions. Keep this run editable until the applicable calculation method and approved policy reference are configured.']);
        }
    }

    /** @return list<string> */
    private function financialIssues(PayrollDetail $detail, PayrollPeriod $period): array
    {
        $context = $this->financialContext($detail, $period);
        $profile = $context['profile'];
        $issues = [];
        if ($profile === null) {
            $issues[] = 'No effective compensation profile covers the payroll compensation date. Add the profile and recompute.';
        } elseif (($profile->grade_step_id !== null && $profile->gradeStep === null)
            || ($profile->grade_step_id === null && $profile->basic_rate === null)
            || $this->rateFor($profile) < 0
            || (float) $profile->service_fraction <= 0 || (float) $profile->service_fraction > 1
            || ! in_array($profile->pay_basis, [CompensationProfile::BASIS_MONTHLY, CompensationProfile::BASIS_DAILY, CompensationProfile::BASIS_PROPORTIONAL_MONTHLY, CompensationProfile::BASIS_HOURLY], true)) {
            $issues[] = 'The effective compensation profile has an invalid salary rate, pay basis, or service fraction.';
        } elseif ($profile->grade_step_id !== null) {
            $schedule = $profile->gradeStep->salarySchedule;
            $date = $context['compensation_date'];
            if ($schedule === null || $schedule->status !== 'ACTIVE'
                || $schedule->effective_from->toDateString() > $date
                || ($schedule->effective_to !== null && $schedule->effective_to->toDateString() < $date)) {
                $issues[] = 'The compensation salary schedule is not active and effective for the payroll compensation date.';
            }
        }

        foreach ($context['rates'] as $name => $rate) {
            if ($rate === null) {
                $issues[] = "No effective {$name} contribution rate covers this payroll period. Configure it and recompute.";
            } elseif ((float) $rate->minimum_compensation < 0
                || ($rate->maximum_compensation !== null && (float) $rate->maximum_compensation < (float) $rate->minimum_compensation)
                || (float) $rate->employee_rate < 0 || (float) $rate->employee_rate > 1
                || (float) $rate->employer_rate < 0 || (float) $rate->employer_rate > 1
                || (float) $rate->employee_fixed_amount < 0 || (float) $rate->employer_fixed_amount < 0) {
                $issues[] = "The effective {$name} contribution configuration contains invalid rates or compensation limits.";
            }
        }
        foreach ($context['deduction_types'] as $name => $type) {
            if ($type === null || ! $type->is_statutory || $type->status !== 'ACTIVE') {
                $issues[] = "Required statutory deduction type [{$name}] is missing, inactive, or not statutory.";
            }
        }
        if ($context['tax']->isEmpty()) {
            $issues[] = 'No effective annual tax configuration covers this payroll period. Configure it and recompute.';
        }
        foreach ($context['tax'] as $bracket) {
            if ((float) $bracket->minimum_income < 0 || (float) $bracket->base_tax < 0
                || (float) $bracket->marginal_tax_rate < 0 || (float) $bracket->marginal_tax_rate > 1
                || ($bracket->maximum_income !== null && (float) $bracket->maximum_income < (float) $bracket->minimum_income)) {
                $issues[] = 'The effective tax configuration contains an invalid income range or tax rate.';
                break;
            }
        }
        if ($issues !== []) {
            return $issues;
        }

        $detail->load(['earnings.earningType', 'deductions.deductionType', 'employerContributions']);
        foreach ($detail->earnings as $earning) {
            if ($earning->earningType === null || $earning->earningType->status !== 'ACTIVE'
                || $earning->is_taxable !== $earning->earningType->is_taxable) {
                $issues[] = 'An earning type is missing, inactive, or has changed tax treatment. Correct the earning configuration and recompute.';
            }
        }
        foreach ($detail->deductions as $deduction) {
            if ($deduction->deductionType === null || $deduction->deductionType->status !== 'ACTIVE'
                || $deduction->deductionType->deduction_category === 'LBP') {
                $issues[] = 'A deduction type is missing, inactive, or duplicates the separately recorded LBP deduction. Correct the deduction entries and recompute.';
            }
        }
        $basic = $detail->payrollRun->run_type === 'REGULAR'
            ? $this->basicPay($profile, $profile->pay_basis, (float) $profile->service_fraction, (float) $detail->payable_days)
            : (float) $detail->basic_pay;
        if ($basic < 0) {
            $issues[] = 'Negative adjustments and automatic recoveries require approved accounting rules and are unsupported.';
        }
        if ($detail->payable_days === null || (float) $detail->payable_days < 0
            || (float) $detail->payable_days > ($profile->pay_basis === CompensationProfile::BASIS_DAILY ? (int) $period->start_date->diffInDays($period->end_date) + 1 : self::PAYROLL_DAYS_PER_MONTH)) {
            $issues[] = 'Payable days are outside the permitted range. Correct and reconfirm the inputs.';
        }
        $contributions = $this->computeContributions($basic, $period, $detail);
        $taxableGross = round($basic + (float) $detail->earnings->where('is_taxable', true)->sum('amount'), 2);
        $annualTaxable = max(0.0, ($taxableGross - round(array_sum(array_column($contributions['employee'], 'amount')), 2)) * 12);
        $matchingBrackets = $context['tax']->filter(fn (TaxBracket $bracket): bool => (float) $bracket->minimum_income <= $annualTaxable
            && ($bracket->maximum_income === null || (float) $bracket->maximum_income >= $annualTaxable));
        if ($matchingBrackets->isEmpty()) {
            $issues[] = 'The effective tax brackets do not cover the calculated taxable income. Correct the tax coverage and recompute.';
        }
        $orderedBrackets = $context['tax']->sortBy('minimum_income')->values();
        foreach ($orderedBrackets as $index => $bracket) {
            if ($index > 0 && ($orderedBrackets[$index - 1]->maximum_income === null
                || (float) $bracket->minimum_income < (float) $orderedBrackets[$index - 1]->maximum_income)) {
                $issues[] = 'The effective tax brackets overlap. Correct the ambiguous tax coverage and recompute.';
                break;
            }
        }

        $tax = $this->computeWithholdingTax($taxableGross, round(array_sum(array_column($contributions['employee'], 'amount')), 2), $period);
        $expectedDeductions = collect($contributions['employee'])->mapWithKeys(fn (array $row): array => [$row['type_id'] => $this->cents($row['amount'])])->filter(fn (int $amount): bool => $amount > 0)->all();
        if ($tax > 0) {
            $expectedDeductions[$context['deduction_types']['Withholding Tax']->getKey()] = $this->cents($tax);
        }
        $statutoryRows = $detail->deductions->filter(fn ($row): bool => (bool) $row->deductionType?->is_statutory);
        $actualDeductions = $statutoryRows->mapWithKeys(fn ($row): array => [$row->deduction_type_id => $this->cents($row->amount)])->all();
        ksort($expectedDeductions);
        ksort($actualDeductions);
        if ($expectedDeductions !== $actualDeductions || $statutoryRows->count() !== count($actualDeductions)) {
            $issues[] = 'Saved statutory deductions or withholding tax do not match the applicable calculations. Recompute payroll.';
        }
        $expectedEmployer = collect($contributions['employer'])->mapWithKeys(fn (array $row): array => [$row['rate_id'] => $this->cents($row['amount'])])->filter(fn (int $amount): bool => $amount > 0)->all();
        $actualEmployer = $detail->employerContributions->mapWithKeys(fn ($row): array => [$row->contribution_rate_id => $this->cents($row->amount)])->all();
        ksort($expectedEmployer);
        ksort($actualEmployer);
        if ($expectedEmployer !== $actualEmployer || $detail->employerContributions->count() !== count($actualEmployer)) {
            $issues[] = 'Saved employer contributions do not match the applicable contribution rates. Recompute payroll.';
        }

        $earnings = (int) $detail->earnings->sum(fn ($row): int => $this->cents($row->amount));
        $deductions = (int) $detail->deductions->sum(fn ($row): int => $this->cents($row->amount));
        $gross = $this->cents($basic) + $earnings;
        $expected = ['salary_rate_snapshot' => $this->cents($this->rateFor($profile)), 'basic_pay' => $this->cents($basic),
            'total_earnings' => $earnings, 'gross_pay' => $gross, 'total_deductions' => $deductions,
            'nthp_before_lbp' => $gross - $deductions, 'net_pay' => $gross - $deductions - $this->cents($detail->lbp_loan_deduction)];
        foreach ($expected as $field => $amount) {
            if ($detail->getAttribute($field) === null || $this->cents($detail->getAttribute($field)) !== $amount) {
                $issues[] = "Saved {$field} does not reconcile with the payroll inputs and line items. Recompute payroll.";
            }
        }
        if ($this->cents($detail->lbp_loan_deduction) < 0 || $detail->earnings->contains(fn ($row): bool => $this->cents($row->amount) < 0)
            || $detail->deductions->contains(fn ($row): bool => $this->cents($row->amount) < 0)) {
            $issues[] = 'Earnings, deductions, and the LBP deduction must not contain negative amounts.';
        }
        if ($expected['net_pay'] < 0 || $this->cents($detail->net_pay) < 0) {
            $issues[] = 'Net pay is negative. Correct deductions or payroll inputs before submission; no authorized negative-pay exception policy is configured.';
        }
        if ($detail->computation_fingerprint === null || ! hash_equals($detail->computation_fingerprint, $this->computationFingerprint($detail, $period))) {
            $issues[] = 'The calculation is missing or stale: compensation, payable days, earnings, deductions, LBP, or applicable configuration changed. Save and recompute payroll before submission.';
        }

        return $issues;
    }

    private function cents(float|string|null $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    /**
     * Lock the effective sources so calculation, fingerprinting, and submission use a stable context.
     *
     * @return array{compensation_date: string, profile: ?CompensationProfile, rates: Collection<string, ContributionRate|null>, tax: Collection<int, TaxBracket>, deduction_types: Collection<string, DeductionType|null>, assignments: array<string, mixed>}
     */
    private function financialContext(PayrollDetail $detail, PayrollPeriod $period): array
    {
        $employment = $detail->employmentRecord()->sharedLock()->firstOrFail();
        $date = $employment->end_date !== null && $employment->end_date->lt($period->end_date) ? $employment->end_date->toDateString() : $period->end_date->toDateString();
        $rates = collect([ContributionRate::TYPE_GSIS, ContributionRate::TYPE_PHILHEALTH, ContributionRate::TYPE_PAGIBIG])
            ->mapWithKeys(fn (string $name): array => [$name => $this->effectiveContributionRate($name, $period)]);
        $deductionTypes = collect(['GSIS Contribution', 'PhilHealth Contribution', 'Pag-IBIG Contribution', 'Withholding Tax'])
            ->mapWithKeys(fn (string $name): array => [$name => DeductionType::where('deduction_name', $name)->sharedLock()->first()]);
        $assignments = [];
        foreach (['earnings' => EmployeeEarningAssignment::class, 'deductions' => EmployeeDeductionAssignment::class] as $key => $model) {
            $relation = $key === 'earnings' ? 'earningType' : 'deductionType';
            $assignments[$key] = $model::where('employment_id', $employment->getKey())
                ->with([$relation => fn ($query) => $query->sharedLock()])
                ->whereDate('effective_from', '<=', $date)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
                ->orderBy('assignment_id')->sharedLock()->get()->toArray();
        }

        return ['compensation_date' => $date, 'profile' => $this->effectiveCompensation($employment, $date),
            'rates' => $rates, 'tax' => $this->effectiveTaxBrackets($period), 'deduction_types' => $deductionTypes, 'assignments' => $assignments];
    }

    private function computationFingerprint(PayrollDetail $detail, PayrollPeriod $period): string
    {
        $context = $this->financialContext($detail, $period);
        $state = $detail->only(['employment_id', 'pay_basis_snapshot', 'salary_rate_snapshot', 'basic_pay', 'payable_days', 'lbp_loan_deduction', 'manual_adjustments',
            'total_earnings', 'gross_pay', 'total_deductions', 'nthp_before_lbp', 'net_pay']);
        if ($detail->payrollRun->run_type !== 'REGULAR') {
            $state['entitlement'] = $detail->only(['coverage_start', 'coverage_end', 'compensation_reference', 'compensation_reason', 'accounting_basis', 'overlap_justification', 'original_payroll_detail_id']);
            $state['policy'] = config('payroll.non_regular.'.$detail->payrollRun->run_type);
            $state['purpose'] = $detail->payrollRun->purpose;
        }
        $state['earnings'] = $detail->earnings()->with(['earningType' => fn ($query) => $query->sharedLock()])->orderBy('payroll_earning_id')->sharedLock()->get()->toArray();
        $state['deductions'] = $detail->deductions()->with(['deductionType' => fn ($query) => $query->sharedLock()])->orderBy('payroll_deduction_id')->sharedLock()->get()->toArray();
        $state['employer'] = $detail->employerContributions()->orderBy('employer_contribution_id')->sharedLock()->get()->toArray();

        return hash('sha256', json_encode(['version' => 1, 'period' => $period->only(['start_date', 'end_date']), 'context' => $context, 'state' => $state], JSON_THROW_ON_ERROR));
    }

    /** @return Collection<int, TaxBracket> */
    private function effectiveTaxBrackets(PayrollPeriod $period): Collection
    {
        $brackets = TaxBracket::where('tax_type', 'ANNUAL')->where('pay_frequency', 'ANNUAL')
            ->whereDate('effective_from', '<=', $period->end_date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->end_date))
            ->orderByDesc('effective_from')->orderBy('minimum_income')->orderBy('tax_bracket_id')->sharedLock()->get();

        return $brackets->filter(fn (TaxBracket $bracket): bool => $bracket->effective_from->equalTo($brackets->first()->effective_from))->values();
    }

    private function recomputeEditableTotals(PayrollDetail $detail, PayrollPeriod $period): void
    {
        $detail->load(['earnings', 'deductions', 'deductions.deductionType']);

        if (! $this->hasAccountingPolicy($detail->payrollRun)) {
            $detail->deductions()->whereHas('deductionType', fn ($query) => $query->where('is_statutory', true))->delete();
            $detail->employerContributions()->delete();
            $earnings = round((float) $detail->earnings->sum('amount'), 2);
            $deductions = round((float) $detail->deductions()->sum('amount'), 2);
            $gross = round((float) $detail->basic_pay + $earnings, 2);
            $detail->update([
                'total_earnings' => $earnings, 'gross_pay' => $gross, 'total_deductions' => $deductions,
                'nthp_before_lbp' => round($gross - $deductions, 2),
                'net_pay' => round($gross - $deductions - (float) $detail->lbp_loan_deduction, 2),
                'payroll_status' => 'POLICY_REQUIRED',
            ]);

            return;
        }

        $basicPay = (float) $detail->basic_pay;
        $totalEarnings = round((float) $detail->earnings->sum('amount'), 2);
        $grossPay = round($basicPay + $totalEarnings, 2);
        $taxableGross = round($basicPay + (float) $detail->earnings->where('is_taxable', true)->sum('amount'), 2);

        $contributions = $this->computeContributions($basicPay, $period, $detail);
        $employeePreTax = round(array_sum(array_column($contributions['employee'], 'amount')), 2);
        $tax = $this->computeWithholdingTax($taxableGross, $employeePreTax, $period);

        // Replace previously computed (statutory) deduction rows.
        $detail->deductions()
            ->whereHas('deductionType', fn ($query) => $query->where('is_statutory', true))
            ->delete();

        foreach ($contributions['employee'] as $contribution) {
            if ($contribution['amount'] <= 0) {
                continue;
            }

            $detail->deductions()->create([
                'deduction_type_id' => $contribution['type_id'],
                'deduction_name_snapshot' => $contribution['label'],
                'amount' => $contribution['amount'],
                'remarks' => 'Computed contribution',
            ]);
        }

        if ($tax > 0) {
            $taxType = $this->requireDeductionType('Withholding Tax', $detail);
            $detail->deductions()->create([
                'deduction_type_id' => $taxType->deduction_type_id,
                'deduction_name_snapshot' => $taxType->deduction_name,
                'amount' => $tax,
                'remarks' => 'Annual graduated projection / 12',
            ]);
        }

        $detail->employerContributions()->delete();
        foreach ($contributions['employer'] as $contribution) {
            if ($contribution['amount'] <= 0) {
                continue;
            }

            $detail->employerContributions()->create([
                'contribution_rate_id' => $contribution['rate_id'],
                'contribution_type_snapshot' => $contribution['label'],
                'amount' => $contribution['amount'],
            ]);
        }

        $totalDeductions = round((float) $detail->deductions()->sum('amount'), 2);
        $lbp = round((float) $detail->lbp_loan_deduction, 2);
        $nthp = round($grossPay - $totalDeductions, 2);
        $netPay = round($nthp - $lbp, 2);

        $detail->update([
            'total_earnings' => $totalEarnings,
            'gross_pay' => $grossPay,
            'total_deductions' => $totalDeductions,
            'nthp_before_lbp' => $nthp,
            'net_pay' => $netPay,
        ]);
    }

    /**
     * @return array{employee: array<int, array{type_id:int, label:string, amount:float}>, employer: array<int, array{rate_id:int, label:string, amount:float}>}
     */
    private function computeContributions(float $basicPay, PayrollPeriod $period, PayrollDetail $detail): array
    {
        $map = [
            ContributionRate::TYPE_GSIS => 'GSIS Contribution',
            ContributionRate::TYPE_PHILHEALTH => 'PhilHealth Contribution',
            ContributionRate::TYPE_PAGIBIG => 'Pag-IBIG Contribution',
        ];

        $employee = [];
        $employer = [];

        foreach ($map as $contributionType => $deductionName) {
            $rate = $this->effectiveContributionRate($contributionType, $period);

            if ($rate === null) {
                continue;
            }

            $base = max($basicPay, (float) $rate->minimum_compensation);

            if ($rate->maximum_compensation !== null) {
                $base = min($base, (float) $rate->maximum_compensation);
            }

            $employeeAmount = round($base * (float) $rate->employee_rate + (float) ($rate->employee_fixed_amount ?? 0), 2);
            $employerAmount = round($base * (float) $rate->employer_rate + (float) ($rate->employer_fixed_amount ?? 0), 2);

            $type = $this->requireDeductionType($deductionName, $detail);

            $employee[] = ['type_id' => $type->deduction_type_id, 'label' => $deductionName, 'amount' => $employeeAmount];
            $employer[] = ['rate_id' => $rate->contribution_rate_id, 'label' => $contributionType.' (Employer)', 'amount' => $employerAmount];
        }

        return ['employee' => $employee, 'employer' => $employer];
    }

    private function computeWithholdingTax(float $taxableMonthlyGross, float $monthlyPreTaxContributions, PayrollPeriod $period): float
    {
        $annualTaxable = max(0.0, ($taxableMonthlyGross - $monthlyPreTaxContributions) * 12);

        $bracket = $this->effectiveTaxBrackets($period)
            ->filter(fn (TaxBracket $bracket): bool => (float) $bracket->minimum_income <= $annualTaxable
                && ($bracket->maximum_income === null || (float) $bracket->maximum_income >= $annualTaxable))
            ->sortByDesc('minimum_income')->first();

        if ($bracket === null) {
            return 0.0;
        }

        $annualTax = (float) $bracket->base_tax
            + (float) $bracket->marginal_tax_rate * ($annualTaxable - (float) $bracket->minimum_income);

        return round(max(0.0, $annualTax) / 12, 2);
    }

    private function basicPay(?CompensationProfile $profile, string $payBasis, float $serviceFraction, float $payableDays): float
    {
        $rate = $this->rateFor($profile);
        if ($payBasis === CompensationProfile::BASIS_DAILY) {
            return round($rate * $payableDays, 2);
        }

        return round($rate * $serviceFraction * min(1.0, $payableDays / self::PAYROLL_DAYS_PER_MONTH), 2);
    }

    private function rateFor(?CompensationProfile $profile): float
    {
        if ($profile === null) {
            return 0.0;
        }

        if ($profile->grade_step_id !== null) {
            return (float) $profile->gradeStep->monthly_salary;
        }

        return (float) $profile->basic_rate;
    }

    private function seedEarnings(PayrollDetail $detail, EmploymentRecord $employment, string $periodEnd): void
    {
        $assignments = EmployeeEarningAssignment::query()
            ->with('earningType')
            ->where('employment_id', $employment->employment_id)
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodEnd))
            ->get();

        foreach ($assignments as $assignment) {
            $amount = $this->assignmentAmount($assignment->amount, $assignment->percentage, (float) $detail->basic_pay);

            if ($amount <= 0) {
                continue;
            }

            $detail->earnings()->create([
                'earning_type_id' => $assignment->earning_type_id,
                'earning_name_snapshot' => $assignment->earningType->earning_name,
                'amount' => $amount,
                'is_taxable' => (bool) $assignment->earningType->is_taxable,
            ]);
        }
    }

    private function seedDeductions(PayrollDetail $detail, EmploymentRecord $employment, string $periodEnd): void
    {
        $assignments = EmployeeDeductionAssignment::query()
            ->with('deductionType')
            ->where('employment_id', $employment->employment_id)
            ->whereDate('effective_from', '<=', $periodEnd)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $periodEnd))
            ->get();

        foreach ($assignments as $assignment) {
            $type = $assignment->deductionType;

            // Statutory and LBP deductions are handled separately.
            if ($type->is_statutory || $type->deduction_category === 'LBP') {
                continue;
            }

            $amount = $this->assignmentAmount($assignment->amount, $assignment->percentage, (float) $detail->basic_pay);

            if ($amount <= 0) {
                continue;
            }

            $detail->deductions()->create([
                'deduction_type_id' => $type->deduction_type_id,
                'deduction_name_snapshot' => $type->deduction_name,
                'amount' => $amount,
            ]);
        }
    }

    private function assignmentAmount(?float $amount, ?float $percentage, float $basicPay): float
    {
        if ($amount !== null) {
            return round((float) $amount, 2);
        }

        if ($percentage !== null) {
            return round($basicPay * (float) $percentage / 100, 2);
        }

        return 0.0;
    }

    private function effectiveCompensation(EmploymentRecord $employment, string $date): ?CompensationProfile
    {
        return $employment->compensationProfiles()
            ->with(['gradeStep' => fn ($query) => $query->sharedLock(), 'gradeStep.salarySchedule' => fn ($query) => $query->sharedLock()])
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->sharedLock()
            ->first();
    }

    private function effectiveContributionRate(string $contributionType, PayrollPeriod $period): ?ContributionRate
    {
        return ContributionRate::query()
            ->where('contribution_type', $contributionType)
            ->whereDate('effective_from', '<=', $period->end_date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->end_date))
            ->orderByDesc('effective_from')
            ->sharedLock()
            ->first();
    }

    private function requireDeductionType(string $name, PayrollDetail $detail): DeductionType
    {
        $type = DeductionType::query()->where('deduction_name', $name)->sharedLock()->first();

        if ($type === null || $type->status !== 'ACTIVE' || ! $type->is_statutory) {
            throw ValidationException::withMessages([
                'financial.'.$detail->getKey() => "{$detail->employee_number_snapshot} ({$detail->employee_name_snapshot}): Required deduction type [{$name}] is missing, inactive, or not statutory. Correct the configuration and recompute.",
            ]);
        }

        return $type;
    }
}
