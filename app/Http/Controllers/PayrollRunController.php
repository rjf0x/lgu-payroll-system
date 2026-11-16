<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollRunRequest;
use App\Models\EmploymentRecord;
use App\Models\PayrollEmployerContribution;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\AuditLogger;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use App\Services\PayrollEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
            ->when($request->filled('payroll_period_id'), fn ($query) => $query->where('payroll_period_id', $request->integer('payroll_period_id')))
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
        $run = DB::transaction(function () use ($request, $periodId): PayrollRun {
            $period = PayrollPeriod::whereKey($periodId)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'OPEN') {
                throw ValidationException::withMessages(['payroll_period_id' => 'Choose an open payroll period.']);
            }
            $nextNumber = (int) PayrollRun::where('payroll_period_id', $periodId)->max('run_number') + 1;

            $run = PayrollRun::create([
                'payroll_period_id' => $periodId, 'run_number' => $nextNumber,
                'run_type' => $request->validated('run_type'), 'status' => PayrollRun::STATUS_DRAFT, 'created_by' => auth()->id(),
            ]);
            $previous = PayrollRun::query()
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->where('run_type', $run->run_type)
                ->whereHas('payrollPeriod', fn ($query) => $query
                    ->where('payroll_type', $period->payroll_type)
                    ->where('start_date', '<=', $period->start_date))
                ->orderByDesc('finalized_at')
                ->orderByDesc('payroll_run_id')
                ->with(['details.earnings', 'details.deductions', 'details.employerContributions', 'details.employmentRecord.employee'])
                ->first();

            foreach ($previous?->details ?? [] as $detail) {
                $employment = $detail->employmentRecord;
                $eligibility = app(PayrollEligibilityService::class);
                if ($employment === null || $employment->employee_id !== $detail->employee_id || $eligibility->requiresException($employment) || ! $eligibility->overlaps($employment, $period)) {
                    continue;
                }
                $copy = $detail->replicate();
                $copy->setRelations([]);
                $copy->fill([
                    'payroll_run_id' => $run->getKey(),
                    'manual_adjustments' => true,
                    'payroll_status' => 'INPUT_REQUIRED',
                    'input_confirmed_at' => null,
                    'input_recorded_by' => null,
                    'eligibility_reason' => null,
                    'eligibility_resolved_by' => null,
                    'eligibility_resolved_at' => null,
                    'eligibility_context' => null,
                ]);
                $copy->save();

                foreach (['earnings', 'deductions', 'employerContributions'] as $relation) {
                    foreach ($detail->{$relation} as $row) {
                        $copy->{$relation}()->save($row->replicate());
                    }
                }
            }
            app(AuditLogger::class)->log('PAYROLL_CREATED', 'Payroll', (int) auth()->id(), 'payroll_runs', (string) $run->getKey());

            return $run;
        });

        return redirect()
            ->route('payroll-runs.show', $run)
            ->with('status', $run->details()->exists()
                ? 'Payroll run created with provisional inputs from the previous finalized run. Confirm and recompute every employee for this coverage period before submitting.'
                : 'Payroll run created. Compute it to generate payroll details.');
    }

    public function show(PayrollRun $payrollRun, PayrollApprovalService $approval, PayrollEligibilityService $eligibility): View
    {
        $payrollRun->load([
            'payrollPeriod',
            'createdBy',
            'submittedBy',
            'finalizedBy',
            'details.employmentRecord.position',
            'details.employmentRecord.employee',
            'approvals.actedBy',
            'anchor.anchoredBy',
            'anchor.verificationLogs',
            'registration.latestVerification',
        ]);

        $detailIds = $payrollRun->details->pluck('payroll_detail_id');
        $user = auth()->user();
        $independent = $approval->isIndependentReviewer($payrollRun, $user);
        $pendingReview = $payrollRun->status === PayrollRun::STATUS_PENDING_APPROVAL && $independent;
        $canReturn = $independent && $user->can('payroll.review')
            && in_array($payrollRun->status, [PayrollRun::STATUS_PENDING_APPROVAL, PayrollRun::STATUS_APPROVED], true);
        $eligibilityIssues = $payrollRun->isEditable()
            ? $payrollRun->details->mapWithKeys(fn ($detail): array => [$detail->getKey() => $eligibility->issue($detail, $payrollRun->payrollPeriod)])->filter()
            : collect();

        return view('payroll-runs.show', [
            'run' => $payrollRun,
            'eligibilityIssues' => $eligibilityIssues,
            'exceptionEmployments' => $payrollRun->isEditable() && $user->can('payroll.prepare')
                ? EmploymentRecord::with('employee')->whereNotIn('employment_id', $payrollRun->details->pluck('employment_id'))
                    ->whereDate('start_date', '<=', $payrollRun->payrollPeriod->end_date)
                    ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $payrollRun->payrollPeriod->start_date))
                    ->where(fn ($query) => $query->where('employment_status', '!=', EmploymentRecord::STATUS_ACTIVE)->orWhereHas('employee', fn ($query) => $query->whereNotNull('employees.deleted_at')))
                    ->orderBy('employment_id')->get()
                : collect(),
            'totalGross' => (float) $payrollRun->details->sum('gross_pay'),
            'totalDeductions' => (float) $payrollRun->details->sum('total_deductions'),
            'totalNet' => (float) $payrollRun->details->sum('net_pay'),
            'employerContributions' => (float) PayrollEmployerContribution::whereIn('payroll_detail_id', $detailIds)->sum('amount'),
            'canSubmit' => $user->can('payroll.submit') && $payrollRun->isEditable() && $payrollRun->details->isNotEmpty()
                && $eligibilityIssues->isEmpty()
                && $payrollRun->details->every(fn ($detail): bool => $detail->payable_days !== null && $detail->input_confirmed_at !== null && $detail->payroll_status === 'COMPUTED'),
            'isIndependentReviewer' => $independent,
            'canApprove' => $pendingReview && $user->can('payroll.approve'),
            'canReject' => $pendingReview && $user->can('payroll.reject'),
            'canReturn' => $canReturn,
            'canReview' => $canReturn || ($pendingReview && ($user->can('payroll.approve') || $user->can('payroll.reject'))),
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

        DB::transaction(function () use ($payrollRun): void {
            $locked = PayrollRun::whereKey($payrollRun->getKey())->lockForUpdate()->firstOrFail();
            abort_unless($locked->isEditable(), 422, 'Only editable payroll runs can be deleted.');
            $locked->delete();
            app(AuditLogger::class)->log('PAYROLL_DELETED', 'Payroll', (int) auth()->id(), 'payroll_runs', (string) $locked->getKey());
        });

        return redirect()->route('payroll-runs.index')->with('status', 'Payroll run deleted.');
    }
}
