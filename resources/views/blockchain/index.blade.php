@extends('layouts.app')

@section('title', 'Payroll Verification')
@section('heading', 'Payroll Verification')

@section('content')
    <p class="mb-4 text-sm text-slate-500">
        Verify current payroll data against its registered blockchain version. Employee and payroll details stay in MySQL.
    </p>

    <section aria-labelledby="registry-heading" class="mb-8 min-w-0">
        <h2 id="registry-heading" class="ui-section-heading mb-4">Payroll registrations</h2>
        <x-results :items="$registrations" />
        <x-table label="Payroll integrity registrations">
            <thead><tr>
                <th scope="col" class="ui-table-primary">Period / Run</th>
                <th scope="col" class="text-right tabular-nums">Revision</th>
                <th scope="col" class="text-center">Submission</th>
                <th scope="col">Last integrity check</th>
                <th scope="col" class="text-right ui-table-actions">Actions</th>
            </tr></thead>
            <tbody>
                @forelse ($registrations as $registration)
                    <tr>
                        <td class="ui-table-primary">{{ $registration->period_snapshot['period_name'] }} #{{ $registration->payrollRun->run_number }}</td>
                        <td class="text-right tabular-nums">{{ $registration->revision }}</td>
                        <td class="text-center"><x-status :value="$registration->status" /></td>
                        <td>
                            @if ($registration->latestVerification)
                                <x-status :value="$registration->latestVerification->verification_result" />
                                <p class="ui-table-secondary"><x-local-time :value="$registration->latestVerification->verified_at" show-timezone /></p>
                            @else
                                Not checked
                            @endif
                        </td>
                        <td class="text-right ui-table-actions"><x-action-menu :label="'Actions for payroll registration #'.$registration->getKey()"><x-button icon="view" :href="route('payroll-registrations.show', $registration)">View &amp; verify</x-button></x-action-menu></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-8 text-center text-muted">No versioned payroll registrations yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
        <div class="mt-4">{{ $registrations->links() }}</div>
    </section>

    <h2 class="ui-section-heading mb-4">Legacy anchors</h2>

    <div class="min-w-0">
        <x-results :items="$anchors" />
        <x-table label="Payroll Verification records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Period / Run</th>
                    <th scope="col" class="px-4 py-3 text-left">Network</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-left">Anchored By</th>
                    <th scope="col" class="px-4 py-3 text-left">Anchored At</th>
                    <th scope="col" class="px-4 py-3 text-left">Hash</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($anchors as $anchor)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">{{ $anchor->payrollRun->payrollPeriod->period_name }} #{{ $anchor->payrollRun->run_number }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $anchor->network_name }}<p class="ui-table-secondary">{{ $anchor->contract_address ? 'Legacy blockchain proof' : 'Simulated local proof' }}</p></td>
                        <td class="px-4 py-3 text-center">
                            <x-status :value="$anchor->anchor_status" />
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $anchor->anchoredBy?->username }}</td>
                        <td class="px-4 py-3 text-slate-500"><x-local-time :value="$anchor->anchored_at" /></td>
                        <td class="px-4 py-3 font-mono text-xs">{{ substr($anchor->payload_hash, 0, 16) }}…</td>
                        <td class="px-4 py-3 text-right ui-table-actions"><x-action-menu :label="'Actions for payroll anchor #'.$anchor->getKey()"><x-button icon="view" variant="secondary" href="{{ route('blockchain-anchors.show', $anchor) }}">View</x-button></x-action-menu></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No anchored payroll yet.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $anchors->links() }}</div>
@endsection
