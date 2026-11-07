@extends('layouts.app')
@section('title', 'Employee')
@section('heading', $employee->full_name)
@section('content')
    <x-page-header :description="'Employee workspace · '.$employee->employee_number">
        <x-button :href="route('employees.index', $directoryContext)">Back to directory</x-button>
        @can('employees.manage')
            <x-button :href="route('employees.edit', $employee)" variant="primary">Edit employee</x-button>
        @endcan
    </x-page-header>
    <nav aria-label="Employee sections" class="mb-6 flex flex-wrap gap-3 text-sm">
        @foreach (['employment' => 'Employment', 'compensation' => 'Compensation', 'earnings' => 'Earnings', 'deductions' => 'Deductions'] as $anchor => $label)
            <a href="#{{ $anchor }}" class="ui-button ui-button-secondary">{{ $label }}</a>
        @endforeach
        @can('attendance.manage')<a href="#attendance" class="ui-button ui-button-secondary">Attendance</a>@endcan
        @can('leave.manage')<a href="#leave" class="ui-button ui-button-secondary">Leave</a>@endcan
    </nav>
    <div class="grid min-w-0 gap-6 xl:grid-cols-3">
        <div class="min-w-0">
            <x-card heading="Personal details">
                <dl class="space-y-4 text-sm">
                    @foreach (['Employee number' => $employee->employee_number, 'Sex' => $employee->sex, 'Civil status' => $employee->civil_status, 'Birth date' => $employee->birth_date?->format('M d, Y'), 'Email' => $employee->email, 'Contact number' => $employee->contact_number, 'Address' => $employee->address] as $label => $value)
                        <div><dt class="text-muted">{{ $label }}</dt><dd class="mt-1 break-words font-medium">{{ $value ?: 'Not provided' }}</dd></div>
                    @endforeach
                </dl>
                @can('employees.manage')
                    <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="mt-5" onsubmit="return confirm('Archive this employee?')" data-submit-feedback>
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="danger">Archive</x-button>
                    </form>
                @endcan
            </x-card>
        </div>
        <div class="min-w-0 space-y-6 xl:col-span-2">
            <x-card id="employment" heading="Employment Records" class="scroll-mt-6">
                @can('employees.manage')
                    <x-button :href="route('employment-records.create', ['employee_id' => $employee->employee_id, 'employee_workspace' => 1])" class="mb-3">Add employment</x-button>
                @endcan
                <ul class="divide-y divide-slate-200">
                    @forelse ($employee->employmentRecords as $record)
                        <li class="py-4 text-sm">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <a href="{{ route('employment-records.show', $record) }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:underline">{{ $record->position->position_title }}</a>
                                <x-status :value="$record->employment_status" />
                            </div>
                            <p class="text-muted">{{ $record->department->department_name }} · {{ $record->employmentType->employment_type_name }} · Started {{ $record->start_date?->format('M d, Y') }}</p>
                            @can('compensation.manage')
                                <div class="mt-3 flex flex-wrap gap-2" aria-label="Add related records for {{ $record->position->position_title }}">
                                    <x-button :href="route('compensation-profiles.create', ['employment_id' => $record->employment_id, 'employee_workspace' => 1])">Add compensation</x-button>
                                    <x-button :href="route('employee-earning-assignments.create', ['employment_id' => $record->employment_id, 'employee_workspace' => 1])">Add earning</x-button>
                                    <x-button :href="route('employee-deduction-assignments.create', ['employment_id' => $record->employment_id, 'employee_workspace' => 1])">Add deduction</x-button>
                                </div>
                            @endcan
                        </li>
                    @empty
                        <li class="py-4 text-sm text-muted">No employment records. Add an employment record before assigning compensation, earnings, or deductions.</li>
                    @endforelse
                </ul>
            </x-card>
            <x-employee-assignment-section id="compensation" heading="Compensation" :records="$compensation" route="compensation-profiles" :employee="$employee" kind="compensation" />
            <x-employee-assignment-section id="earnings" heading="Earnings" :records="$earnings" route="employee-earning-assignments" :employee="$employee" kind="earnings" />
            <x-employee-assignment-section id="deductions" heading="Deductions" :records="$deductions" route="employee-deduction-assignments" :employee="$employee" kind="deductions" />
            @can('attendance.manage')
                <x-card id="attendance" heading="Attendance" class="scroll-mt-6">
                    <div class="mb-4 flex flex-wrap gap-3">
                        <x-button :href="route('attendance-records.create', ['employee_id' => $employee->employee_id, 'employee_workspace' => 1])">Record attendance</x-button>
                        <x-button :href="route('attendance-records.index', ['employee_id' => $employee->employee_id])">View all attendance</x-button>
                    </div>
                    <p class="mb-2 text-sm text-muted">Five most recent records by attendance date.</p>
                    <ul class="divide-y divide-slate-200">
                        @forelse ($attendance as $record)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm"><span>{{ $record->attendance_date?->format('M d, Y') }} · {{ $record->credited_days }} credited days</span><x-status :value="$record->attendance_status" /></li>
                        @empty
                            <li class="py-3 text-sm text-muted">No attendance records.</li>
                        @endforelse
                    </ul>
                </x-card>
            @endcan
            @can('leave.manage')
                <x-card id="leave" heading="Leave" class="scroll-mt-6">
                    <div class="mb-4 flex flex-wrap gap-3">
                        <x-button :href="route('leave-applications.create', ['employee_id' => $employee->employee_id, 'employee_workspace' => 1])">File leave</x-button>
                        <x-button :href="route('leave-applications.index', ['employee_id' => $employee->employee_id])">View all leave applications</x-button>
                        <x-button :href="route('leave-balances.index', ['employee_id' => $employee->employee_id])">Manage leave balances</x-button>
                    </div>
                    <h3 class="font-semibold">Balances for {{ now()->year }}</h3>
                    <ul class="mb-4 text-sm text-muted">
                        @forelse ($leaveBalances as $balance)
                            <li class="py-1">{{ $balance->leaveType->leave_name }}: {{ $balance->available_balance }} days available</li>
                        @empty
                            <li class="py-1">No leave balances recorded for this year.</li>
                        @endforelse
                    </ul>
                    <h3 class="font-semibold">Recent applications</h3>
                    <ul class="divide-y divide-slate-200">
                        @forelse ($leave as $application)
                            <li class="py-3 text-sm"><a href="{{ route('leave-applications.show', $application) }}" class="inline-flex min-h-11 items-center font-semibold text-brand hover:underline">{{ $application->leaveType->leave_name }} · {{ $application->start_date?->format('M d, Y') }} to {{ $application->end_date?->format('M d, Y') }}</a><div><x-status :value="$application->approval_status" /></div></li>
                        @empty
                            <li class="py-3 text-sm text-muted">No leave applications.</li>
                        @endforelse
                    </ul>
                </x-card>
            @endcan
        </div>
    </div>
@endsection
