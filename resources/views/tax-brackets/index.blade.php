@extends('layouts.app')

@section('title', 'Tax Brackets')
@section('heading', 'Tax Brackets')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Effective-dated withholding tax brackets.</p>
        @can('compensation.manage')
            <a href="{{ route('tax-brackets.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Bracket</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Tax Type</th>
                    <th class="px-4 py-3 text-left">Frequency</th>
                    <th class="px-4 py-3 text-right">Min Income</th>
                    <th class="px-4 py-3 text-right">Max Income</th>
                    <th class="px-4 py-3 text-right">Base Tax</th>
                    <th class="px-4 py-3 text-right">Marginal Rate</th>
                    <th class="px-4 py-3 text-left">Effective</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($brackets as $bracket)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $bracket->tax_type }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $bracket->pay_frequency }}</td>
                        <td class="px-4 py-3 text-right font-mono">₱{{ number_format((float) $bracket->minimum_income, 2) }}</td>
                        <td class="px-4 py-3 text-right font-mono">{{ $bracket->maximum_income !== null ? '₱'.number_format((float) $bracket->maximum_income, 2) : '—' }}</td>
                        <td class="px-4 py-3 text-right font-mono">₱{{ number_format((float) $bracket->base_tax, 2) }}</td>
                        <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $bracket->marginal_tax_rate * 100, 2), '0'), '.') }}%</td>
                        <td class="px-4 py-3 text-slate-600">{{ $bracket->effective_from?->format('M d, Y') }}</td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <a href="{{ route('tax-brackets.edit', $bracket) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-500">No tax brackets.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $brackets->links() }}</div>
@endsection
