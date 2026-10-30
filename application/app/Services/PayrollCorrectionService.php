<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollCorrectionDecision;
use App\Models\PayrollCorrectionRequest;
use App\Models\PayrollFinalization;
use App\Models\PayrollSubmission;
use App\Models\User;
use App\Rules\Utf8Text;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class PayrollCorrectionService
{
    public function family(PayrollBatch $batch): Builder
    {
        return PayrollBatch::whereDate('period_start', $batch->period_start->format('Y-m-d'))->where('is_demonstration', $batch->is_demonstration);
    }

    public function independent(User $actor, PayrollBatch $batch, PayrollFinalization $source, PayrollCorrectionRequest $proposal): bool
    {
        $submission = PayrollSubmission::find($source->payroll_submission_id);

        return $proposal->source_finalization_id === $source->id && $actor->id !== $proposal->requested_by
            && $submission && app(PayrollWorkflowService::class)->isIndependent($actor, $batch, $submission);
    }

    public function digest(PayrollBatch $batch, PayrollFinalization $source): string
    {
        abort_unless($source->payroll_batch_id === $batch->id && $source->version === $batch->payroll_version, 409, 'Finalized version metadata is inconsistent.');
        $hash = app(PayrollAnchoringService::class)->digest($batch, $source);
        app(PayrollSnapshotService::class)->assertPreserved($batch, $source);
        $commitment = PayrollCommitment::where('payroll_finalization_id', $source->id)->first();
        abort_if($commitment && ($commitment->algorithm !== 'sha256'
            || $commitment->canonicalization !== CanonicalPayrollSnapshot::VERSION
            || ! hash_equals($commitment->sha256, $hash)), 409, 'The source differs from its saved commitment. Preserve it for integrity review.');

        return $hash;
    }

    /** Frozen authorization lineage becomes part of the corrected payroll snapshot/hash. */
    public function context(PayrollBatch $batch): array
    {
        $decision = PayrollCorrectionDecision::where('correction_batch_id', $batch->id)->where('action', 'authorize')->first();
        abort_unless($decision, 409, 'Correction authorization is missing.');
        $proposal = PayrollCorrectionRequest::findOrFail($decision->payroll_correction_request_id);
        $source = PayrollFinalization::findOrFail($proposal->source_finalization_id);
        $parent = PayrollBatch::findOrFail($source->payroll_batch_id);
        abort_unless($batch->correction_of_finalization_id === $source->id
            && $batch->root_payroll_batch_id === ($parent->root_payroll_batch_id ?? $parent->id)
            && $batch->payroll_version === $parent->payroll_version + 1
            && $parent->period_start->format('Y-m-d') === $batch->period_start->format('Y-m-d')
            && $parent->is_demonstration === $batch->is_demonstration
            && hash_equals($proposal->source_hash, $this->digest($parent, $source)), 409, 'Correction lineage or source changed. Submission is blocked.');

        return [
            'root_payroll_batch_id' => $batch->root_payroll_batch_id, 'payroll_version' => $batch->payroll_version,
            'source_finalization_id' => $source->id, 'source_sha256' => $proposal->source_hash,
            'request_id' => $proposal->id, 'requested_by' => $proposal->requested_by,
            'request_revision' => $proposal->revision, 'requested_at' => $proposal->created_at->toISOString(),
            'request_reason' => $proposal->reason, 'request_authority' => $proposal->authority_reference,
            'decision_id' => $decision->id, 'authorized_by' => $decision->created_by,
            'authorized_at' => $decision->created_at->toISOString(),
            'authorization_reason' => $decision->reason, 'authorization_reference' => $decision->authority_reference,
        ];
    }

    public function act(Request $request, PayrollBatch $batch, PayrollFinalization $source, string $action, array $data, ?PayrollCorrectionRequest $proposal = null): PayrollCorrectionRequest|PayrollCorrectionDecision
    {
        try {
            return DB::transaction(function () use ($request, $batch, $source, $action, $data, $proposal) {
                $actor = User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                abort_unless($actor->is_active && $actor->auth_version === $request->session()->get('auth_version'), 403);
                // Serialize all proposals/authorizations for this monthly version family.
                $family = $this->family($batch)->orderBy('payroll_version')->lockForUpdate()->get();
                $current = $family->firstWhere('id', $batch->id);
                abort_unless($current, 404);
                abort_unless(in_array($action, ['request', 'authorize', 'return'], true), 404);
                Gate::forUser($actor)->authorize($action === 'request' ? 'requestCorrection' : 'reviewCorrection', $current);
                $saved = PayrollFinalization::whereKey($source->id)->lockForUpdate()->firstOrFail();
                abort_unless($saved->payroll_batch_id === $current->id, 404);
                $data = Validator::make($data, [
                    'request_key' => [$action === 'request' ? 'required' : 'nullable', 'uuid'],
                    'source_hash' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
                    'reason' => ['required', 'string', new Utf8Text, 'max:500'],
                    'authority_reference' => [$action === 'return' ? 'nullable' : 'required', 'string', new Utf8Text, 'max:255'],
                    'review_confirmed' => ['required', 'accepted'],
                ])->validate();
                $authority = $data['authority_reference'] ?? null;
                if ($action === 'request') {
                    $key = strtolower($data['request_key']);
                    $retry = PayrollCorrectionRequest::where('request_key', $key)->first();
                    if ($retry) {
                        abort_unless($retry->source_finalization_id === $saved->id && $retry->requested_by === $actor->id
                            && $retry->source_hash === $data['source_hash'] && $retry->reason === $data['reason']
                            && $retry->authority_reference === $authority, 409, 'This request key belongs to another reviewed correction.');

                        return $retry;
                    }
                } else {
                    $proposal = PayrollCorrectionRequest::whereKey($proposal?->id)->firstOrFail();
                    abort_unless($proposal->source_finalization_id === $saved->id, 404);
                    abort_unless($this->independent($actor, $current, $saved, $proposal), 403, 'A preparer or requester cannot authorize or return their own correction.');
                    abort_unless(hash_equals($proposal->source_hash, $data['source_hash']), 409, 'Reload the correction request before reviewing.');
                    $decision = $proposal->decision;
                    if ($decision) {
                        abort_unless($decision->created_by === $actor->id && $decision->action === $action
                            && $decision->reason === $data['reason'] && $decision->authority_reference === $authority, 409, 'This correction request has already been reviewed.');

                        return $decision;
                    }
                }
                abort_unless($family->last()->id === $current->id && $current->payroll_version < 2147483647, 409, 'Use the latest finalized version. An existing correction draft must be completed first.');
                $latest = PayrollCorrectionRequest::where('source_finalization_id', $saved->id)->orderByDesc('revision')->first();
                if ($action === 'request') {
                    abort_unless(hash_equals($data['source_hash'], $this->digest($current, $saved)), 409, 'The source changed. Reload and review before requesting a correction.');
                    abort_if($latest && ! $latest->decision, 409, 'A correction request is already awaiting review.');
                    abort_if($latest && $latest->revision >= 2147483647, 409);

                    return PayrollCorrectionRequest::forceCreate([
                        'source_finalization_id' => $saved->id, 'revision' => ($latest?->revision ?? 0) + 1,
                        'request_key' => $key, 'source_hash' => $data['source_hash'], 'reason' => $data['reason'],
                        'authority_reference' => $authority, 'requested_by' => $actor->id, 'created_at' => now(),
                    ]);
                }
                abort_unless($latest?->id === $proposal->id, 409, 'Only the latest pending request may be reviewed.');
                $replacement = null;
                if ($action === 'authorize') {
                    abort_unless(hash_equals($proposal->source_hash, $this->digest($current, $saved)), 409, 'The requested source changed. Return this request for integrity review.');
                    $requester = User::findOrFail($proposal->requested_by);
                    abort_unless($requester->is_active && $requester->role === Role::PayrollOfficer, 409, 'The requester must remain an active Payroll Officer. Return and request a new revision.');
                    // Start empty: never infer updated coverage, entitlements or inputs from old values.
                    $replacement = PayrollBatch::forceCreate([
                        'period_start' => $current->period_start->format('Y-m-d'), 'is_demonstration' => true,
                        'payroll_version' => $current->payroll_version + 1,
                        'root_payroll_batch_id' => $current->root_payroll_batch_id ?? $current->id,
                        'correction_of_finalization_id' => $saved->id,
                        'status' => 'draft', 'record_version' => 1, 'coverage_preparer_ids' => [$requester->id],
                        'created_by' => $requester->id, 'updated_by' => $requester->id,
                    ]);
                }

                return PayrollCorrectionDecision::forceCreate([
                    'payroll_correction_request_id' => $proposal->id, 'action' => $action,
                    'correction_batch_id' => $replacement?->id, 'reason' => $data['reason'],
                    'authority_reference' => $authority, 'created_by' => $actor->id, 'created_at' => now(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'This correction changed concurrently. Reload its history before acting.');
        }
    }
}
