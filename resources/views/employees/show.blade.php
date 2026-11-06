@extends('layouts.app')

@section('title', 'Employee')
@section('heading', $employee->full_name)

@section('content')
    <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <dl class="space-y-4 text-sm">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Employee Number</dt>
                    <dd class="mt-1 font-mono text-xs">{{ $employee->employee_number }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Sex</dt>
                    <dd class="mt-1">{{ $employee->sex }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Civil Status</dt>
                    <dd class="mt-1">{{ $employee->civil_status ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Birth Date</dt>
                    <dd class="mt-1">{{ $employee->birth_date?->format('M d, Y') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact</dt>
                    <dd class="mt-1">{{ $employee->email ?: '—' }} · {{ $employee->contact_number ?: '—' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Address</dt>
                    <dd class="mt-1 text-slate-700">{{ $employee->address }}</dd>
                </div>
            </dl>

            @can('employees.manage')
                <div class="mt-6 flex gap-3">
                    <a href="{{ route('employees.edit', $employee) }}" class="rounded-md border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">Edit</a>
                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Archive this employee?')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-md border border-rose-200 px-3 py-2 text-xs font-semibold text-rose-600 hover:bg-rose-50">Archive</button>
                    </form>
                </div>
            @endcan
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                <span class="text-sm font-semibold text-slate-700">Employment Records</span>
                @can('employees.manage')
                    <a href="{{ route('employment-records.create', ['employee_id' => $employee->employee_id]) }}" class="text-xs font-semibold text-emerald-700 hover:underline">+ Add</a>
                @endcan
            </div>
            <ul class="divide-y divide-slate-100 text-sm">
                @forelse ($employee->employmentRecords as $record)
                    <li class="px-6 py-3">
                        <a href="{{ route('employment-records.show', $record) }}" class="font-medium text-emerald-700 hover:underline">
                            {{ $record->position->position_title }}
                        </a>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ $record->department->department_name }} · {{ $record->employmentType->employment_type_name }}
                            · Started {{ $record->start_date?->format('M d, Y') }}
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-8 text-center text-slate-500">No employment records.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
