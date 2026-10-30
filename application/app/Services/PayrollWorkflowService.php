<?php

namespace App\Services;

use App\Http\Requests\PayrollWorkflowRequest;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollFinalization;
use App\Models\PayrollInputRevision;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorkflowEvent;
use App\Models\StatutoryPreview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PayrollWorkflowService
{
    public function latestSubmission(PayrollBatch $batch): ?PayrollSubmission
    {
        return PayrollSubmission::where('payroll_batch_id', $batch->id)->orderByDesc('revision')->first();
    }

    /** A local stale-form token; this is not a blockchain commitment. */
    public function reviewToken(array $snapshot): string
    {
        $sort = function (array $value) use (&$sort): array {
            foreach ($value as &$member) {
                if (is_array($member)) {
                    $member = $sort($member);
                }
            }
            unset($member);
            if (! array_is_list($value)) {
                ksort($value);
            }

            return $value;
        };

        return hash('sha256', json_encode($sort($snapshot), JSON_THROW_ON_ERROR));
    }

    public function currentSnapshot(PayrollBatch $batch, bool $lock = false, ?PayrollCalculationRun $run = null): array
    {
        $fail = fn (string $message) => throw ValidationException::withMessages(['review' => $message]);
        if (! $batch->is_demonstration) {
            $fail('Real payroll review/finalization is blocked until client-approved rules and examples are validated.');
        }
        $run ??= PayrollCalculationRun::where('payroll_batch_id', $batch->id)
            ->where('batch_version', $batch->record_version)->where('engine_version', PayrollCalculator::VERSION)->first();
        if (! $run || $run->payroll_batch_id !== $batch->id || $run->engine_version !== PayrollCalculator::VERSION) {
            $fail('Calculate a current core preview before submitting payroll.');
        }
        $entriesQuery = $batch->entries()->orderBy('employee_id');
        $entries = ($lock ? $entriesQuery->lockForUpdate() : $entriesQuery)->get();
        $items = $run->items()->orderBy('employee_id')->get();
        if ($entries->isEmpty() || $entries->pluck('employee_id')->all() !== $items->pluck('employee_id')->all()) {
            $fail('Employee coverage changed. Review inputs and calculate a current preview.');
        }
        $employees = $lock ? Employee::whereIn('id', $entries->pluck('employee_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id') : null;
        $rows = [];
        $totals = ['total_earnings' => '0.00', 'total_deductions' => '0.00', 'net_pay' => '0.00'];
        foreach ($items as $item) {
            $entry = $entries->firstWhere('employee_id', $item->employee_id);
            // Lock sources in the same batch/employee/source order as preparation.
            if ($lock) {
                $employee = $employees->get($entry->employee_id);
                abort_unless($employee, 404);
                $entry->setRelation('employee', $employee);
                $preparation = app(PayrollPreparationService::class);
                $source = $preparation->source($employee, $batch, true);
                if ($source['issue']) {
                    $fail($entry->employee_number.': '.$source['issue']);
                }
                $context = app(PayrollInputService::class)->context($entry);
                foreach ($preparation->snapshot($employee, $source['record']) as $field => $value) {
                    if ((string) $context[$field] !== (string) $value) {
                        $fail($entry->employee_number.': Employee or salary details changed. Return and refresh the saved snapshot before resubmitting.');
                    }
                }
            }
            if ($issue = app(PayrollCalculationService::class)->itemIssue($item, $batch)) {
                $fail($entry->employee_number.': '.$issue);
            }
            $profile = app(PayrollRuleVersionService::class)->profile(
                $item->entry_snapshot['personnel_classification'], $batch->period_start->format('Y-m-d'), true, $lock, $item->entry_snapshot['office'],
            );
            $query = StatutoryPreview::where('payroll_calculation_item_id', $item->id)->orderByDesc('revision');
            $statutory = ($lock ? $query->lockForUpdate() : $query)->first();
            if (! $statutory || $statutory->result['status'] !== 'demonstration'
                || $statutory->result['engine'] !== StatutoryCalculator::VERSION
                || $this->reviewToken($statutory->parameters) !== $this->reviewToken($profile)) {
                $fail($entry->employee_number.': Save a complete statutory reference review using the currently applicable configuration.');
            }
            $result = $statutory->result;
            if (bccomp($result['net_before_tax_refund'], '0.00', 2) < 0
                || bccomp($result['tax']['credit_or_refund_due'], '0.00', 2) > 0) {
                $fail($entry->employee_number.': Negative net pay or tax credits/refunds require a validated handling policy before submission.');
            }
            $totals['total_earnings'] = bcadd($totals['total_earnings'], $item->breakdown['totals']['total_earnings'], 2);
            $totals['total_deductions'] = bcadd($totals['total_deductions'], $result['total_deductions'], 2);
            $totals['net_pay'] = bcadd($totals['net_pay'], $result['net_before_tax_refund'], 2);
            $rows[] = [
                'calculation_item_id' => $item->id, 'employee' => $item->entry_snapshot,
                'input_revision_id' => $item->payroll_input_revision_id, 'inputs' => $item->input_snapshot,
                'core_breakdown' => $item->breakdown, 'statutory_preview_id' => $statutory->id,
                'statutory_revision' => $statutory->revision, 'statutory_inputs' => $statutory->inputs,
                'parameters' => $statutory->parameters, 'statutory_result' => $result,
            ];
        }

        $snapshot = [
            'schema' => 'payroll-review-v1', 'payroll_batch_id' => $batch->id,
            'period' => $batch->period_start->format('Y-m'), 'is_demonstration' => true, 'production_ready' => false,
            'calculation_run_id' => $run->id, 'core_engine' => $run->engine_version,
            'core_totals' => $run->totals, 'totals' => $totals, 'employees' => $rows,
        ];
        if ($batch->payroll_version > 1) {
            $snapshot['correction'] = app(PayrollCorrectionService::class)->context($batch);
        }

        return $snapshot;
    }

    public function isIndependent(User $actor, PayrollBatch $batch, PayrollSubmission $submission): bool
    {
        if ($submission->payroll_batch_id !== $batch->id) {
            return false;
        }
        $prepared = $actor->id === $batch->created_by || $actor->id === $submission->created_by
            || in_array($actor->id, $batch->coverage_preparer_ids ?? [], true)
            || PayrollInputRevision::where('payroll_batch_id', $batch->id)->where('created_by', $actor->id)->exists()
            || PayrollCalculationRun::where('payroll_batch_id', $batch->id)->where('created_by', $actor->id)->exists()
            || StatutoryPreview::where('created_by', $actor->id)->whereIn('payroll_calculation_item_id', function ($query) use ($batch) {
                $query->select('payroll_calculation_items.id')->from('payroll_calculation_items')
                    ->join('payroll_calculation_runs', 'payroll_calculation_runs.id', '=', 'payroll_calculation_items.payroll_calculation_run_id')
                    ->where('payroll_calculation_runs.payroll_batch_id', $batch->id);
            })->exists();

        return ! $prepared;
    }

    private function ensureIndependent(User $actor, PayrollBatch $batch, PayrollSubmission $submission): void
    {
        abort_unless($this->isIndependent($actor, $batch, $submission), 403, 'A payroll preparer or submitter cannot review or finalize their own payroll, including after a role change.');
    }

    public function transition(Request $request, PayrollBatch $batch, string $action, array $data): void
    {
        DB::transaction(function () use ($request, $batch, $action, $data) {
            $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
            $current = PayrollBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            $ability = match ($action) {
                'submit' => 'submit', 'approve', 'return' => 'review', 'finalize' => 'finalize', default => abort(404),
            };
            Gate::forUser($actor)->authorize($ability, $current);
            $data = Validator::make($data, PayrollWorkflowRequest::rulesFor($action))->validate();
            if ($current->record_version !== (int) $data['record_version']) {
                throw ValidationException::withMessages(['record_version' => 'Payroll changed. Reload the review before acting.']);
            }
            if (! $current->is_demonstration) {
                throw ValidationException::withMessages(['review' => 'Real payroll review/finalization remains blocked pending client validation.']);
            }
            $submission = $this->latestSubmission($current);
            if ($action === 'submit') {
                $snapshot = $this->currentSnapshot($current, true);
                if (! hash_equals($this->reviewToken($snapshot), $data['review_token'])) {
                    throw ValidationException::withMessages(['review' => 'Review values changed. Reload and review the current employee amounts before submitting.']);
                }
                $submission = PayrollSubmission::forceCreate([
                    'payroll_batch_id' => $current->id, 'revision' => ($submission?->revision ?? 0) + 1,
                    'batch_version' => $current->record_version, 'payroll_calculation_run_id' => $snapshot['calculation_run_id'],
                    'snapshot' => $snapshot, 'created_by' => $actor->id,
                ]);
            } else {
                abort_unless($submission && $submission->id === (int) $data['submission_id'], 409, 'Reload the current submission.');
                $this->ensureIndependent($actor, $current, $submission);
                if ($action === 'finalize' && ! PayrollWorkflowEvent::where('payroll_submission_id', $submission->id)->where('action', 'approve')->exists()) {
                    throw ValidationException::withMessages(['review' => 'A retained approval of this submission is required before finalization.']);
                }
                if ($action !== 'return') {
                    $run = PayrollCalculationRun::findOrFail($submission->payroll_calculation_run_id);
                    $snapshot = $this->currentSnapshot($current, true, $run);
                    if ($this->reviewToken($snapshot) !== $this->reviewToken($submission->snapshot)) {
                        throw ValidationException::withMessages(['review' => 'Submitted values or configuration changed. Return payroll for correction and resubmission.']);
                    }
                }
            }
            if ($action === 'approve' && $current->status !== 'submitted') {
                throw ValidationException::withMessages(['review' => 'Only a submitted payroll can be approved.']);
            }
            $from = $current->status;
            $current->status = match ($action) {
                'submit' => 'submitted', 'return' => 'returned', 'approve' => 'approved', 'finalize' => 'finalized',
            };
            $current->record_version++;
            $current->updated_by = $actor->id;
            $current->save();
            PayrollWorkflowEvent::forceCreate([
                'payroll_batch_id' => $current->id, 'payroll_submission_id' => $submission->id,
                'batch_version' => $current->record_version, 'action' => $action,
                'from_status' => $from, 'to_status' => $current->status, 'created_by' => $actor->id,
                'actor_username' => $actor->username, 'reason' => $data['reason'],
            ]);
            if ($action === 'finalize') {
                $events = PayrollWorkflowEvent::where('payroll_submission_id', $submission->id)->orderBy('id')->get();
                $finalization = PayrollFinalization::forceCreate([
                    'payroll_batch_id' => $current->id, 'payroll_submission_id' => $submission->id, 'version' => $current->payroll_version,
                    'snapshot' => [
                        'schema' => 'finalized-payroll-v1', 'version' => $current->payroll_version, 'payroll' => $submission->snapshot,
                        'submission_id' => $submission->id, 'submission_revision' => $submission->revision,
                        'submitted_by' => $submission->created_by, 'submitted_at' => $submission->created_at->toISOString(),
                        'workflow' => $events->map(fn ($event) => [
                            'action' => $event->action, 'actor_id' => $event->created_by, 'actor_username' => $event->actor_username,
                            'reason' => $event->reason, 'at' => $event->created_at->toISOString(),
                        ])->all(),
                    ], 'created_by' => $actor->id,
                ]);
                app(PayrollSnapshotService::class)->archive($finalization);
            }
        }, 3);
    }
}
