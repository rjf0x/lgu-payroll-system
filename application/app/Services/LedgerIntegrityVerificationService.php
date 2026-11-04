<?php

namespace App\Services;

use App\Models\LedgerVerification;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollCorrectionRequest;
use App\Models\PayrollFinalization;
use App\Models\PayrollSnapshotArchive;
use App\Models\User;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Read-only, system-wide integrity verification of the retained payroll ledger.
 *
 * The application does not define a sequential previous-hash ledger; instead every
 * finalized payroll is preserved as immutable canonical bytes with a SHA-256 digest,
 * optionally linked to an immutable commitment and to a correction lineage. This
 * service re-derives those values and reports inconsistencies without ever writing
 * to, repairing or rewriting an existing audit record.
 */
class LedgerIntegrityVerificationService
{
    public const SCHEMA = PayrollSnapshotService::SCHEMA;

    public const MESSAGES = [
        'invalid_record_hash' => 'The stored digest does not match the preserved record bytes.',
        'modified_source_snapshot' => 'The retained record no longer matches its preserved canonical source.',
        'broken_reference' => 'A retained record or its lineage link is missing or inconsistent.',
        'unsupported_record_format' => 'A retained record uses an unsupported canonicalization, algorithm or digest format.',
    ];

    private const MAX_REPORTED_ISSUES = 200;

    public function __construct(private CanonicalPayrollSnapshot $canonical) {}

    public function latest(): ?LedgerVerification
    {
        return LedgerVerification::orderByDesc('id')->first();
    }

    /** Cheap count that must match the number of records a fresh verification inspects. */
    public function ledgerRecordCount(): int
    {
        return PayrollSnapshotArchive::count()
            + PayrollCorrectionRequest::count()
            + PayrollBatch::whereNotNull('correction_of_finalization_id')->count();
    }

    public function verify(User $actor, string $requestKey): LedgerVerification
    {
        $fresh = User::whereKey($actor->id)->firstOrFail();
        abort_unless($fresh->is_active, 403);

        $existing = LedgerVerification::where('request_key', $requestKey)->first();
        if ($existing) {
            abort_unless($existing->created_by === $fresh->id, 409, 'This verification request belongs to another account.');

            return $existing;
        }

        [$recordsChecked, $issues] = $this->scan();
        $truncated = count($issues) > self::MAX_REPORTED_ISSUES;
        $report = [
            'issues' => array_slice($issues, 0, self::MAX_REPORTED_ISSUES),
            'counts' => array_count_values(array_column($issues, 'type')),
            'truncated' => $truncated,
            'note' => 'Read-only inspection of preserved canonical records and lineage links. No record was modified or repaired.',
        ];

        try {
            $verification = LedgerVerification::forceCreate([
                'request_key' => $requestKey,
                'status' => $issues === [] ? 'verified' : 'issues_found',
                'records_checked' => $recordsChecked,
                'issues_found' => count($issues),
                'report' => $report,
                'created_by' => $fresh->id,
                'created_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            // The same request key was submitted concurrently; reuse the retained result.
            return LedgerVerification::where('request_key', $requestKey)->firstOrFail();
        }

        // Safe operational logging only: no payroll values, digests or provider details.
        Log::info('ledger.integrity_verified', [
            'verification_id' => $verification->id,
            'actor_id' => $fresh->id,
            'status' => $verification->status,
            'records_checked' => $verification->records_checked,
            'issues_found' => $verification->issues_found,
        ]);

        app(BlockchainMonitoringService::class)->forget();

        return $verification;
    }

    /**
     * @return array{0: int, 1: array<int, array<string, mixed>>}
     */
    private function scan(): array
    {
        $checked = 0;
        $issues = [];

        PayrollSnapshotArchive::query()->orderBy('id')->chunkById(200, function ($archives) use (&$checked, &$issues): void {
            foreach ($archives as $archive) {
                $checked++;
                $reference = 'archive:'.$archive->id;

                if (! preg_match('/\A[a-f0-9]{64}\z/', (string) $archive->sha256)
                    || ! hash_equals((string) $archive->sha256, hash('sha256', (string) $archive->canonical_bytes))) {
                    $this->addIssue($issues, 'invalid_record_hash', $reference, $archive->payroll_finalization_id);
                }
                if ($archive->schema_version !== self::SCHEMA
                    || $archive->canonicalization !== CanonicalPayrollSnapshot::VERSION
                    || $archive->algorithm !== 'sha256') {
                    $this->addIssue($issues, 'unsupported_record_format', $reference, $archive->payroll_finalization_id);
                }

                $finalization = PayrollFinalization::find($archive->payroll_finalization_id);
                if (! $finalization) {
                    $this->addIssue($issues, 'broken_reference', $reference, $archive->payroll_finalization_id);

                    continue;
                }
                try {
                    $bytes = $this->canonical->encodeFinalization(
                        $finalization->payroll_batch_id,
                        $finalization->id,
                        $finalization->version,
                        is_array($finalization->snapshot) ? $finalization->snapshot : [],
                    );
                } catch (Throwable) {
                    $this->addIssue($issues, 'modified_source_snapshot', $reference, $finalization->id);

                    continue;
                }
                if (! hash_equals((string) $archive->canonical_bytes, $bytes)) {
                    $this->addIssue($issues, 'modified_source_snapshot', $reference, $finalization->id);
                }

                $commitment = PayrollCommitment::where('payroll_finalization_id', $finalization->id)->first();
                if ($commitment) {
                    $commitmentReference = 'commitment:'.$commitment->id;
                    if ($commitment->algorithm !== 'sha256'
                        || $commitment->canonicalization !== CanonicalPayrollSnapshot::VERSION
                        || ! preg_match('/\A[a-f0-9]{64}\z/', (string) $commitment->sha256)) {
                        $this->addIssue($issues, 'unsupported_record_format', $commitmentReference, $finalization->id);
                    } elseif (! hash_equals((string) $commitment->sha256, (string) $archive->sha256)) {
                        $this->addIssue($issues, 'broken_reference', $commitmentReference, $finalization->id);
                    }
                }
            }
        });

        PayrollCorrectionRequest::query()->orderBy('id')->chunkById(200, function ($requests) use (&$checked, &$issues): void {
            foreach ($requests as $request) {
                $checked++;
                $reference = 'correction:'.$request->id;
                $source = PayrollFinalization::find($request->source_finalization_id);
                if (! $source) {
                    $this->addIssue($issues, 'broken_reference', $reference, $request->source_finalization_id);

                    continue;
                }
                $archive = PayrollSnapshotArchive::where('payroll_finalization_id', $source->id)->first();
                if (! $archive || ! hash_equals((string) $request->source_hash, (string) $archive->sha256)) {
                    $this->addIssue($issues, 'broken_reference', $reference, $source->id);
                }
            }
        });

        PayrollBatch::query()->whereNotNull('correction_of_finalization_id')->orderBy('id')->chunkById(200, function ($batches) use (&$checked, &$issues): void {
            foreach ($batches as $batch) {
                $checked++;
                $reference = 'batch:'.$batch->id;
                $parent = PayrollFinalization::find($batch->correction_of_finalization_id);
                if (! $parent
                    || ! PayrollSnapshotArchive::where('payroll_finalization_id', $parent->id)->exists()) {
                    $this->addIssue($issues, 'broken_reference', $reference, $batch->correction_of_finalization_id);
                }
            }
        });

        return [$checked, $issues];
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     */
    private function addIssue(array &$issues, string $type, string $reference, ?int $finalizationId): void
    {
        $issues[] = [
            'type' => $type,
            'reference' => $reference,
            'finalization_id' => $finalizationId,
            'message' => self::MESSAGES[$type] ?? 'Retained record requires review.',
        ];
    }
}
