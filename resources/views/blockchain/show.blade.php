@extends('layouts.app')

@section('title', 'Anchor Detail')
@section('heading', 'Blockchain Anchor · '.$anchor->payrollRun->payrollPeriod->period_name.' #'.$anchor->payrollRun->run_number)

@section('content')
    @php($latestCheck = $anchor->verificationLogs->first())
    <x-card heading="Integrity verification" class="mb-6">
        @if ($anchor->anchor_status === 'PENDING')
            <p class="mb-3">The transaction is awaiting confirmation. Check again after Besu includes it in a block.</p>
        @elseif ($anchor->anchor_status === 'FAILED')
            <x-alert variant="error">The anchoring transaction failed. Contact the administrator to reconcile it before resubmission.</x-alert>
        @elseif ($latestCheck)
            <p class="mb-3"><x-status :value="$latestCheck->verification_result" /> ? Last checked {{ $latestCheck->verified_at?->format('M d, Y g:i A') }} {{ config('app.timezone') }}</p>
            <p>{{ $latestCheck->verification_result === 'MATCH' ? 'At the last check, payroll data matched the anchored hash.' : 'At the last check, payroll data or the local anchor hash differed from the anchored evidence. Ask the administrator to investigate.' }}</p>
        @else
            <p>No integrity check has been recorded for this anchor.</p>
        @endif
        @if (! $anchor->contract_address)
            <p class="mt-3 font-semibold text-amber-900">Simulated proof: this checks local records and provides no independent blockchain evidence.</p>
        @else
            <p class="mt-3 text-muted">Verification reads the confirmed transaction and contract state from the configured Besu node.</p>
        @endif
        <p class="mt-3 text-muted">A previous result describes that check only. Verify again to check the current payroll data.</p>
        <form method="POST" action="{{ route('blockchain-anchors.verify', $anchor) }}" class="mt-4" data-submit-feedback>
            @csrf
            <x-button type="submit" variant="primary">{{ $anchor->anchor_status === 'PENDING' ? 'Check confirmation and verify' : 'Verify integrity' }}</x-button>
        </form>
    </x-card>
    <div class="mb-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1 font-semibold">{{ $anchor->anchor_status }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Network</dt>
                <dd class="mt-1">{{ $anchor->network_name }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Hash Algorithm</dt>
                <dd class="mt-1">{{ $anchor->hash_algorithm }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Anchored By</dt>
                <dd class="mt-1">{{ $anchor->anchoredBy?->username }} · {{ $anchor->anchored_at?->format('M d, Y g:i A') }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Block Number</dt>
                <dd class="mt-1 font-mono">{{ $anchor->block_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contract</dt>
                <dd class="mt-1 font-mono text-xs">{{ $anchor->contract_address ?? '—' }}</dd>
            </div>
        </dl>

        <div class="mt-5 space-y-3 text-sm">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Stored Payload Hash</dt>
                <dd class="mt-1 break-all rounded-md bg-slate-50 px-3 py-2 font-mono text-xs">{{ $anchor->payload_hash }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Transaction ID</dt>
                <dd class="mt-1 break-all rounded-md bg-slate-50 px-3 py-2 font-mono text-xs">{{ $anchor->transaction_id ?? '—' }}</dd>
            </div>
        </div>


    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Verification History</div>
        <x-table label="Anchor Detail records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-2 text-left">Result</th>
                    <th scope="col" class="px-5 py-2 text-left">Verified By</th>
                    <th scope="col" class="px-5 py-2 text-left">When</th>
                    <th scope="col" class="px-5 py-2 text-left">Calculated Hash</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($anchor->verificationLogs as $log)
                    <tr>
                        <td class="px-5 py-2">
                            <x-status :value="$log->verification_result" />
                        </td>
                        <td class="px-5 py-2 text-slate-600">{{ $log->verifiedBy?->username }}</td>
                        <td class="px-5 py-2 text-slate-500">{{ $log->verified_at?->format('M d, Y g:i A') }}</td>
                        <td class="px-5 py-2 font-mono text-xs">{{ substr($log->calculated_hash, 0, 24) }}…</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">No verifications yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>
@endsection
