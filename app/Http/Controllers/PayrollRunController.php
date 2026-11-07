<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollRunRequest;
use App\Models\PayrollEmployerContribution;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollRunController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString() ?: null;

        $runs = PayrollRun::query()
            ->with(['payrollPeriod', 'createdBy'])
            ->withCount('details')
            ->when($status !== null, fn ($query) => $query->where('status', $status))
            ->orderByDesc('payroll_run_id')
            ->paginate(15)
            ->withQueryString();

        return view('payroll-runs.index', compact('runs', 'status'));
    }

    public function create(): View
    {
        return view('payroll-runs.create', [
            'periods' => PayrollPeriod::orderByDesc('start_date')->get(),
        ]);
    }

    public function store(PayrollRunRequest $request): RedirectResponse
    {
        $periodId = $request->integer('payroll_period_id');
        $nextNumber = (int) PayrollRun::where('payroll_period_id', $periodId)->max('run_number') + 1;

        $run = PayrollRun::create([
            'payroll_period_id' => $periodId,
            'run_number' => $nextNumber,
            'run_type' => $request->string('run_type')->toString(),
            'status' => PayrollRun::STATUS_DRAFT,
            'created_by' => auth()->id(),
        ]);

        return redirect()
            ->route('payroll-runs.show', $run)
            ->with('status', 'Payroll run created. Compute it to generate payroll details.');
    }

    public function show(PayrollRun $payrollRun, PayrollApprovalService $approval): View
    {
        $payrollRun->load([
            'payrollPeriod',
            'createdBy',
            'submittedBy',
            'finalizedBy',
            'details.employmentRecord.position',
            'approvals.actedBy',
            'anchor.anchoredBy',
            'anchor.verificationLogs',
        ]);

        $detailIds = $payrollRun->details->pluck('payroll_detail_id');
        $user = auth()->user();

        return view('payroll-runs.show', [
            'run' => $payrollRun,
            'totalGross' => (float) $payrollRun->details->sum('gross_pay'),
            'totalDeductions' => (float) $payrollRun->details->sum('total_deductions'),
            'totalNet' => (float) $payrollRun->details->sum('net_pay'),
            'employerContributions' => (float) PayrollEmployerContribution::whereIn('payroll_detail_id', $detailIds)->sum('amount'),
            'canSubmit' => $user->can('payroll.submit') && $payrollRun->isEditable() && $payrollRun->details->isNotEmpty(),
            'canReview' => $user->can('payroll.approve')
                && $payrollRun->status === PayrollRun::STATUS_PENDING_APPROVAL
                && $approval->isIndependentReviewer($payrollRun, $user),
            'canFinalize' => $user->can('payroll.finalize') && $payrollRun->status === PayrollRun::STATUS_APPROVED,
        ]);
    }

    public function compute(PayrollRun $payrollRun, PayrollComputationService $service): RedirectResponse
    {
        if (! $payrollRun->isEditable()) {
            return back()->with('error', 'This payroll run can no longer be recomputed.');
        }

        $service->computeRun($payrollRun);

        return redirect()
            ->route('payroll-runs.show', $payrollRun)
            ->with('status', 'Payroll computed for '.$payrollRun->details()->count().' employee(s).');
    }

    public function destroy(PayrollRun $payrollRun): RedirectResponse
    {
        if (! $payrollRun->isEditable()) {
            return back()->with('error', 'Only draft, returned, or rejected runs can be deleted.');
        }

        $payrollRun->delete();

        return redirect()->route('payroll-runs.index')->with('status', 'Payroll run deleted.');
    }
}
