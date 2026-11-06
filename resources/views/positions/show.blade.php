@extends('layouts.app')

@section('title', 'Position')
@section('heading', $position->position_title)

@section('content')
    <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <dl class="grid grid-cols-1 gap-5 sm:grid-cols-3">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Code</dt>
                <dd class="mt-1 font-mono text-xs">{{ $position->position_code }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Department</dt>
                <dd class="mt-1">
                    <a href="{{ route('departments.show', $position->department) }}" class="text-emerald-700 hover:underline">
                        {{ $position->department->department_name }}
                    </a>
                </dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Salary Grade</dt>
                <dd class="mt-1">{{ $position->salary_grade ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Status</dt>
                <dd class="mt-1"><x-status :value="$position->status" /></dd>
            </div>
            <div class="sm:col-span-2">
                <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Description</dt>
                <dd class="mt-1 text-slate-700">{{ $position->description ?: '—' }}</dd>
            </div>
        </dl>

        @can('employees.manage')
            <div class="mt-6 flex gap-3">
                <a href="{{ route('positions.edit', $position) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Edit</a>
                <form method="POST" action="{{ route('positions.destroy', $position) }}" onsubmit="return confirm('Delete this position?')">
                    @csrf
                    @method('DELETE')
                    <button class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Delete</button>
                </form>
            </div>
        @endcan
    </div>
@endsection
