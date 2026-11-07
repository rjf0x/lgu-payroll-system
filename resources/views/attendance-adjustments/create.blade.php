@extends('layouts.app')

@section('title', 'Request Adjustment')
@section('heading', 'Request Attendance Adjustment')

@section('content')
    <form
        method="POST"
        action="{{ route('attendance-adjustments.store') }}"
        class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
     data-submit-feedback>
        @csrf

        <x-form.select
            name="attendance_id"
            label="Attendance Record"
            :options="$records->mapWithKeys(fn ($r) => [$r->attendance_id => $r->employee->full_name.' · '.$r->attendance_date?->format('M d, Y').' · '.$r->attendance_status])"
            :selected="$selectedId"
            placeholder="Select an attendance record"
            required
        />

        <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
            <x-form.select
                name="requested_attendance_status"
                label="Requested Status"
                :options="[
                    'PRESENT' => 'Present',
                    'HALF_DAY' => 'Half Day',
                    'ABSENT' => 'Absent',
                    'ON_LEAVE' => 'On Leave',
                    'HOLIDAY' => 'Holiday',
                    'REST_DAY' => 'Rest Day',
                ]"
                required
            />
            <x-form.input name="requested_credited_days" label="Requested Credited Days" type="number" step="0.25" value="1" required />
        </div>

        <x-form.textarea name="adjustment_reason" label="Reason" required />

        <div class="flex flex-wrap gap-3">
            <button class="ui-button ui-button-primary">Submit request</button>
            <a href="{{ route('attendance-adjustments.index') }}" class="ui-button ui-button-secondary">Cancel</a>
        </div>
    </form>
@endsection
