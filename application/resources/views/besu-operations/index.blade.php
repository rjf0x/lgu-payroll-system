@extends('layouts.app')
@section('title', 'Blockchain operations · LGU Payroll')
@section('content')
    @php
        $qs = \App\Services\PayrollAnchorQueryService::class;
        $verification = $summary['verification'];
        $counts = $verification['counts'];
        $hashMismatches = (int) ($counts['invalid_record_hash'] ?? 0) + (int) ($counts['unsupported_record_format'] ?? 0);
        $brokenLinks = (int) ($counts['broken_reference'] ?? 0);
        $modifiedRecords = (int) ($counts['modified_source_snapshot'] ?? 0);
        $pendingSubmissions = ($summary['submissions']['processing'] ?? 0) + ($summary['submissions']['pending'] ?? 0)
            + ($summary['submissions']['unknown'] ?? 0) + ($summary['anchors']['queued'] ?? 0)
            + ($summary['anchors']['pending'] ?? 0);
        $failedAnchors = ($summary['anchors']['failed'] ?? 0) + ($summary['submissions']['failed'] ?? 0);
        $format = function ($value) {
            if (! $value || ! (is_string($value) || $value instanceof \DateTimeInterface)) {
                return 'Not available';
            }
            try {
                return \Illuminate\Support\Carbon::parse($value)->format('Y-m-d H:i:s');
            } catch (\Throwable) {
                return 'Not available';
            }
        };
    @endphp
    <div class="page-heading">
        <div>
            <p class="eyebrow">Ledger monitoring</p>
            <h1>Blockchain operations</h1>
            <p>Read-only overview of ledger integrity, payroll hash anchoring, submission records and retained verification history. This page never signs, sends or authorizes payroll.</p>
        </div>
        <span class="page-date">{{ now()->format('l, F j, Y') }}</span>
    </div>

    @unless ($summary['available'])
        <p class="text-amber-900">The retained ledger could not be read. Monitoring figures are unavailable and no records were changed. This is reported separately from blockchain connectivity.</p>
    @endunless

    <div class="section-heading"><div><h2>Blockchain overview</h2><p>Every figure below is read from retained database records. Demonstration or hardcoded values are never substituted.</p></div></div>
    <div class="monitor-cards">
        <section class="monitor-card rounded border bg-white" aria-label="Ledger integrity summary">
            <p class="monitor-card-label">Ledger integrity</p>
            <strong class="monitor-card-value">{{ $summary['label'] }}</strong>
            <dl>
                <dt>Last successful verification</dt>
                <dd>{{ $verification['last_verified_at'] ? $format($verification['last_verified_at']) : 'Not yet verified' }}</dd>
                <dt>Integrity violations detected</dt>
                <dd>{{ $verification['id'] ? number_format((int) $verification['issues_found']) : 'Not yet verified' }}</dd>
            </dl>
        </section>
        <section class="monitor-card rounded border bg-white" aria-label="Anchored records summary">
            <p class="monitor-card-label">Anchored records</p>
            <strong class="monitor-card-value">{{ is_null($summary['anchors']['confirmed']) ? 'Unavailable' : number_format($summary['anchors']['confirmed']) }}</strong>
            <dl>
                <dt>Latest successful anchor</dt>
                <dd>{{ $format($summary['anchors']['latest_confirmed_at']) }}</dd>
                <dt>Failed anchoring attempts</dt>
                <dd>{{ $summary['available'] ? number_format($failedAnchors) : 'Unavailable' }}</dd>
            </dl>
        </section>
        <section class="monitor-card rounded border bg-white" aria-label="Pending submissions summary">
            <p class="monitor-card-label">Pending submissions</p>
            <strong class="monitor-card-value">{{ $summary['available'] ? number_format($pendingSubmissions) : 'Unavailable' }}</strong>
            <dl>
                <dt>Processing status</dt>
                <dd>{{ ! $summary['available'] ? 'Unavailable' : (($summary['submissions']['processing'] ?? 0) > 0 ? 'Submissions are being processed' : 'No active processing') }}</dd>
                <dt>Recorded failures</dt>
                <dd>{{ $summary['available'] ? number_format($failedAnchors) : 'Unavailable' }}</dd>
            </dl>
        </section>
        <section class="monitor-card rounded border bg-white" aria-label="Integrity issues summary">
            <p class="monitor-card-label">Integrity issues</p>
            <strong class="monitor-card-value">{{ $verification['id'] ? number_format($hashMismatches + $brokenLinks + $modifiedRecords) : 'Not yet verified' }}</strong>
            <dl>
                <dt>Hash mismatches</dt>
                <dd>{{ $verification['id'] ? number_format($hashMismatches) : 'Not yet verified' }}</dd>
                <dt>Broken chain references</dt>
                <dd>{{ $verification['id'] ? number_format($brokenLinks) : 'Not yet verified' }}</dd>
            </dl>
        </section>
    </div>

    <div class="section-heading"><div><h2>Ledger monitoring</h2><p>The application preserves a per-record SHA-256 digest, not a previous-hash pointer. Sequential records are listed here; no cryptographic chain link between adjacent records is claimed.</p></div></div>
    <section class="rounded border bg-white" aria-label="Ledger monitoring detail">
        <dl class="monitor-ledger">
            <div><dt>Ledger availability</dt><dd>{{ $summary['available'] ? 'Available' : 'Unavailable' }}</dd></div>
            <div><dt>Hash algorithm</dt><dd>{{ implode(', ', $summary['ledger']['algorithms']) ?: 'Not available' }}</dd></div>
            <div><dt>Latest audit record</dt><dd>{{ $summary['ledger']['latest_archive_id'] ? 'Archive #'.$summary['ledger']['latest_archive_id'].' (finalization #'.$summary['ledger']['latest_finalization_id'].')' : 'None retained' }}</dd></div>
            <div><dt>Latest record hash</dt><dd class="mono">{{ $summary['ledger']['latest_sha256'] ?? 'Not available' }}</dd></div>
            <div><dt>Previous record hash</dt><dd class="mono">{{ $summary['ledger']['previous_sha256'] ?? 'Not available' }}</dd></div>
            <div><dt>Last integrity verification</dt><dd>{{ $format($verification['created_at']) }}</dd></div>
            <div><dt>Verification result</dt><dd>{{ $verification['id'] ? $summary['label'] : 'No verification recorded' }}</dd></div>
        </dl>
        @if ($verification['stale'])
            <p class="monitor-note">The ledger changed after the last verification. Run a fresh verification to describe the current records.</p>
        @endif
        <p class="monitor-note">Verification is read-only. It never repairs, overwrites or deletes retained audit records, and it does not contact an external network.</p>
        <form method="POST" action="{{ route('besu-operations.verify') }}" class="monitor-action">
            @csrf
            <input type="hidden" name="request_key" value="{{ $requestKey }}">
            <button class="button button-primary">Verify ledger integrity</button>
            <span class="monitor-action-hint">Recomputes every preserved record digest and lineage link from current database bytes.</span>
        </form>
    </section>

    <div class="section-heading"><div><h2>Payroll hash anchoring</h2><p>Only reference metadata and digests are shown. Confidential payroll values are never loaded on this page.</p></div></div>
    <form method="GET" action="{{ route('besu-operations.index') }}" class="monitor-filters">
        <div><label for="anchor-q">Search</label><input id="anchor-q" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Payroll period, batch or hash"></div>
        <div><label for="anchor-status">Anchor status</label>
            <select id="anchor-status" name="status">
                <option value="">All statuses</option>
                @foreach ($qs::ANCHOR_STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $qs::anchorLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <div><label for="anchor-period">Payroll period</label><input id="anchor-period" name="period" value="{{ $filters['period'] ?? '' }}" placeholder="YYYY-MM"></div>
        <div><button class="button button-secondary">Apply filters</button></div>
    </form>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table>
            <caption class="sr-only">Payroll hash anchor records</caption>
            <thead><tr><th>Payroll reference</th><th>Certification reference</th><th>Algorithm</th><th>Payroll hash</th><th>Anchor status</th><th>Created</th><th>Anchored</th><th></th></tr></thead>
            <tbody>
            @forelse ($anchors as $anchor)
                <tr>
                    <td>{{ $anchor->period_start }} · Batch #{{ $anchor->batch_id }} · v{{ $anchor->payroll_version }}</td>
                    <td>Finalization #{{ $anchor->finalization_id }}</td>
                    <td>{{ $anchor->algorithm }}</td>
                    <td class="mono">{{ $anchor->sha256 }}</td>
                    <td>{{ $qs::anchorLabel($anchor->anchor_status) }}</td>
                    <td>{{ $format($anchor->created_at) }}</td>
                    <td>{{ $format($anchor->confirmed_at) }}</td>
                    <td><a href="{{ route('besu-operations.anchors.show', $anchor->id) }}" class="text-teal-800 underline">Inspect</a></td>
                </tr>
            @empty
                <tr><td colspan="8">No payroll hash anchors have been recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $anchors->links() }}</div>

    <div class="section-heading"><div><h2>Submission history</h2><p>Recorded submission attempts. A locally stored attempt is never presented as an externally confirmed blockchain transaction.</p></div></div>
    <form method="GET" action="{{ route('besu-operations.index') }}" class="monitor-filters">
        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
        <input type="hidden" name="status" value="{{ $filters['status'] ?? '' }}">
        <input type="hidden" name="period" value="{{ $filters['period'] ?? '' }}">
        <div><label for="submission-status">Submission status</label>
            <select id="submission-status" name="submission_status">
                <option value="">All statuses</option>
                @foreach ($qs::SUBMISSION_STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['submission_status'] ?? '') === $status)>{{ $qs::submissionLabel($status) }}</option>
                @endforeach
            </select>
        </div>
        <div><button class="button button-secondary">Filter submissions</button></div>
    </form>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table>
            <caption class="sr-only">Recorded blockchain submission attempts</caption>
            <thead><tr><th>Submission reference</th><th>Payroll reference</th><th>Status</th><th>Attempted</th><th>Processing result</th></tr></thead>
            <tbody>
            @forelse ($submissions as $submission)
                <tr>
                    <td>Attempt #{{ $submission->id }}</td>
                    <td>{{ $submission->period_start }} · Batch #{{ $submission->batch_id }} · v{{ $submission->payroll_version }}</td>
                    <td>{{ $qs::submissionLabel($submission->status) }}</td>
                    <td>{{ $format($submission->created_at) }}</td>
                    <td>{{ $qs::submissionResult($submission->status) }}
                        @if ($submission->network || $submission->transaction_reference)
                            <span class="mono block">{{ $submission->network }} {{ $submission->transaction_reference }}</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5">No submission attempts have been recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $submissions->links() }}</div>

    <div class="section-heading"><div><h2>Verification history</h2><p>Every authorized verification is retained and immutable. Its report describes the records inspected at that time.</p></div></div>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table>
            <caption class="sr-only">Retained ledger verification history</caption>
            <thead><tr><th>Verification</th><th>Date and time</th><th>Initiated by</th><th>Records checked</th><th>Result</th><th>Inconsistencies</th><th></th></tr></thead>
            <tbody>
            @forelse ($verifications as $record)
                <tr>
                    <td>#{{ $record->id }}</td>
                    <td>{{ $format($record->created_at) }}</td>
                    <td>Account #{{ $record->created_by }}</td>
                    <td>{{ number_format($record->records_checked) }}</td>
                    <td>{{ $record->status === 'verified' ? 'Verified' : 'Issues found' }}</td>
                    <td>{{ number_format($record->issues_found) }}</td>
                    <td><a href="{{ route('besu-operations.verifications.show', $record) }}" class="text-teal-800 underline">Report</a></td>
                </tr>
            @empty
                <tr><td colspan="7">No ledger verification has been recorded.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $verifications->links() }}</div>

    <div class="section-heading"><div><h2>Anchoring journal</h2><p>Existing automatic anchoring status is preserved here. Signed transaction bytes are never displayed.</p></div></div>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table>
            <caption class="sr-only">Automatic anchoring journal</caption>
            <thead><tr><th>Outbox</th><th>Status</th><th>Transaction</th><th>Block</th><th>Last check</th><th>Updated</th></tr></thead>
            <tbody>
            @forelse ($journal as $entry)
                <tr>
                    <td>#{{ $entry->id }}</td>
                    <td>{{ ucfirst($entry->status) }}</td>
                    <td class="mono">{{ $entry->transaction_hash ?? 'None' }}</td>
                    <td>{{ $entry->block_number !== null ? '#'.$entry->block_number : 'None' }}</td>
                    <td>{{ $entry->last_error ? str_replace('_', ' ', $entry->last_error) : 'No recorded error' }}
                        @if ($entry->retry_count)<span class="block">Incomplete checks: {{ $entry->retry_count }}</span>@endif
                    </td>
                    <td>{{ $format($entry->updated_at) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No blockchain submissions are queued.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $journal->links() }}</div>
@endsection
