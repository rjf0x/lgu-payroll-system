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
                    <a href="{{ route('departments.show', $position->department) }}" class="text-brand hover:underline">
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
                <a href="{{ route('positions.edit', $position) }}" class="ui-button ui-button-secondary">Edit</a>
                <form method="POST" action="{{ route('positions.destroy', $position) }}" onsubmit="return confirm('Delete this position?')" data-submit-feedback>
                    @csrf
                    @method('DELETE')
                    <button class="ui-button ui-button-danger">Delete</button>
                </form>
            </div>
        @endcan
    </div>
@endsection
