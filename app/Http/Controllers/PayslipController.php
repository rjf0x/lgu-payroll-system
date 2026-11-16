<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayslips;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollRegisterService;
use App\Services\PayslipGenerationService;
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

    public function generate(PayrollRun $payrollRun, PayslipGenerationService $generation): RedirectResponse
    {
        if (! in_array($payrollRun->status, [PayrollRun::STATUS_APPROVED, PayrollRun::STATUS_FINALIZED], true)) {
            return back()->with('error', 'Payslips can only be generated for an approved or finalized payroll run.');
        }

        if (config('deployment.queue_payslips')) {
            GeneratePayslips::dispatch((int) $payrollRun->getKey(), (int) auth()->id())->onQueue('reports')->afterCommit();

            return back()->with('status', 'Payslip generation queued. Refresh this page after the report worker finishes.');
        }

        $generated = $generation->generate($payrollRun);

        return back()->with('status', "Generated {$generated} payslip(s).");
    }

    public function show(Payslip $payslip, PayrollRegisterService $register): View
    {
        $payslip->load([
            'payrollDetail.earnings',
            'payrollDetail.deductions',
            'payrollDetail.employerContributions',
            'payrollDetail.payrollRun.payrollPeriod',
        ]);

        return view('payslips.show', compact('payslip', 'register'));
    }

    public function publish(Payslip $payslip): RedirectResponse
    {
        $payslip->update([
            'publication_status' => Payslip::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        return back()->with('status', 'Payslip published.');
    }
}
