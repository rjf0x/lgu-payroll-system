@extends('layouts.app')

@section('title', 'Payroll Integrity Verification')
@section('heading')
    Payroll Integrity · {{ $registration->period_snapshot['period_name'] }} #{{ $registration->payrollRun->run_number }}
@endsection

@section('content')
    @php($latestCheck = $registration->latestVerification)
    <div class="mb-5 flex flex-wrap gap-3">
        <x-button :href="route('blockchain.index')">All integrity records</x-button>
        @can('payroll.view')
            <x-button :href="route('payroll-runs.show', $registration->payrollRun)">View payroll</x-button>
        @endcan
    </div>

    <x-card heading="Payroll integrity" class="mb-6">
        @if ($latestCheck)
            @php($variant = match ($latestCheck->verification_result) { 'MATCH' => 'success', 'MISMATCH', 'INVALID_EVIDENCE' => 'error', default => 'warning' })
            <x-alert :variant="$variant">
                <p class="mb-2 font-semibold"><x-status :value="$latestCheck->verification_result" /></p>
                <p>{{ $latestCheck->remarks }}</p>
            </x-alert>
            <p class="mt-3 text-sm text-muted">Last checked {{ $latestCheck->verified_at->format('M d, Y g:i A') }} {{ config('app.timezone') }} by {{ $latestCheck->verifiedBy?->username ?? 'Unavailable user' }}.</p>
        @else
            <p>No integrity check has been recorded for this payroll registration.</p>
        @endif
        <p class="mt-3 text-sm text-muted">Each result describes that check only. Verify again to check the current payroll data against fresh blockchain evidence.</p>
        <form method="POST" action="{{ route('payroll-registrations.verify', $registration) }}" class="mt-4" data-submit-feedback>
            @csrf
            <x-button type="submit" variant="primary">Verify current payroll</x-button>
        </form>
    </x-card>

    <x-card heading="Registration" class="mb-6">
        <dl class="grid grid-cols-1 gap-5 text-sm sm:grid-cols-2">
            <div><dt class="font-semibold">Submission status</dt><dd class="mt-1"><x-status :value="$registration->status" /></dd></div>
            <div><dt class="font-semibold">Revision</dt><dd class="mt-1">{{ $registration->revision }}</dd></div>
            <div class="min-w-0"><dt class="font-semibold">Payroll reference</dt><dd class="mt-1 font-mono wrap-anywhere">{{ $registration->payroll_reference }}</dd></div>
            <div><dt class="font-semibold">Hash format</dt><dd class="mt-1">SHA-256 · Version {{ $registration->hash_schema_version }}</dd></div>
        </dl>
        <p class="mt-4 text-sm text-muted">Submission status tracks registration processing. An integrity result requires a separate verification. Verification does not resubmit or replace payroll data.</p>
        <details class="mt-5">
            <summary class="cursor-pointer rounded-md py-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">Registration evidence</summary>
            <dl class="mt-3 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2">
                <div><dt class="font-semibold">Chain ID</dt><dd class="mt-1">{{ $registration->target_network['chain_id'] }}</dd></div>
                <div class="min-w-0"><dt class="font-semibold">Contract</dt><dd class="mt-1 font-mono text-xs wrap-anywhere">{{ $registration->target_network['contract_address'] }}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="font-semibold">Captured payroll hash</dt><dd class="mt-1 font-mono text-xs wrap-anywhere">{{ $registration->payload_hash }}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="font-semibold">Blockchain hash at last check</dt><dd class="mt-1 font-mono text-xs wrap-anywhere">{{ $latestCheck?->stored_hash ?? 'Not available for the last check' }}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="font-semibold">Current payroll hash at last check</dt><dd class="mt-1 font-mono text-xs wrap-anywhere">{{ $latestCheck?->calculated_hash ?? 'Not calculated for the last check' }}</dd></div>
                <div class="min-w-0 sm:col-span-2"><dt class="font-semibold">Transaction at last check</dt><dd class="mt-1 font-mono text-xs wrap-anywhere">{{ $latestCheck?->evidence['transaction_hash'] ?? 'Not available for the last check' }}</dd></div>
                <div><dt class="font-semibold">Block at last check</dt><dd class="mt-1">{{ $latestCheck?->evidence['block_number'] ?? 'Unavailable' }}</dd></div>
                <div><dt class="font-semibold">Blockchain timestamp (Unix seconds)</dt><dd class="mt-1">{{ $latestCheck?->evidence['registered_at'] ?? 'Unavailable' }}</dd></div>
            </dl>
        </details>
    </x-card>

    <x-card heading="Verification history">
        <x-results :items="$checks" />
        <x-table label="Payroll integrity verification history">
            <thead><tr>
                <th scope="col">Result</th>
                <th scope="col">Checked by</th>
                <th scope="col">When</th>
                <th scope="col">Finding</th>
            </tr></thead>
            <tbody>
                @forelse ($checks as $check)
                    <tr>
                        <td><x-status :value="$check->verification_result" /></td>
                        <td>{{ $check->verifiedBy?->username ?? 'Unavailable user' }}</td>
                        <td>{{ $check->verified_at->format('M d, Y g:i A') }} {{ config('app.timezone') }}</td>
                        <td class="min-w-0">
                            <p>{{ $check->remarks }}</p>
                            <details class="mt-2">
                                <summary class="cursor-pointer rounded-md py-3 font-semibold focus-visible:outline-2 focus-visible:outline-offset-2">Compare recorded hashes</summary>
                                <dl class="mt-2 space-y-2 text-xs">
                                    <div><dt class="font-semibold">Blockchain</dt><dd class="font-mono wrap-anywhere">{{ $check->stored_hash ?? 'Unavailable' }}</dd></div>
                                    <div><dt class="font-semibold">Current payroll</dt><dd class="font-mono wrap-anywhere">{{ $check->calculated_hash ?? 'Not calculated' }}</dd></div>
                                    <div><dt class="font-semibold">Captured hash</dt><dd class="font-mono wrap-anywhere">{{ $check->captured_hash }}</dd></div>
                                </dl>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-8 text-center text-muted">No verification attempts yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="mt-4">{{ $checks->links() }}</div>
    </x-card>
@endsection
