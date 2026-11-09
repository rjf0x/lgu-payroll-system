@props(['id', 'heading', 'records', 'route', 'employee', 'kind'])

<x-card :id="$id" :heading="$heading" class="scroll-mt-6">
    <p class="mb-3 text-sm text-muted">Five most recent records by effective date. Future and expired records may appear.</p>
    <ul class="divide-y divide-slate-200">
        @forelse ($records as $record)
            <li class="py-3 text-sm">
                <p class="font-semibold">{{ $kind === 'compensation' ? str_replace('_', ' ', $record->pay_basis) : ($kind === 'earnings' ? $record->earningType->earning_name : $record->deductionType->deduction_name) }}</p>
                <p class="text-muted">{{ $record->employmentRecord->position->position_title }} · Effective {{ $record->effective_from?->format('M d, Y') }}{{ $record->effective_to ? ' to '.$record->effective_to->format('M d, Y') : ' onwards' }}</p>
                @if ($kind === 'compensation')
                    @php($rate = $record->basic_rate ?? $record->gradeStep?->monthly_salary)
                    <p class="mt-1 tabular-nums">{{ $rate !== null ? 'Basic rate ₱'.number_format((float) $rate, 2) : 'Rate unavailable' }} · Service fraction {{ $record->service_fraction }}</p>
                    <x-button icon="view" variant="secondary" href="{{ route('compensation-profiles.show', $record) }}">View compensation profile</x-button>
                    @can('compensation.manage')
                        <x-button icon="edit" variant="secondary" href="{{ route('compensation-profiles.edit', [$record, 'employee_workspace' => 1]) }}">Edit profile</x-button>
                    @endcan
                @else
                    <p class="mt-1 tabular-nums">{{ $record->amount !== null ? '₱'.number_format((float) $record->amount, 2) : 'No fixed amount' }}{{ $record->percentage !== null ? ' · '.number_format((float) $record->percentage, 2).'%' : '' }}</p>
                    @if ($kind === 'earnings')
                        <p class="mt-1 text-muted">Frequency: {{ \Illuminate\Support\Str::headline(strtolower($record->frequency)) }}</p>
                    @else
                        <div class="mt-2"><x-status :value="$record->status" /></div>
                    @endif
                    @can('compensation.manage')
                        <x-button icon="edit" variant="secondary" href="{{ route($route.'.edit', [$record, 'employee_workspace' => 1]) }}">Edit assignment</x-button>
                    @endcan
                @endif
            </li>
        @empty
            <li class="py-4 text-sm text-muted">No {{ strtolower($heading) }} records for this employee.</li>
        @endforelse
    </ul>
    <x-button icon="view" :href="route($route.'.index', ['employee_id' => $employee->employee_id])" class="mt-3">View all {{ strtolower($heading) }}</x-button>
</x-card>
