<?php

namespace App\Services;

use App\Models\PayrollRegistration;
use App\Models\PayrollRegistrationVerification as Verification;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\Blockchain\PayrollCanonicalizer;
use App\Services\Blockchain\PayrollPayloadBuilder;
use App\Services\Blockchain\PayrollRegistryClient;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Throwable;

class PayrollVerificationService
{
    public function __construct(
        private readonly PayrollRegistryClient $client,
        private readonly PayrollPayloadBuilder $payloads,
        private readonly PayrollCanonicalizer $canonicalizer,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function verify(PayrollRegistration $registration, User $user): Verification
    {
        abort_unless($user->isActive() && $user->can('blockchain.verify'), 403);
        $context = PayrollRegistration::query()->findOrFail($registration->getKey());
        $read = null;
        $failure = null;
        try {
            $read = $this->client->read($context);
        } catch (Throwable $exception) {
            $failure = in_array($exception->getMessage(), ['BRIDGE_NETWORK_MISMATCH', 'INVALID_BRIDGE_EVIDENCE', 'INVALID_BRIDGE_RESPONSE'], true)
                ? Verification::INVALID_EVIDENCE : Verification::UNAVAILABLE;
        }

        return DB::transaction(function () use ($context, $read, $failure, $user): Verification {
            $run = PayrollRun::query()->lockForUpdate()->findOrFail($context->payroll_run_id);
            $locked = PayrollRegistration::query()->lockForUpdate()->findOrFail($context->getKey());
            $changed = false;
            foreach (['payroll_run_id', 'organization_id', 'payroll_reference', 'revision', 'hash_schema_version', 'payload_hash',
                'canonical_payload', 'period_snapshot', 'approval_snapshot', 'target_network', 'idempotency_key'] as $field) {
                $changed = $changed || $context->getRawOriginal($field) !== $locked->getRawOriginal($field);
            }
            $result = $this->evaluate($run, $locked, $read, $changed ? Verification::INVALID_EVIDENCE : $failure);
            $log = Verification::create([
                'registration_id' => $locked->getKey(), 'verified_by' => $user->getKey(),
                'captured_hash' => $locked->payload_hash, 'verified_at' => now(), ...$result,
            ]);
            $this->auditLogger->log('PAYROLL_REGISTRY_VERIFIED', 'Blockchain', $user->getKey(), 'payroll_registration_verifications', (string) $log->getKey(),
                newValues: ['registration_id' => $locked->getKey(), 'verification_result' => $log->verification_result]);

            return $log;
        });
    }

    /** @param array{registration:?array<string, mixed>, submission:?array<string, mixed>}|null $read
     * @return array{verification_result:string, remarks:string, stored_hash:?string, calculated_hash:?string, evidence:?array<string, mixed>}
     */
    private function evaluate(PayrollRun $run, PayrollRegistration $registration, ?array $read, ?string $failure): array
    {
        $result = ['verification_result' => $failure ?? Verification::NOT_REGISTERED, 'remarks' => '',
            'stored_hash' => null, 'calculated_hash' => null, 'evidence' => null];
        if ($failure !== null) {
            $result['remarks'] = $failure === Verification::UNAVAILABLE
                ? 'Verification is unavailable. No integrity conclusion was made. Try again or contact the administrator.'
                : 'The evidence could not be validated against the original registration. Contact the administrator.';

            return $result;
        }
        $evidence = $read['registration'];
        if ($evidence === null) {
            $submission = $read['submission']['status'] ?? null;
            $result['verification_result'] = match (true) {
                $submission === PayrollRegistration::CONFIRMED => Verification::INVALID_EVIDENCE,
                in_array($submission, PayrollRegistration::ACTIVE, true) => Verification::PENDING,
                $submission === null && in_array($registration->status, PayrollRegistration::ACTIVE, true) => Verification::PENDING,
                default => Verification::NOT_REGISTERED,
            };
            $result['remarks'] = match ($result['verification_result']) {
                Verification::PENDING => 'Registration is still pending. No integrity conclusion was made. Check again after confirmation.',
                Verification::INVALID_EVIDENCE => 'The reported confirmation has no corresponding blockchain registration. Contact the administrator.',
                default => 'No successful blockchain registration was found for this payroll reference and revision.',
            };

            return $result;
        }
        $result['stored_hash'] = $evidence['payload_hash'];
        $result['evidence'] = $evidence;
        if ($registration->hash_schema_version !== PayrollCanonicalizer::VERSION || $evidence['hash_schema_version'] !== PayrollCanonicalizer::VERSION) {
            $result['verification_result'] = Verification::INVALID_EVIDENCE;
            $result['remarks'] = 'The registered hash schema is unsupported. No integrity conclusion was made.';

            return $result;
        }
        try {
            $result['calculated_hash'] = $this->canonicalizer->hash($this->payloads->build($run, $registration));
        } catch (InvalidArgumentException) {
            $result['verification_result'] = Verification::MISMATCH;
            $result['remarks'] = 'Current payroll data no longer satisfies the registered schema. Ask an authorized reviewer to investigate.';

            return $result;
        }
        $currentMatches = hash_equals($evidence['payload_hash'], $result['calculated_hash']);
        $metadataMatches = hash_equals($evidence['payload_hash'], $registration->payload_hash)
            && hash_equals($evidence['payload_hash'], hash('sha256', $registration->canonical_payload))
            && hash_equals($registration->idempotency_key, hash('sha256', $registration->organization_id."\n".$registration->payroll_reference."\n".$registration->revision));
        $result['verification_result'] = $currentMatches && $metadataMatches ? Verification::MATCH : Verification::MISMATCH;
        $result['remarks'] = match (true) {
            ! $currentMatches => 'Payroll data differs from the registered version. Ask an authorized reviewer to investigate.',
            ! $metadataMatches => 'Saved registration data differs from the blockchain evidence. Ask an administrator to investigate.',
            default => 'At this check, payroll data matches the version registered on the blockchain.',
        };

        return $result;
    }
}
