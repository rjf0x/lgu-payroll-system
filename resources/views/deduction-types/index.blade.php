@extends('layouts.app')

@section('title', 'Deduction Types')
@section('heading', 'Deduction Types')

@section('content')
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <p class="text-sm text-slate-500">Loan, contribution, GABE, and other deduction definitions.</p>
        @can('compensation.manage')
            <x-button icon="add" variant="primary" href="{{ route('deduction-types.create') }}">Add Deduction Type</x-button>
        @endcan
    </div>

    <div class="min-w-0">
        <x-results :items="$deductionTypes" />
        <x-table label="Deduction Types records">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-4 py-3 text-left ui-table-primary">Name</th>
                    <th scope="col" class="px-4 py-3 text-left">Category</th>
                    <th scope="col" class="px-4 py-3 text-left">Computation</th>
                    <th scope="col" class="px-4 py-3 text-left">Statutory</th>
                    <th scope="col" class="px-4 py-3 text-left">Pre-tax</th>
                    <th scope="col" class="px-4 py-3 text-center">Status</th>
                    <th scope="col" class="px-4 py-3 text-right ui-table-actions">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($deductionTypes as $deductionType)
                    <tr>
                        <td class="px-4 py-3 ui-table-primary">{{ $deductionType->deduction_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $deductionType->deduction_category }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $deductionType->computation_type }}</td>
                        <td class="px-4 py-3">{{ $deductionType->is_statutory ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $deductionType->is_pre_tax ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3 text-center"><x-status :value="$deductionType->status" /></td>
                        <td class="px-4 py-3 text-right ui-table-actions">
                            <x-action-menu :label="'Actions for '.$deductionType->deduction_name">
                                @can('compensation.manage')
                                    <x-button icon="edit" variant="secondary" href="{{ route('deduction-types.edit', $deductionType) }}">Edit</x-button>
                                @endcan
                            </x-action-menu>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No deduction types.</td></tr>
                @endforelse
            </tbody>
        </x-table>
    </div>

    <div class="mt-4">{{ $deductionTypes->links() }}</div>
@endsection
