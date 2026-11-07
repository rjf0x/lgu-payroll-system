@extends('layouts.app')

@section('title', 'Department')
@section('heading', $department->department_name)

@section('content')
    <div class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm lg:col-span-1">
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Code</dt>
                    <dd class="mt-1 font-mono text-xs">{{ $department->department_code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                    <dd class="mt-1"><x-status :value="$department->status" /></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                    <dd class="mt-1 text-slate-700">{{ $department->description ?: '—' }}</dd>
                </div>
            </dl>

            @can('employees.manage')
                <div class="mt-6 flex gap-3">
                    <a href="{{ route('departments.edit', $department) }}" class="ui-button ui-button-secondary">Edit</a>
                    <form method="POST" action="{{ route('departments.destroy', $department) }}" onsubmit="return confirm('Delete this department?')" data-submit-feedback>
                        @csrf
                        @method('DELETE')
                        <button class="ui-button ui-button-danger">Delete</button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="border-b border-slate-100 px-6 py-4 text-sm font-semibold text-slate-700">Positions</div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($department->positions as $position)
                    <li class="flex items-center justify-between px-6 py-3">
                        <a href="{{ route('positions.show', $position) }}" class="text-brand hover:underline">{{ $position->position_title }}</a>
                        <span class="text-xs text-slate-500">SG {{ $position->salary_grade ?? '—' }}</span>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-slate-500">No positions in this department.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
