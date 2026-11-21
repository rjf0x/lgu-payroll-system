<?php

namespace App\Services;

use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use Illuminate\Database\Eloquent\Model;

class PayrollAuditSnapshot
{
    /** @return array<string, mixed> */
    public function detail(PayrollDetail $detail): array
    {
        return $this->values($detail->fresh(['earnings', 'deductions', 'employerContributions']));
    }

    /** @return list<array<string, mixed>> */
    public function run(PayrollRun $run): array
    {
        return $run->details()->with(['earnings', 'deductions', 'employerContributions'])
            ->orderBy('payroll_detail_id')->get()->map($this->values(...))->all();
    }

    /** @return array<string, mixed> */
    private function values(PayrollDetail $detail): array
    {
        $values = $detail->getAttributes();
        foreach (['earnings', 'deductions', 'employerContributions'] as $relation) {
            $values[$relation] = $detail->{$relation}->sortBy(fn (Model $row): int => (int) $row->getKey())
                ->map(fn (Model $row): array => $row->getAttributes())->values()->all();
        }

        return $values;
    }
}
