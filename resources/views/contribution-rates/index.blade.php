@extends('layouts.app')

@section('title', 'Contribution Rates')
@section('heading', 'Contribution Rates')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Effective-dated GSIS, PhilHealth, and Pag-IBIG rates.</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('contribution-rates.create') }}">+ Add Rate</x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$rates" />
        <x-table label="Contribution Rates records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Contribution</th>
                    <th scope="col" class="px-4 py-3 text-right">Min</th>
                    <th scope="col" class="px-4 py-3 text-right">Max</th>
                    <th scope="col" class="px-4 py-3 text-right">Employee</th>
                    <th scope="col" class="px-4 py-3 text-right">Employer</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($rates as $rate)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $rate->contribution_type }}</td>
                        <td class="px-4 py-3 text-right font-mono">₱{{ number_format((float) $rate->minimum_compensation, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ $rate->maximum_compensation !== null ? '₱'.number_format((float) $rate->maximum_compensation, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $rate->employee_rate * 100, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $rate->employer_rate * 100, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-3 text-slate-600">{{ $rate->effective_from?->format('M d, Y') }} – {{ $rate->effective_to?->format('M d, Y') ?? 'present' }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <x-button icon="edit" variant="secondary" href="{{ route('contribution-rates.edit', $rate) }}">Edit</x-button>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No contribution rates.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $rates->links() }}</div>
@endsection
