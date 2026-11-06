@extends('layouts.app')

@section('title', 'Earning Types')
@section('heading', 'Earning Types')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Allowance and earning definitions used by payroll.</p>
        @can('compensation.manage')
            <a href="{{ route('earning-types.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Earning Type</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Computation</th>
                    <th class="px-4 py-3 text-left">Taxable</th>
                    <th class="px-4 py-3 text-left">Recurring</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($earningTypes as $earningType)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $earningType->earning_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $earningType->earning_category }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $earningType->computation_type }}</td>
                        <td class="px-4 py-3">{{ $earningType->is_taxable ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $earningType->is_recurring ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3"><x-status :value="$earningType->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <a href="{{ route('earning-types.edit', $earningType) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No earning types.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $earningTypes->links() }}</div>
@endsection
