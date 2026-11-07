<?php

namespace App\Http\Controllers;

use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Payslip;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PayslipController extends Controller
{
    public function index(PayrollRun $payrollRun): View
    {
        $payrollRun->load(['payrollPeriod', 'details.payslip']);

        return view('payslips.index', [
            'run' => $payrollRun,
            'canGenerate' => in_array($payrollRun->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED], true),
        ]);
    }

    public function generate(PayrollRun $payrollRun): RedirectResponse
    {
        if (! in_array($payrollRun->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED], true)) {
            return back()->with('error', 'Payslips can only be generated for an approved or finalized payroll run.');
        }

        $generated = 0;

        foreach ($payrollRun->details()->with('payslip')->get() as $detail) {
            if ($detail->payslip !== null) {
                continue;
            }

            Payslip::create([
                'payroll_detail_id' => $detail->payroll_detail_id,
                'payslip_number' => $this->payslipNumber($payrollRun, $detail),
                'generated_at' => now(),
                'publication_status' => Payslip::STATUS_GENERATED,
            ]);

            $generated++;
        }

        return back()->with('status', "Generated {$generated} payslip(s).");
    }

    public function show(Payslip $payslip): View
    {
        $payslip->load([
            'payrollDetail.earnings',
            'payrollDetail.deductions',
            'payrollDetail.employerContributions',
            'payrollDetail.payrollRun.payrollPeriod',
        ]);

        return view('payslips.show', compact('payslip'));
    }

    public function publish(Payslip $payslip): RedirectResponse
    {
        $payslip->update([
            'publication_status' => Payslip::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return back()->with('status', 'Payslip published.');
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
