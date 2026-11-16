<?php

namespace App\Services;

use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayrollDuplicateGuard
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Serialize financial transitions across runs, including initially empty conflict sets.
     * Acquire the database mutex before run locks and retain it through commit.
     */
    public function transaction(PayrollRun $run, string $stage, Closure $operation, bool $serialize = true): mixed
    {
        try {
            return DB::transaction(function () use ($operation, $serialize): mixed {
                if ($serialize) {
                    DB::table('payroll_processing_locks')->where('id', 1)->lockForUpdate()->firstOrFail();
                }

                return $operation();
            }, 5);
        } catch (ValidationException $exception) {
            $this->audit->log('PAYROLL_VALIDATION_BLOCKED', 'Payroll', auth()->id(), 'payroll_runs', (string) $run->getKey(), newValues: [
                'stage' => $stage, 'errors' => $exception->errors(),
            ]);

            throw $exception;
        }
    }

    public function validate(PayrollRun $run, string $stage, bool $preparing = false): void
    {
        $period = $run->payrollPeriod()->sharedLock()->firstOrFail();
        $details = $run->details()->with(['earnings', 'employmentRecord', 'originalDetail.payrollRun.payrollPeriod'])->get();
        $conflicts = PayrollDetail::query()
            ->where('payroll_run_id', '!=', $run->getKey())
            ->whereIn('employee_id', $details->pluck('employee_id'))
            ->whereHas('payrollRun', fn ($query) => $query->whereIn('status', [
                PayrollRun::STATUS_PROCESSING, PayrollRun::STATUS_PENDING_APPROVAL,
                PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED,
            ])->whereHas('payrollPeriod', fn ($query) => $query
                ->whereDate('start_date', '<=', $period->end_date)->whereDate('end_date', '>=', $period->start_date)))
            ->with(['earnings', 'payrollRun.payrollPeriod'])->get();
        $detailsByEmployee = $conflicts->concat($details)->groupBy('employee_id');
        $errors = [];
        foreach ($details as $detail) {
            $issues = [];
            $start = $run->run_type === 'REGULAR' ? $period->start_date->toDateString() : ($detail->coverage_start?->toDateString() ?? $period->start_date->toDateString());
            $end = $run->run_type === 'REGULAR' ? $period->end_date->toDateString() : ($detail->coverage_end?->toDateString() ?? $period->end_date->toDateString());
            if ($start > $end || $start < $period->start_date->toDateString() || $end > $period->end_date->toDateString()) {
                $issues[] = 'Coverage must be ordered and contained in the payroll period.';
            }
            if ($detail->employmentRecord === null || (int) $detail->employmentRecord->employee_id !== (int) $detail->employee_id) {
                $issues[] = 'The employee and employment identity do not match. Correct employee inclusion.';
            }
            foreach ($detail->earnings->groupBy('earning_type_id')->filter(fn ($rows): bool => $rows->count() > 1) as $typeId => $rows) {
                $label = $rows->first()->earning_name_snapshot;
                $issues[] = "Duplicate {$label} (earning type #{$typeId}) in {$period->period_name}, run #{$run->getKey()}, coverage {$start} to {$end}. Combine or correct the repeated earning entries.";
            }
            if (! in_array($run->run_type, ['REGULAR', 'SUPPLEMENTAL', 'ADJUSTMENT'], true)) {
                $issues[] = 'Choose a supported payroll run type.';
            }
            if ($run->run_type !== 'REGULAR' && ! $preparing) {
                foreach (['coverage_start', 'coverage_end', 'compensation_reference', 'compensation_reason', 'accounting_basis'] as $field) {
                    if (blank($detail->getAttribute($field))) {
                        $issues[] = 'Enter '.str_replace('_', ' ', $field).' for this additional entitlement or correction.';
                    }
                }
                if (blank($run->purpose)) {
                    $issues[] = 'Document the purpose of this non-regular run.';
                }
                if ($this->components($detail) === []) {
                    $issues[] = 'Enter an explicit positive additional earning or adjustment amount. Automatic recoveries are unsupported.';
                }
            }
            if ($run->run_type === 'SUPPLEMENTAL' && (float) $detail->basic_pay !== 0.0) {
                $issues[] = 'Supplemental payroll must contain additional earnings only; remove regular basic salary.';
            }
            if ($run->run_type === 'ADJUSTMENT' && (! $preparing || $detail->original_payroll_detail_id !== null)) {
                $original = $detail->originalDetail;
                if ($original === null || $original->payrollRun->status !== PayrollRun::STATUS_FINALIZED
                    || (int) $original->employee_id !== (int) $detail->employee_id
                    || (int) $original->payroll_run_id === (int) $run->getKey()) {
                    $issues[] = 'Reference a finalized original payroll detail for this employee; original records must remain unchanged.';
                } elseif ($start < ($original->coverage_start?->toDateString() ?? $original->payrollRun->payrollPeriod->start_date->toDateString())
                    || $end > ($original->coverage_end?->toDateString() ?? $original->payrollRun->payrollPeriod->end_date->toDateString())) {
                    $issues[] = 'Adjustment coverage must fall within the referenced original payroll coverage.';
                }
            } elseif ($run->run_type !== 'ADJUSTMENT' && $detail->original_payroll_detail_id !== null) {
                $issues[] = 'An original payroll detail may be referenced only by an adjustment run.';
            }

            foreach ($detailsByEmployee->get($detail->employee_id, collect()) as $other) {
                if ((int) $other->getKey() === (int) $detail->getKey()) {
                    continue;
                }
                $otherRun = (int) $other->payroll_run_id === (int) $run->getKey() ? $run : $other->payrollRun;
                $otherPeriod = $otherRun->payrollPeriod;
                $otherStart = $otherRun->run_type === 'REGULAR' ? $otherPeriod->start_date->toDateString() : ($other->coverage_start?->toDateString() ?? $otherPeriod->start_date->toDateString());
                $otherEnd = $otherRun->run_type === 'REGULAR' ? $otherPeriod->end_date->toDateString() : ($other->coverage_end?->toDateString() ?? $otherPeriod->end_date->toDateString());
                if ($start > $otherEnd || $end < $otherStart) {
                    continue;
                }
                foreach (array_intersect_key($this->components($detail), $this->components($other)) as $component => $label) {
                    $sameReference = filled($detail->compensation_reference) && filled($other->compensation_reference)
                        && $this->reference($detail->compensation_reference) === $this->reference($other->compensation_reference);
                    $isOriginal = ((int) $detail->original_payroll_detail_id === (int) $other->getKey() && $run->run_type === 'ADJUSTMENT')
                        || ((int) $other->original_payroll_detail_id === (int) $detail->getKey() && $otherRun->run_type === 'ADJUSTMENT');
                    $additional = $run->run_type !== 'REGULAR' ? $detail : ($otherRun->run_type !== 'REGULAR' ? $other : null);
                    $authorizedAdditional = $additional !== null && filled($additional->compensation_reference)
                        && filled($additional->overlap_justification) && filled($additional->accounting_basis);
                    if (! $sameReference && ($isOriginal || $authorizedAdditional)) {
                        if (! $preparing) {
                            $this->audit->log('PAYROLL_DISTINCT_ENTITLEMENT', 'Payroll', auth()->id(), 'payroll_details', (string) $detail->getKey(), newValues: [
                                'stage' => $stage, 'conflicting_run_id' => $otherRun->getKey(), 'component' => $component,
                                'original_detail_id' => $detail->original_payroll_detail_id,
                                'reference' => $additional?->compensation_reference, 'justification' => $additional?->overlap_justification,
                            ]);
                        }

                        continue;
                    }
                    $issues[] = "Duplicate {$label}: coverage {$start} to {$end} overlaps {$otherPeriod->period_name} ({$otherStart} to {$otherEnd}), run #{$otherRun->getKey()} / {$otherRun->run_type} / {$otherRun->status}. Correct or remove this entitlement; pending or approved runs must be returned for correction. Separate additional entitlements require a distinct reference and documented overlap justification.";
                }
            }
            if ($issues !== []) {
                $errors['duplicates.'.$detail->getKey()] = $detail->employee_number_snapshot.' ('.$detail->employee_name_snapshot.'): '.implode(' ', array_unique($issues));
            }
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @return array<string, string> */
    private function components(PayrollDetail $detail): array
    {
        $components = (float) $detail->basic_pay > 0 ? ['basic' => 'basic salary'] : [];
        foreach ($detail->earnings as $earning) {
            if ((float) $earning->amount > 0) {
                $components['earning:'.$earning->earning_type_id] = $earning->earning_name_snapshot;
            }
        }

        return $components;
    }

    private function reference(string $reference): string
    {
        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($reference)) ?? $reference);
    }
}
