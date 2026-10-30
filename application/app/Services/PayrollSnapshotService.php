<?php

namespace App\Services;

use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollSnapshotArchive;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use JsonException;

class PayrollSnapshotService
{
    public const SCHEMA = 'finalized-payroll-v1';

    public function __construct(private CanonicalPayrollSnapshot $canonical) {}

    /** Recompute current frozen JSON for comparison; never read live payroll inputs. */
    public function currentBytes(PayrollBatch $batch, PayrollFinalization $finalization): string
    {
        // Keep recomputation separate from archive-aware document issuance to avoid recursion.
        app(PayrollDocumentService::class)->validatedProjection($batch, $finalization);
        abort_unless(($finalization->snapshot['submission_id'] ?? null) === $finalization->payroll_submission_id
            && $finalization->version === $batch->payroll_version, 409, 'Finalization metadata is inconsistent.');
        try {
            return $this->canonical->encodeFinalization($batch->id, $finalization->id, $finalization->version, $finalization->snapshot);
        } catch (InvalidArgumentException|JsonException) {
            abort(409, 'The preserved snapshot cannot be serialized using the commitment format.');
        }
    }

    /** Read-only: legacy records remain reproducible without silently writing a baseline. */
    public function assertPreserved(PayrollBatch $batch, PayrollFinalization $finalization): void
    {
        $bytes = $this->currentBytes($batch, $finalization);
        $archive = PayrollSnapshotArchive::where('payroll_finalization_id', $finalization->id)->first();
        if ($archive) {
            $this->check($archive, $bytes);
        }
    }

    private function check(PayrollSnapshotArchive $archive, string $currentBytes): void
    {
        abort_unless($archive->schema_version === self::SCHEMA
            && $archive->canonicalization === CanonicalPayrollSnapshot::VERSION && $archive->algorithm === 'sha256'
            && preg_match('/\A[a-f0-9]{64}\z/', $archive->sha256)
            && hash_equals($archive->sha256, hash('sha256', $archive->canonical_bytes))
            && hash_equals($archive->canonical_bytes, $currentBytes), 409,
            'The finalized source differs from its preserved snapshot. Preserve it for integrity review.');
    }

    /** Called within finalization's transaction, or explicitly for a legacy record. */
    public function archive(PayrollFinalization $finalization, bool $write = true): ?PayrollSnapshotArchive
    {
        return DB::transaction(function () use ($finalization, $write) {
            // Same lock order as finalization, preparation and correction operations.
            $batch = PayrollBatch::whereKey($finalization->payroll_batch_id)->lockForUpdate()->firstOrFail();
            $saved = PayrollFinalization::whereKey($finalization->id)->lockForUpdate()->firstOrFail();
            abort_unless($saved->payroll_batch_id === $batch->id, 409);
            $bytes = $this->currentBytes($batch, $saved);
            $digest = hash('sha256', $bytes);
            $commitment = PayrollCommitment::where('payroll_finalization_id', $saved->id)->first();
            abort_if($commitment && ($commitment->canonicalization !== CanonicalPayrollSnapshot::VERSION
                || $commitment->algorithm !== 'sha256' || ! hash_equals($commitment->sha256, $digest)), 409,
                'The historical digest differs. No snapshot bytes were archived.');
            $archive = PayrollSnapshotArchive::where('payroll_finalization_id', $saved->id)->first();
            if ($archive) {
                $this->check($archive, $bytes);

                return $archive;
            }
            if (! $write) {
                return null;
            }

            return PayrollSnapshotArchive::forceCreate([
                'payroll_finalization_id' => $saved->id, 'schema_version' => self::SCHEMA,
                'canonicalization' => CanonicalPayrollSnapshot::VERSION, 'algorithm' => 'sha256',
                'canonical_bytes' => $bytes, 'sha256' => $digest,
            ]);
        });
    }
}
