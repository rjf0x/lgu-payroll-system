<?php

namespace App\Services;

use App\Models\PayrollApproval;
use App\Models\PayrollRegistration;
use App\Models\PayrollRun;
use App\Services\Blockchain\PayrollCanonicalizer;
use App\Services\Blockchain\PayrollPayloadBuilder;
use App\Services\Blockchain\PayrollRegistryClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use Throwable;

class PayrollRegistrationService
{
    public function __construct(
        private readonly PayrollCanonicalizer $canonicalizer,
        private readonly PayrollPayloadBuilder $payloads,
        private readonly PayrollRegistryClient $client,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** Capture is called only while finalization holds the payroll run's transaction lock. */
    public function capture(PayrollRun $run): PayrollRegistration
    {
        abort_unless(DB::transactionLevel() > 0 && $run->status === PayrollRun::STATUS_FINALIZED, 422, 'Registry capture requires atomic payroll finalization.');
        $approval = $run->approvals()->where('action', PayrollApproval::ACTION_APPROVED)
            ->where('acted_at', '<=', $run->finalized_at)->orderByDesc('acted_at')->orderByDesc('approval_id')->first();
        if ($approval === null) {
            throw ValidationException::withMessages(['blockchain' => 'A successful payroll approval is required for registry capture.']);
        }
        $period = $run->payrollPeriod()->lockForUpdate()->firstOrFail();
        $registration = new PayrollRegistration([
            'payroll_run_id' => $run->getKey(),
            'organization_id' => config('blockchain.registry.organization_id'),
            'payroll_reference' => (string) Str::uuid(),
            'revision' => 1,
            'hash_schema_version' => PayrollCanonicalizer::VERSION,
            'period_snapshot' => $this->payloads->values($period, PayrollCanonicalizer::PERIOD),
            'approval_snapshot' => $this->payloads->values($approval, PayrollCanonicalizer::APPROVAL),
            'target_network' => $this->client->target(),
            'status' => PayrollRegistration::QUEUED,
            'next_attempt_at' => now(),
        ]);
        try {
            $registration->canonical_payload = $this->canonicalizer->serialize($this->payloads->build($run, $registration));
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages(['blockchain' => $exception->getMessage()]);
        }
        $registration->payload_hash = hash('sha256', $registration->canonical_payload);
        $registration->idempotency_key = hash('sha256', $registration->organization_id."\n".$registration->payroll_reference."\n".$registration->revision);
        $registration->save();
        $this->auditLogger->log('PAYROLL_REGISTRATION_QUEUED', 'Blockchain', $run->finalized_by,
            'payroll_registrations', (string) $registration->getKey(), newValues: ['hash_schema_version' => PayrollCanonicalizer::VERSION, 'payload_hash' => $registration->payload_hash]);

        return $registration;
    }

    public function synchronize(int $registrationId): void
    {
        if (! config('blockchain.registry.enabled')) {
            return;
        }
        $token = (string) Str::uuid();
        $registration = DB::transaction(function () use ($registrationId, $token): ?PayrollRegistration {
            $registration = PayrollRegistration::query()->lockForUpdate()->find($registrationId);
            if ($registration === null || ! in_array($registration->status, PayrollRegistration::ACTIVE, true)
                || $registration->next_attempt_at?->isFuture()) {
                return null;
            }
            $registration->update(['lease_token' => $token, 'attempts' => $registration->attempts + 1, 'next_attempt_at' => now()->addMinutes(2)]);

            return $registration;
        });
        if ($registration === null) {
            return;
        }
        try {
            $this->validateCapturedPayload($registration);
            $result = $this->client->synchronize($registration);
            $attributes = [...$result, 'last_error' => $result['status'] === PayrollRegistration::CONFLICT ? 'REGISTRATION_CONFLICT' : null];
        } catch (Throwable $exception) {
            $allowed = ['INVALID_REGISTRY_CONFIG', 'REGISTRY_TARGET_CHANGED', 'BRIDGE_NETWORK_MISMATCH', 'INVALID_BRIDGE_EVIDENCE',
                'INVALID_BRIDGE_RESPONSE', 'INVALID_BRIDGE_CREDENTIALS', 'BRIDGE_REQUEST_FAILED', 'LOCAL_PAYLOAD_INVALID'];
            $code = in_array($exception->getMessage(), $allowed, true) ? $exception->getMessage() : 'BRIDGE_UNAVAILABLE';
            $attributes = ['status' => $code === 'LOCAL_PAYLOAD_INVALID' ? PayrollRegistration::FAILED
                : ($registration->transaction_hash === null ? PayrollRegistration::QUEUED : PayrollRegistration::RECONCILING), 'last_error' => $code];
        }
        $active = in_array($attributes['status'], PayrollRegistration::ACTIVE, true);
        $registration->fill([...$attributes, 'lease_token' => null,
            'next_attempt_at' => $active ? now()->addSeconds(min(300, 30 * (2 ** min($registration->attempts, 4)))) : null]);
        DB::transaction(function () use ($registration, $token): void {
            $locked = PayrollRegistration::query()->lockForUpdate()->findOrFail($registration->getKey());
            if ($locked->lease_token !== $token) {
                return;
            }
            $oldStatus = $locked->status;
            $changes = [];
            foreach (array_keys($registration->getDirty()) as $attribute) {
                $changes[$attribute] = $registration->getAttribute($attribute);
            }
            $locked->fill($changes)->save();
            if ($oldStatus !== $locked->status) {
                $this->auditLogger->log('PAYROLL_REGISTRATION_'.$locked->status, 'Blockchain', null, 'payroll_registrations', (string) $locked->getKey(),
                    newValues: ['status' => $locked->status, 'transaction_hash' => $locked->transaction_hash]);
            }
        });
    }

    private function validateCapturedPayload(PayrollRegistration $registration): void
    {
        try {
            $payload = json_decode($registration->canonical_payload, true, 512, JSON_THROW_ON_ERROR);
            $valid = is_array($payload) && $registration->hash_schema_version === PayrollCanonicalizer::VERSION
                && hash_equals($registration->payload_hash, hash('sha256', $registration->canonical_payload))
                && $this->canonicalizer->serialize($payload) === $registration->canonical_payload;
            foreach (['organization_id', 'payroll_reference', 'revision', 'hash_schema_version'] as $key) {
                $valid = $valid && ($payload[$key] ?? null) === $registration->{$key};
            }
            $valid = $valid && hash_equals($registration->idempotency_key,
                hash('sha256', $registration->organization_id."\n".$registration->payroll_reference."\n".$registration->revision));
        } catch (InvalidArgumentException|JsonException) {
            $valid = false;
        }
        if (! $valid) {
            throw new \RuntimeException('LOCAL_PAYLOAD_INVALID');
        }
    }
}
