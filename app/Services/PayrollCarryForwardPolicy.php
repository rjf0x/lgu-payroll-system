<?php

namespace App\Services;

use App\Models\EmployeeDeductionAssignment;
use App\Models\EmployeeEarningAssignment;
use App\Models\PayrollDeduction;
use App\Models\PayrollDetail;
use App\Models\PayrollEarning;
use App\Models\PayrollPeriod;
use Illuminate\Support\Collection;

class PayrollCarryForwardPolicy
{
    /**
     * Same-period replacements preserve reviewed inputs. New periods may carry only
     * recurring, active sources; copied values remain provisional until confirmed.
     *
     * @return array{earnings: Collection<int, PayrollEarning>, deductions: Collection<int, PayrollDeduction>}
     */
    public function rows(PayrollDetail $source, PayrollPeriod $period): array
    {
        $source->loadMissing(['earnings.earningType', 'deductions.deductionType', 'employmentRecord.earningAssignments', 'employmentRecord.deductionAssignments']);
        $employment = $source->employmentRecord;
        $date = $employment->end_date !== null && $employment->end_date->lt($period->end_date)
            ? $employment->end_date->toDateString()
            : $period->end_date->toDateString();

        return [
            'earnings' => $source->earnings->filter(function (PayrollEarning $row) use ($employment, $date): bool {
                $type = $row->earningType;
                if ($type === null || $type->status !== 'ACTIVE' || ! $type->is_recurring) {
                    return false;
                }
                $assignments = $employment->earningAssignments->where('earning_type_id', $row->earning_type_id);

                return $assignments->isEmpty() || $assignments->contains(fn (EmployeeEarningAssignment $assignment): bool => $assignment->frequency === 'MONTHLY' && $this->effective($assignment, $date));
            }),
            'deductions' => $source->deductions->filter(function (PayrollDeduction $row) use ($employment, $date): bool {
                $type = $row->deductionType;
                if ($type === null || $type->status !== 'ACTIVE' || $type->deduction_category === 'LBP') {
                    return false;
                }
                if ($type->is_statutory) {
                    return true;
                }
                $assignments = $employment->deductionAssignments->where('deduction_type_id', $row->deduction_type_id);

                return $assignments->isEmpty() || $assignments->contains(fn (EmployeeDeductionAssignment $assignment): bool => $assignment->status === 'ACTIVE' && $this->effective($assignment, $date));
            }),
        ];
    }

    private function effective(EmployeeEarningAssignment|EmployeeDeductionAssignment $assignment, string $date): bool
    {
        return $assignment->effective_from->toDateString() <= $date
            && ($assignment->effective_to === null || $assignment->effective_to->toDateString() >= $date);
    }
}
