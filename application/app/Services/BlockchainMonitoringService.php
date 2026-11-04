<?php

namespace App\Services;

use App\Models\LedgerVerification;
use App\Models\PayrollCommitment;
use App\Models\PayrollSnapshotArchive;
use App\Services\Blockchain\BesuStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cheap, read-only aggregation for the Blockchain Monitoring and Verification Center
 * and the workspace navbar indicator.
 *
 * It never verifies the full ledger and never contacts a node beyond the existing
 * cached reachability probe. A fresh, explicit verification is only produced by
 * {@see LedgerIntegrityVerificationService}.
 */
class BlockchainMonitoringService
{
    private const CACHE_KEY = 'blockchain:monitoring-summary';

    private const FRESH_SECONDS = 15;

    public const LABELS = [
        'ledger_operational' => 'Ledger Operational',
        'integrity_verified' => 'Integrity Verified',
        'integrity_issue' => 'Integrity Issue',
        'verification_required' => 'Verification Required',
        'service_unavailable' => 'Service Unavailable',
    ];

    public function __construct(private BesuStatus $besu, private LedgerIntegrityVerificationService $ledger) {}

    /** Invalidate the cached summary after a recorded verification. */
    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addSeconds(self::FRESH_SECONDS), fn (): array => $this->build());
    }

    /**
     * @return array<string, mixed>
     */
    private function build(): array
    {
        try {
            return $this->read();
        } catch (Throwable) {
            // A missing or unavailable ledger table must not break every workspace page.
            // Provider/database details are never surfaced.
            return $this->unavailable();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function read(): array
    {
        $records = $this->ledger->ledgerRecordCount();
        $latestArchive = PayrollSnapshotArchive::orderByDesc('id')
            ->first(['id', 'payroll_finalization_id', 'sha256', 'algorithm', 'canonicalization', 'created_at']);
        $previousArchive = $latestArchive
            ? PayrollSnapshotArchive::where('id', '<', $latestArchive->id)->orderByDesc('id')->value('sha256')
            : null;

        $algorithms = PayrollSnapshotArchive::distinct()->pluck('algorithm')
            ->merge(PayrollCommitment::distinct()->pluck('algorithm'))
            ->filter()->unique()->values()->all();

        $anchorCounts = DB::table('besu_anchor_outbox')->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');
        $confirmed = $this->latestConfirmedAnchor();
        $submissionCounts = DB::table('payroll_anchor_attempts')->selectRaw('status, count(*) as total')
            ->groupBy('status')->pluck('total', 'status');

        $verification = $this->ledger->latest();
        $report = $verification?->report;
        $counts = is_array($report) && is_array($report['counts'] ?? null) ? $report['counts'] : [];
        $lastVerified = LedgerVerification::where('status', 'verified')->orderByDesc('id')->first(['id', 'created_at', 'created_by']);
        $chain = $this->chainSummary();

        $state = match (true) {
            $verification && $verification->status === 'issues_found' => 'integrity_issue',
            $verification && $verification->status === 'verified' && $verification->records_checked === $records => 'integrity_verified',
            $records === 0 => 'ledger_operational',
            default => 'verification_required',
        };

        return [
            'available' => true,
            'state' => $state,
            'label' => self::LABELS[$state],
            'ledger' => [
                'records' => $records,
                'archives' => PayrollSnapshotArchive::count(),
                'commitments' => PayrollCommitment::count(),
                'algorithms' => $algorithms === [] ? ['sha256'] : $algorithms,
                'latest_archive_id' => $latestArchive?->id,
                'latest_finalization_id' => $latestArchive?->payroll_finalization_id,
                'latest_sha256' => $latestArchive?->sha256,
                'latest_algorithm' => $latestArchive?->algorithm,
                'previous_sha256' => $previousArchive,
                // Cache-safe scalars only: objects such as Carbon are never cached.
                'latest_created_at' => $latestArchive?->created_at?->toIso8601String(),
            ],
            'anchors' => [
                'confirmed' => (int) ($anchorCounts['confirmed'] ?? 0),
                'failed' => (int) ($anchorCounts['failed'] ?? 0),
                'pending' => (int) ($anchorCounts['pending'] ?? 0) + (int) ($anchorCounts['signed'] ?? 0),
                'queued' => (int) ($anchorCounts['queued'] ?? 0),
                'latest_confirmed_at' => $confirmed['confirmed_at'] ?? null,
                'latest_confirmed_reference' => $confirmed['reference'] ?? null,
            ],
            'submissions' => [
                'processing' => (int) ($submissionCounts['submitting'] ?? 0),
                'pending' => (int) ($submissionCounts['pending'] ?? 0),
                'unavailable' => (int) ($submissionCounts['unavailable'] ?? 0),
                'failed' => (int) ($submissionCounts['failed'] ?? 0),
                'unknown' => (int) ($submissionCounts['unknown'] ?? 0),
            ],
            'verification' => [
                'id' => $verification?->id,
                'status' => $verification?->status,
                'created_at' => $verification?->created_at?->toIso8601String(),
                'records_checked' => $verification?->records_checked,
                'issues_found' => $verification?->issues_found,
                'created_by' => $verification?->created_by,
                'counts' => $counts,
                'stale' => $verification !== null && $verification->records_checked !== $records,
                'last_verified_at' => $lastVerified?->created_at?->toIso8601String(),
                'last_verified_id' => $lastVerified?->id,
            ],
            'chain' => $chain,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function unavailable(): array
    {
        return [
            'available' => false,
            'state' => 'service_unavailable',
            'label' => self::LABELS['service_unavailable'],
            'ledger' => ['records' => null, 'archives' => null, 'commitments' => null, 'algorithms' => [], 'latest_archive_id' => null,
                'latest_finalization_id' => null, 'latest_sha256' => null, 'latest_algorithm' => null, 'previous_sha256' => null, 'latest_created_at' => null],
            'anchors' => ['confirmed' => null, 'failed' => null, 'pending' => null, 'queued' => null,
                'latest_confirmed_at' => null, 'latest_confirmed_reference' => null],
            'submissions' => ['processing' => null, 'pending' => null, 'unavailable' => null, 'failed' => null, 'unknown' => null],
            'verification' => ['id' => null, 'status' => null, 'created_at' => null, 'records_checked' => null,
                'issues_found' => null, 'created_by' => null, 'counts' => [], 'stale' => null,
                'last_verified_at' => null, 'last_verified_id' => null],
            'chain' => $this->chainSummary(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestConfirmedAnchor(): array
    {
        $row = DB::table('besu_anchor_outbox')->where('status', 'confirmed')
            ->orderByDesc('confirmed_at')->orderByDesc('id')->first(['confirmed_at', 'reference']);

        return $row ? ['confirmed_at' => $row->confirmed_at ? Carbon::parse($row->confirmed_at)->toIso8601String() : null, 'reference' => $row->reference] : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function chainSummary(): array
    {
        $enabled = $this->besu->enabled();
        $state = $enabled ? $this->besu->check() : null;

        return [
            'enabled' => $enabled,
            'automatic' => (bool) config('besu.automatic_anchoring'),
            'online' => $state['online'] ?? null,
            'block_number' => $state['block_number'] ?? null,
            'label' => ! $enabled
                ? 'Not configured'
                : (($state['online'] ?? false) ? 'Reachable' : 'Unreachable'),
        ];
    }
}
