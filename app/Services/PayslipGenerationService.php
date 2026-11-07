<?php

namespace App\Services;

use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Support\Facades\DB;

class PayslipGenerationService
{
    public function generate(PayrollRun $run): int
    {
        return DB::transaction(function () use ($run): int {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($run->getKey());
            abort_unless(in_array($run->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED], true), 422, 'Payslips require an approved or finalized run.');
            $run->load('payrollPeriod');
            $generated = 0;

            foreach ($run->details()->with('payslip')->get() as $detail) {
                if ($detail->payslip !== null) {
                    continue;
                }

                Payslip::create([
                    'payroll_detail_id' => $detail->payroll_detail_id,
                    'payslip_number' => $this->payslipNumber($run, $detail),
                    'generated_at' => now(),
                    'publication_status' => Payslip::STATUS_GENERATED,
                ]);
                $generated++;
            }

            return $generated;
        });
    }

    private function payslipNumber(PayrollRun $run, PayrollDetail $detail): string
    {
        $base = 'PS-'.$run->payrollPeriod->start_date->format('Ym').'-'.$detail->employee_number_snapshot;

        if (Payslip::where('payslip_number', $base)->exists()) {
            $base .= '-'.$detail->payroll_detail_id;
        }

        return $base;
    }
}
