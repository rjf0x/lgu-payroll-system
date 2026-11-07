@extends('layouts.app')

@section('title', 'Blockchain Verification')
@section('heading', 'Blockchain Verification')

@section('content')
    <p class="mb-4 text-sm text-slate-500">
        Only a deterministic hash of finalized payroll is anchored. No employee or payroll detail is placed on-chain.
    </p>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Period / Run</th>
                    <th class="px-4 py-3 text-left">Network</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-left">Anchored By</th>
                    <th class="px-4 py-3 text-left">Anchored At</th>
                    <th class="px-4 py-3 text-left">Hash</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($anchors as $anchor)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $anchor->payrollRun->payrollPeriod->period_name }} #{{ $anchor->payrollRun->run_number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $anchor->network_name }}</td>
                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">{{ $anchor->anchor_status }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $anchor->anchoredBy?->username }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $anchor->anchored_at?->format('M d, Y g:i A') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ substr($anchor->payload_hash, 0, 16) }}…</td>
                        <td class="px-4 py-3 text-right"><a href="{{ route('blockchain-anchors.show', $anchor) }}" class="text-emerald-700 hover:underline">View</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No anchored payroll yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $anchors->links() }}</div>
@endsection
