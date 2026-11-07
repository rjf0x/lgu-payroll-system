<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollDecisionRequest;
use App\Models\PayrollRun;
use App\Services\PayrollApprovalService;
use Illuminate\Http\RedirectResponse;

class PayrollApprovalController extends Controller
{
    public function submit(PayrollRun $payrollRun, PayrollApprovalService $service): RedirectResponse
    {
        $service->submit($payrollRun, auth()->user());

        return back()->with('status', 'Payroll submitted for independent approval.');
    }

    public function approve(PayrollDecisionRequest $request, PayrollRun $payrollRun, PayrollApprovalService $service): RedirectResponse
    {
        $service->approve($payrollRun, auth()->user(), $request->validated('remarks'));

        return back()->with('status', 'Payroll approved.');
    }

    public function reject(PayrollDecisionRequest $request, PayrollRun $payrollRun, PayrollApprovalService $service): RedirectResponse
    {
        $service->reject($payrollRun, auth()->user(), $request->validated('remarks'));

        return back()->with('status', 'Payroll rejected.');
    }

    public function returnForCorrection(PayrollDecisionRequest $request, PayrollRun $payrollRun, PayrollApprovalService $service): RedirectResponse
    {
        $service->returnForCorrection($payrollRun, auth()->user(), $request->validated('remarks'));

        return back()->with('status', 'Payroll returned for correction.');
    }

    public function finalize(PayrollRun $payrollRun, PayrollApprovalService $service): RedirectResponse
    {
        $service->finalize($payrollRun, auth()->user());

        return back()->with('status', 'Payroll finalized.');
    }
}
