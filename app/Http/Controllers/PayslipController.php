<?php

namespace App\Http\Controllers;

use App\Jobs\GeneratePayslips;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Services\PayrollRegisterService;
use App\Services\PayslipGenerationService;
use App\Services\PayslipPublicationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
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

        $generated = $generation->generate($payrollRun, (int) auth()->id());

        return back()->with('status', "Generated {$generated} payslip(s).");
    }

    public function show(Payslip $payslip, PayrollRegisterService $register): Response
    {
        $runId = $payslip->payrollDetail()->value('payroll_run_id');

        return DB::transaction(function () use ($payslip, $register, $runId): Response {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($runId);
            $payslip = Payslip::query()->lockForUpdate()->findOrFail($payslip->getKey());
            abort_unless($payslip->isViewableFor($run), 422, 'This payslip is withdrawn or its payroll is awaiting approval. Generate a current payslip after approval.');
            $payslip->load([
                'payrollDetail.earnings',
                'payrollDetail.deductions',
                'payrollDetail.employerContributions',
            ]);
            $run->load('payrollPeriod');
            $payslip->payrollDetail->setRelation('payrollRun', $run);
            $isPublished = $run->status === PayrollRun::STATUS_FINALIZED && $payslip->publication_status === Payslip::STATUS_PUBLISHED;

            return response()->view('payslips.show', compact('payslip', 'register', 'isPublished'))
                ->header('Cache-Control', 'private, no-store, max-age=0');
        });
    }

    public function publish(Payslip $payslip, PayslipPublicationService $publication): RedirectResponse
    {
        $publication->publish($payslip, (int) auth()->id());

        return back()->with('status', 'Payslip published.');
    }
}
