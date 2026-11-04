<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only queries for the payroll hash-anchor and submission monitoring tables.
 *
 * Only reference metadata, digests and anchoring state are selected. Confidential
 * payroll values, finalized snapshots and encrypted signed payloads are never loaded.
 */
class PayrollAnchorQueryService
{
    public const ANCHOR_STATUSES = ['queued', 'signed', 'pending', 'confirmed', 'failed', 'not_queued'];

    public const SUBMISSION_STATUSES = ['submitting', 'pending', 'unavailable', 'failed', 'unknown'];

    public const ANCHOR_LABELS = [
        'queued' => 'Queued', 'signed' => 'Signed', 'pending' => 'Pending',
        'confirmed' => 'Confirmed', 'failed' => 'Failed', 'not_queued' => 'Not queued',
    ];

    public const SUBMISSION_LABELS = [
        'submitting' => 'Processing', 'pending' => 'Pending', 'unavailable' => 'Unavailable',
        'failed' => 'Rejected', 'unknown' => 'Unknown',
    ];

    /** Stored submission state and its plain processing result; never a confirmed transaction claim. */
    public const SUBMISSION_RESULTS = [
        'submitting' => 'Submission is in progress.',
        'pending' => 'A submission was recorded but is not independently confirmed on a network.',
        'unavailable' => 'No authorized submission interface was available. Nothing was sent.',
        'failed' => 'The submission was rejected. No successful anchor is established.',
        'unknown' => 'Acceptance is uncertain or interrupted. Review before any new submission.',
    ];

    public static function anchorLabel(?string $status): string
    {
        return self::ANCHOR_LABELS[$status ?? 'not_queued'] ?? 'Unknown';
    }

    public static function submissionLabel(?string $status): string
    {
        return self::SUBMISSION_LABELS[$status ?? ''] ?? 'Unknown';
    }

    public static function submissionResult(?string $status): string
    {
        return self::SUBMISSION_RESULTS[$status ?? ''] ?? 'Review required.';
    }

    public function anchors(array $filters, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('payroll_commitments as c')
            ->join('payroll_finalizations as f', 'f.id', '=', 'c.payroll_finalization_id')
            ->join('payroll_batches as b', 'b.id', '=', 'f.payroll_batch_id')
            ->leftJoin('besu_anchor_outbox as o', 'o.payroll_commitment_id', '=', 'c.id')
            ->select([
                'c.id', 'c.algorithm', 'c.sha256', 'c.submission_key', 'c.created_at',
                'f.id as finalization_id', 'f.version as finalization_version',
                'b.id as batch_id', 'b.period_start', 'b.payroll_version', 'b.is_demonstration',
                'o.id as outbox_id', 'o.status as anchor_status', 'o.confirmed_at', 'o.transaction_hash',
                DB::raw('(select pa.status from payroll_anchor_attempts pa where pa.payroll_commitment_id = c.id order by pa.id desc limit 1) as attempt_status'),
            ]);

        $this->applyPeriodAndSearch($query, $filters);
        $status = $filters['status'] ?? null;
        if (is_string($status) && in_array($status, self::ANCHOR_STATUSES, true)) {
            $status === 'not_queued' ? $query->whereNull('o.id') : $query->where('o.status', $status);
        }

        return $query->orderByDesc('c.id')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }

    public function submissions(array $filters, int $page = 1, int $perPage = 15): LengthAwarePaginator
    {
        $query = DB::table('payroll_anchor_attempts as a')
            ->join('payroll_commitments as c', 'c.id', '=', 'a.payroll_commitment_id')
            ->join('payroll_finalizations as f', 'f.id', '=', 'c.payroll_finalization_id')
            ->join('payroll_batches as b', 'b.id', '=', 'f.payroll_batch_id')
            ->select([
                'a.id', 'a.status', 'a.network', 'a.transaction_reference', 'a.created_at', 'a.completed_at',
                'c.id as commitment_id', 'c.submission_key',
                'b.id as batch_id', 'b.period_start', 'b.payroll_version', 'b.is_demonstration',
            ]);

        $status = $filters['submission_status'] ?? null;
        if (is_string($status) && in_array($status, self::SUBMISSION_STATUSES, true)) {
            $query->where('a.status', $status);
        }

        return $query->orderByDesc('a.id')->paginate($perPage, ['*'], 'submissions_page', $page)->withQueryString();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function anchorDetail(int $commitmentId): ?array
    {
        $anchor = DB::table('payroll_commitments as c')
            ->join('payroll_finalizations as f', 'f.id', '=', 'c.payroll_finalization_id')
            ->join('payroll_batches as b', 'b.id', '=', 'f.payroll_batch_id')
            ->leftJoin('besu_anchor_outbox as o', 'o.payroll_commitment_id', '=', 'c.id')
            ->where('c.id', $commitmentId)
            ->select([
                'c.id', 'c.algorithm', 'c.canonicalization', 'c.sha256', 'c.submission_key', 'c.created_at',
                'f.id as finalization_id', 'f.version as finalization_version', 'f.created_at as finalized_at',
                'b.id as batch_id', 'b.period_start', 'b.payroll_version', 'b.is_demonstration',
                'o.id as outbox_id', 'o.reference', 'o.status as anchor_status', 'o.transaction_hash',
                'o.block_number', 'o.block_hash', 'o.confirmed_at', 'o.last_error', 'o.retry_count', 'o.next_attempt_at',
            ])->first();

        if (! $anchor) {
            return null;
        }

        $attempts = DB::table('payroll_anchor_attempts')->where('payroll_commitment_id', $commitmentId)
            ->orderByDesc('id')->get(['id', 'status', 'network', 'transaction_reference', 'created_at', 'completed_at']);
        $events = $anchor->outbox_id
            ? DB::table('besu_anchor_events')->where('outbox_id', $anchor->outbox_id)->orderByDesc('id')->limit(50)->get(['id', 'event', 'created_at'])
            : new Collection;
        $transactions = $anchor->outbox_id
            ? DB::table('besu_anchor_transactions')->where('outbox_id', $anchor->outbox_id)->orderByDesc('id')
                ->get(['id', 'status', 'nonce', 'block_number', 'block_hash', 'confirmed_at', 'created_at', 'transaction_hash'])
            : new Collection;

        return compact('anchor', 'attempts', 'events', 'transactions');
    }

    private function applyPeriodAndSearch(Builder $query, array $filters): void
    {
        $period = $filters['period'] ?? null;
        if (is_string($period) && preg_match('/\A\d{4}-\d{2}\z/', $period)) {
            [$start, $end] = $this->periodRange($period);
            $query->where('b.period_start', '>=', $start)->where('b.period_start', '<', $end);
        }

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search === '') {
            return;
        }
        $needle = strtolower($search);
        $query->where(function (Builder $inner) use ($needle, $search): void {
            $inner->where('c.sha256', 'like', $needle.'%')
                ->orWhere('c.submission_key', 'like', $needle.'%');
            if (preg_match('/\A\d{4}-\d{2}\z/', $search)) {
                [$start, $end] = $this->periodRange($search);
                $inner->orWhere(fn (Builder $period) => $period->where('b.period_start', '>=', $start)->where('b.period_start', '<', $end));
            }
            if (ctype_digit($search)) {
                $inner->orWhere('b.id', (int) $search)->orWhere('f.id', (int) $search);
            }
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function periodRange(string $period): array
    {
        $start = Carbon::createFromFormat('!Y-m', $period);

        return [$start->toDateString(), $start->copy()->addMonth()->toDateString()];
    }
}
