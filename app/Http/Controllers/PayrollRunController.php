<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollRunRequest;
use App\Models\EmploymentRecord;
use App\Models\PayrollEmployerContribution;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Services\AuditLogger;
use App\Services\PayrollApprovalService;
use App\Services\PayrollAuditSnapshot;
use App\Services\PayrollCarryForwardPolicy;
use App\Services\PayrollComputationService;
use App\Services\PayrollDuplicateGuard;
use App\Services\PayrollEligibilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollRunController extends Controller
{
    public function __construct(
        private readonly PayrollDuplicateGuard $duplicates,
        private readonly PayrollAuditSnapshot $auditSnapshots,
        private readonly PayrollCarryForwardPolicy $carryForward,
    ) {}

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
            'replacements' => PayrollRun::where('run_type', 'REGULAR')->whereIn('status', [PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_REJECTED])->with('payrollPeriod')->get(),
        ]);
    }

    public function store(PayrollRunRequest $request): RedirectResponse
    {
        $periodId = $request->integer('payroll_period_id');
        $run = $this->duplicates->transaction(new PayrollRun, 'creation', function () use ($request, $periodId): PayrollRun {
            $period = PayrollPeriod::whereKey($periodId)->lockForUpdate()->firstOrFail();
            if ($period->status !== 'OPEN') {
                throw ValidationException::withMessages(['payroll_period_id' => 'Choose an open payroll period.']);
            }
            $nextNumber = (int) PayrollRun::where('payroll_period_id', $periodId)->max('run_number') + 1;

            $replacement = null;
            if ($request->filled('replaces_payroll_run_id')) {
                $replacement = PayrollRun::whereKey($request->integer('replaces_payroll_run_id'))->lockForUpdate()->firstOrFail();
                if ($replacement->run_type !== 'REGULAR' || $request->validated('run_type') !== 'REGULAR'
                    || (int) $replacement->payroll_period_id !== $periodId
                    || ! in_array($replacement->status, [PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_REJECTED], true)
                    || ! $replacement->approvals()->whereIn('action', ['RETURNED', 'REJECTED'])->exists()) {
                    throw ValidationException::withMessages(['replaces_payroll_run_id' => 'Replace only a regular run in the same period after an independent reviewer has returned or rejected it. Finalized runs must be corrected using an adjustment.']);
                }
                $replacement->update(['status' => PayrollRun::STATUS_CANCELLED]);
            }

            $run = PayrollRun::create([
                'payroll_period_id' => $periodId, 'run_number' => $nextNumber,
                'run_type' => $request->validated('run_type'), 'status' => PayrollRun::STATUS_DRAFT, 'created_by' => auth()->id(),
                'purpose' => $request->validated('purpose'), 'replaces_payroll_run_id' => $replacement?->getKey(),
            ]);
            $previous = $replacement ?? ($run->run_type === 'REGULAR' ? PayrollRun::query()
                ->where('status', PayrollRun::STATUS_FINALIZED)
                ->where('run_type', $run->run_type)
                ->whereHas('payrollPeriod', fn ($query) => $query
                    ->where('payroll_type', $period->payroll_type)
                    ->where('end_date', '<', $period->start_date))
                ->orderByDesc('finalized_at')
                ->orderByDesc('payroll_run_id')
                ->with(['details.earnings.earningType', 'details.deductions.deductionType', 'details.employerContributions', 'details.employmentRecord.employee', 'details.employmentRecord.earningAssignments', 'details.employmentRecord.deductionAssignments'])
                ->first() : null);

            foreach ($previous?->details ?? [] as $detail) {
                $employment = $detail->employmentRecord;
                $eligibility = app(PayrollEligibilityService::class);
                if ($employment === null || $employment->employee_id !== $detail->employee_id || $eligibility->requiresException($employment) || ! $eligibility->overlaps($employment, $period)) {
                    continue;
                }
                $copy = $detail->replicate(['computation_fingerprint', 'coverage_start', 'coverage_end', 'compensation_reference', 'compensation_reason', 'accounting_basis', 'overlap_justification', 'original_payroll_detail_id']);
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

                $rows = $replacement === null ? $this->carryForward->rows($detail, $period) : [
                    'earnings' => $detail->earnings, 'deductions' => $detail->deductions,
                ];
                $rows['employerContributions'] = $detail->employerContributions;
                foreach ($rows as $relation => $entries) {
                    foreach ($entries as $row) {
                        $copy->{$relation}()->save($row->replicate());
                    }
                }
            }
            app(AuditLogger::class)->log('PAYROLL_CREATED', 'Payroll', (int) auth()->id(), 'payroll_runs', (string) $run->getKey(), newValues: [
                ...$run->getAttributes(), 'details' => $this->auditSnapshots->run($run),
                'audit_context' => app(AuditLogger::class)->context('Created a payroll run with provisional source inputs.'),
            ]);
            if ($replacement !== null) {
                app(AuditLogger::class)->log('PAYROLL_REPLACED', 'Payroll', (int) auth()->id(), 'payroll_runs', (string) $replacement->getKey(), newValues: [
                    'replacement_run_id' => $run->getKey(), 'reason' => $request->validated('replacement_reason'), 'status' => PayrollRun::STATUS_CANCELLED,
                ]);
            }

            return $run;
        });

        return redirect()
            ->route('payroll-runs.show', $run)
            ->with('status', $run->details()->exists()
                ? 'Payroll run created with provisional source inputs. Confirm and recompute every employee for this coverage period before submitting.'
                : ($run->run_type === 'REGULAR' ? 'Payroll run created. Compute it to generate payroll details.' : 'Payroll run created. Include employees explicitly, then enter additional amounts, coverage, references, and accounting authorization.'));
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
        $hasAccountingPolicy = app(PayrollComputationService::class)->hasAccountingPolicy($payrollRun);
        $independent = $approval->isIndependentReviewer($payrollRun, $user);
        $pendingReview = $payrollRun->status === PayrollRun::STATUS_PENDING_APPROVAL && $independent;
        $canReturn = $independent && $user->can('payroll.review')
            && in_array($payrollRun->status, [PayrollRun::STATUS_PENDING_APPROVAL, PayrollRun::STATUS_APPROVED], true);
        $eligibilityIssues = $payrollRun->isEditable()
            ? $payrollRun->details->mapWithKeys(fn ($detail): array => [$detail->getKey() => $eligibility->issue($detail, $payrollRun->payrollPeriod)])->filter()
            : collect();

        return view('payroll-runs.show', [
            'run' => $payrollRun,
            'accountingPolicyRequired' => ! $hasAccountingPolicy,
            'eligibilityIssues' => $eligibilityIssues,
            'exceptionEmployments' => $payrollRun->isEditable() && $user->can('payroll.prepare')
                ? EmploymentRecord::with('employee')->whereNotIn('employment_id', $payrollRun->details->pluck('employment_id'))
                    ->whereDate('start_date', '<=', $payrollRun->payrollPeriod->end_date)
                    ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $payrollRun->payrollPeriod->start_date))
                    ->when($payrollRun->run_type === 'REGULAR', fn ($query) => $query->where(fn ($query) => $query->where('employment_status', '!=', EmploymentRecord::STATUS_ACTIVE)->orWhereHas('employee', fn ($query) => $query->whereNotNull('employees.deleted_at'))))
                    ->orderBy('employment_id')->get()
                : collect(),
            'totalGross' => (float) $payrollRun->details->sum('gross_pay'),
            'totalDeductions' => (float) $payrollRun->details->sum('total_deductions'),
            'totalNet' => (float) $payrollRun->details->sum('net_pay'),
            'employerContributions' => (float) PayrollEmployerContribution::whereIn('payroll_detail_id', $detailIds)->sum('amount'),
            'canSubmit' => $hasAccountingPolicy && $user->can('payroll.submit') && $payrollRun->isEditable() && $payrollRun->details->isNotEmpty()
                && $eligibilityIssues->isEmpty()
                && $payrollRun->details->every(fn ($detail): bool => $detail->payable_days !== null && $detail->input_confirmed_at !== null && $detail->payroll_status === 'COMPUTED'),
            'isIndependentReviewer' => $independent,
            'canApprove' => $hasAccountingPolicy && $pendingReview && $user->can('payroll.approve'),
            'canReject' => $pendingReview && $user->can('payroll.reject'),
            'canReturn' => $canReturn,
            'canReview' => $canReturn || ($pendingReview && ($user->can('payroll.approve') || $user->can('payroll.reject'))),
            'canFinalize' => $hasAccountingPolicy && $user->can('payroll.finalize') && $payrollRun->status === PayrollRun::STATUS_APPROVED,
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
            $before = [...$locked->getAttributes(), 'details' => $this->auditSnapshots->run($locked)];
            $locked->delete();
            app(AuditLogger::class)->log('PAYROLL_DELETED', 'Payroll', (int) auth()->id(), 'payroll_runs', (string) $locked->getKey(), $before, [
                'audit_context' => app(AuditLogger::class)->context('Deleted an editable payroll run and its financial details.'),
            ]);
        });

        return redirect()->route('payroll-runs.index')->with('status', 'Payroll run deleted.');
    }
}
