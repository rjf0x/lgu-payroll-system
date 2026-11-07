@extends('layouts.app')

@section('title', 'Earning Types')
@section('heading', 'Earning Types')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Allowance and earning definitions used by payroll.</p>
        @can('compensation.manage')
            <a href="{{ route('earning-types.create') }}" class="ui-button ui-button-primary">+ Add Earning Type</a>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$earningTypes" />
        <x-table label="Earning Types records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left">Name</th>
                    <th scope="col" class="px-4 py-3 text-left">Category</th>
                    <th scope="col" class="px-4 py-3 text-left">Computation</th>
                    <th scope="col" class="px-4 py-3 text-left">Taxable</th>
                    <th scope="col" class="px-4 py-3 text-left">Recurring</th>
                    <th scope="col" class="px-4 py-3 text-left">Status</th>
                    <th scope="col" class="px-4 py-3 text-right">Actions</th>
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
        </x-table>
    </div>

    <div class="mt-4">{{ $earningTypes->links() }}</div>
@endsection
