@extends('layouts.app')
@section('title', 'Verification report · LGU Payroll')
@section('content')
    @php
        $messages = \App\Services\LedgerIntegrityVerificationService::MESSAGES;
        $report = is_array($verification->report) ? $verification->report : [];
        $issues = $report['issues'] ?? [];
        $typeLabels = [
            'invalid_record_hash' => 'Invalid record hash',
            'modified_source_snapshot' => 'Modified audit record',
            'broken_reference' => 'Broken chain reference',
            'unsupported_record_format' => 'Unsupported record format',
        ];
    @endphp
    <a href="{{ route('besu-operations.index') }}" class="text-teal-800 underline">Blockchain operations</a>
    <h1 class="mt-5 text-3xl font-bold">Ledger verification #{{ $verification->id }}</h1>
    <p class="mt-3">{{ $verification->status === 'verified' ? 'No inconsistencies were detected in the records inspected.' : 'Inconsistencies were detected in the retained ledger. No record was repaired or modified.' }}</p>

    <dl class="monitor-ledger mt-5">
        <div><dt>Verification result</dt><dd>{{ $verification->status === 'verified' ? 'Verified' : 'Issues found' }}</dd></div>
        <div><dt>Date and time</dt><dd>{{ $verification->created_at->format('Y-m-d H:i:s') }}</dd></div>
        <div><dt>Initiated by</dt><dd>Account #{{ $verification->created_by }}</dd></div>
        <div><dt>Records checked</dt><dd>{{ number_format($verification->records_checked) }}</dd></div>
        <div><dt>Inconsistencies</dt><dd>{{ number_format($verification->issues_found) }}</dd></div>
    </dl>

    @if (! empty($report['note']))
        <p class="monitor-note">{{ $report['note'] }}</p>
    @endif

    <h2 class="mt-8 text-xl font-semibold">Detailed report</h2>
    <div class="mt-3 overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table>
            <caption class="sr-only">Detected ledger inconsistencies</caption>
            <thead><tr><th>Type</th><th>Reference</th><th>Finalization</th><th>Detail</th></tr></thead>
            <tbody>
            @forelse ($issues as $issue)
                <tr>
                    <td>{{ $typeLabels[$issue['type']] ?? 'Inconsistency' }}</td>
                    <td class="mono">{{ $issue['reference'] ?? 'Unknown' }}</td>
                    <td>{{ $issue['finalization_id'] ? '#'.$issue['finalization_id'] : 'Not applicable' }}</td>
                    <td>{{ $messages[$issue['type']] ?? ($issue['message'] ?? 'Review required.') }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No inconsistencies were recorded by this verification.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    @if (! empty($report['truncated']))
        <p class="monitor-note">Only the first 200 inconsistencies are listed here. The total count above is complete. Detailed investigation is required.</p>
    @endif
    <p class="monitor-note">This report describes the records inspected at the recorded time. It is not continuous monitoring and it does not establish external blockchain confirmation.</p>
@endsection
