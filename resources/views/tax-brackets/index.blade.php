@extends('layouts.app')

@section('title', 'Tax Brackets')
@section('heading', 'Tax Brackets')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Effective-dated withholding tax brackets.</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('tax-brackets.create') }}">Add Bracket</x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$brackets" />
        <x-table label="Tax Brackets records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Tax Type</th>
                    <th scope="col" class="px-4 py-3 text-left">Frequency</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Min Income</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Max Income</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Base Tax</th>
                    <th scope="col" class="px-4 py-3 text-right tabular-nums">Marginal Rate</th>
                    <th scope="col" class="px-4 py-3 text-left">Effective</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($brackets as $bracket)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">{{ $bracket->tax_type }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $bracket->pay_frequency }}</td>
                        <td class="px-4 py-3 font-mono text-right tabular-nums">₱{{ number_format((float) $bracket->minimum_income, 2) }}</td>
                        <td class="px-4 py-3 font-mono text-right tabular-nums">{{ $bracket->maximum_income !== null ? '₱'.number_format((float) $bracket->maximum_income, 2) : '—' }}</td>
                        <td class="px-4 py-3 font-mono text-right tabular-nums">₱{{ number_format((float) $bracket->base_tax, 2) }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rtrim(rtrim(number_format((float) $bracket->marginal_tax_rate * 100, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-3 text-slate-600">{{ $bracket->effective_from?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for tax bracket #'.$bracket->getKey()">
                                @can('compensation.manage')
                                    <x-button icon="edit" variant="secondary" href="{{ route('tax-brackets.edit', $bracket) }}">Edit</x-button>
                                @endcan
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No tax brackets.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $brackets->links() }}</div>
@endsection
