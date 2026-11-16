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
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

    public function __construct(private readonly PayrollEligibilityService $eligibility) {}

    /**
     * Rebuild every detail for a run from the current source data.
     */
    public function computeRun(PayrollRun $run): void
    {
        DB::transaction(function () use ($run): void {
            $locked = PayrollRun::whereKey($run->getKey())->lockForUpdate()->firstOrFail();
            $run->setRawAttributes($locked->getAttributes(), true);
            abort_unless($run->isEditable(), 422, 'This payroll run can no longer be recomputed.');
            $period = $locked->payrollPeriod;
            $oldTotals = $this->eligibility->totals($locked);
            $employments = EmploymentRecord::query()
                ->with(['employee', 'department', 'position', 'employmentType'])
                ->where(function ($query) use ($locked, $period): void {
                    $query->where(function ($query) use ($period): void {
                        $query->where('employment_status', EmploymentRecord::STATUS_ACTIVE)
                            ->whereHas('employee', fn ($query) => $query->whereNull('employees.deleted_at'))
                            ->whereDate('start_date', '<=', $period->end_date)
                            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $period->start_date));
                    })->orWhereIn('employment_id', $locked->details()->select('employment_id'));
                })->get();

            foreach ($employments as $employment) {
                if ($employment->employee !== null && $this->eligibility->overlaps($employment, $period)) {
                    $this->computeDetail($run, $employment, $period);
                }
            }

            $run->update([
                'status' => PayrollRun::STATUS_DRAFT,
                'processed_at' => now(),
            ]);
            app(AuditLogger::class)->log('PAYROLL_RECALCULATED', 'Payroll', auth()->id(), 'payroll_runs', (string) $run->getKey(), $oldTotals, [
                ...$this->eligibility->totals($locked), 'reason' => 'Recomputed from current compensation, statutory settings, and payroll inputs.',
            ]);
        });
    }

    /**
     * Create a single employee detail with baseline earnings/deductions and totals.
     */
    public function computeDetail(PayrollRun $run, EmploymentRecord $employment, PayrollPeriod $period): PayrollDetail
    {
        return DB::transaction(function () use ($run, $employment, $period): PayrollDetail {
            $locked = PayrollRun::query()->lockForUpdate()->findOrFail($run->getKey());
            abort_unless($locked->isEditable(), 422, 'This payroll run can no longer be recomputed.');

            return $this->computeEditableDetail($locked, $employment, $period);
        });
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
            'basic_pay' => $this->basicPay($profile, $payBasis, $serviceFraction, $detail->payable_days === null ? 0.0 : (float) $detail->payable_days),
            'total_earnings' => 0,
            'gross_pay' => 0,
            'total_deductions' => 0,
            'nthp_before_lbp' => 0,
            'lbp_loan_deduction' => $detail->lbp_loan_deduction ?? 0,
            'net_pay' => 0,
            'payroll_status' => $detail->input_confirmed_at ? 'COMPUTED' : 'INPUT_REQUIRED',
        ]);

        $detail->save();
        if (! $detail->manual_adjustments) {
            $detail->earnings()->delete();
            $detail->deductions()->delete();
            $this->seedEarnings($detail, $employment, $compensationDate);
            $this->seedDeductions($detail, $employment, $compensationDate);
        }
        $this->recomputeTotals($detail, $period);

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
            $this->recomputeEditableTotals($detail, $period);
        });
    }

    private function recomputeEditableTotals(PayrollDetail $detail, PayrollPeriod $period): void
    {
        $detail->load(['earnings', 'deductions', 'deductions.deductionType']);

        $basicPay = (float) $detail->basic_pay;
        $totalEarnings = round((float) $detail->earnings->sum('amount'), 2);
        $grossPay = round($basicPay + $totalEarnings, 2);
        $taxableGross = round($basicPay + (float) $detail->earnings->where('is_taxable', true)->sum('amount'), 2);

        $contributions = $this->computeContributions($basicPay, $period);
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
            $taxType = $this->requireDeductionType('Withholding Tax');
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
    private function computeContributions(float $basicPay, PayrollPeriod $period): array
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

            $type = $this->requireDeductionType($deductionName);

            $employee[] = ['type_id' => $type->deduction_type_id, 'label' => $deductionName, 'amount' => $employeeAmount];
            $employer[] = ['rate_id' => $rate->contribution_rate_id, 'label' => $contributionType.' (Employer)', 'amount' => $employerAmount];
        }

        return ['employee' => $employee, 'employer' => $employer];
    }

    private function computeWithholdingTax(float $taxableMonthlyGross, float $monthlyPreTaxContributions, PayrollPeriod $period): float
    {
        $annualTaxable = max(0.0, ($taxableMonthlyGross - $monthlyPreTaxContributions) * 12);

        $effectiveDate = TaxBracket::query()
            ->where('tax_type', 'ANNUAL')
            ->where('pay_frequency', 'ANNUAL')
            ->whereDate('effective_from', '<=', $period->end_date)
            ->where(fn ($query) => $query
                ->whereNull('effective_to')
                ->orWhereDate('effective_to', '>=', $period->end_date))
            ->max('effective_from');

        if ($effectiveDate === null) {
            return 0.0;
        }

        $bracket = TaxBracket::query()
            ->where('tax_type', 'ANNUAL')->where('pay_frequency', 'ANNUAL')
            ->where('effective_from', $effectiveDate)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->end_date))
            ->where('minimum_income', '<=', $annualTaxable)
            ->where(fn ($query) => $query
                ->whereNull('maximum_income')
                ->orWhere('maximum_income', '>=', $annualTaxable))
            ->orderByDesc('minimum_income')
            ->first();

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
            ->with('gradeStep')
            ->whereDate('effective_from', '<=', $date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $date))
            ->orderByDesc('effective_from')
            ->first();
    }

    private function effectiveContributionRate(string $contributionType, PayrollPeriod $period): ?ContributionRate
    {
        return ContributionRate::query()
            ->where('contribution_type', $contributionType)
            ->whereDate('effective_from', '<=', $period->end_date)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $period->end_date))
            ->orderByDesc('effective_from')
            ->first();
    }

    private function requireDeductionType(string $name): DeductionType
    {
        $type = DeductionType::query()->where('deduction_name', $name)->first();

        if ($type === null) {
            throw new RuntimeException("Required deduction type [{$name}] is not configured.");
        }

        return $type;
    }
}
