@extends('layouts.app')

@section('title', 'Anchor Detail')
@section('heading', 'Blockchain Anchor · '.$anchor->payrollRun->payrollPeriod->period_name.' #'.$anchor->payrollRun->run_number)

@section('content')
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

        <form method="POST" action="{{ route('blockchain-anchors.verify', $anchor) }}" class="mt-6">
            @csrf
            <button class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-semibold text-white hover:bg-emerald-800">Verify integrity</button>
        </form>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Verification History</div>
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-5 py-2 text-left">Result</th>
                    <th class="px-5 py-2 text-left">Verified By</th>
                    <th class="px-5 py-2 text-left">When</th>
                    <th class="px-5 py-2 text-left">Calculated Hash</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($anchor->verificationLogs as $log)
                    <tr>
                        <td class="px-5 py-2">
                            <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold {{ $log->verification_result === 'MATCH' ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $log->verification_result }}
                            </span>
                        </td>
                        <td class="px-5 py-2 text-slate-600">{{ $log->verifiedBy?->username }}</td>
                        <td class="px-5 py-2 text-slate-500">{{ $log->verified_at?->format('M d, Y g:i A') }}</td>
                        <td class="px-5 py-2 font-mono text-xs">{{ substr($log->calculated_hash, 0, 24) }}…</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-6 text-center text-slate-500">No verifications yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
