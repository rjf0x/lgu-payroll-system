@extends('layouts.app')

@section('title', 'Deduction Types')
@section('heading', 'Deduction Types')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-500">Loan, contribution, GABE, and other deduction definitions.</p>
        @can('compensation.manage')
            <a href="{{ route('deduction-types.create') }}" class="rounded-md bg-emerald-700 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-800">+ Add Deduction Type</a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th class="px-4 py-3 text-left">Name</th>
                    <th class="px-4 py-3 text-left">Category</th>
                    <th class="px-4 py-3 text-left">Computation</th>
                    <th class="px-4 py-3 text-left">Statutory</th>
                    <th class="px-4 py-3 text-left">Pre-tax</th>
                    <th class="px-4 py-3 text-left">Status</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($deductionTypes as $deductionType)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $deductionType->deduction_name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $deductionType->deduction_category }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $deductionType->computation_type }}</td>
                        <td class="px-4 py-3">{{ $deductionType->is_statutory ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3">{{ $deductionType->is_pre_tax ? 'Yes' : 'No' }}</td>
                        <td class="px-4 py-3"><x-status :value="$deductionType->status" /></td>
                        <td class="px-4 py-3 text-right">
                            @can('compensation.manage')
                                <a href="{{ route('deduction-types.edit', $deductionType) }}" class="text-slate-600 hover:underline">Edit</a>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-500">No deduction types.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $deductionTypes->links() }}</div>
@endsection
