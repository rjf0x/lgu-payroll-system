@extends('layouts.app')
@section('content')
    @php
        $safe = fn ($value) => is_scalar($value) ? (string) $value : '';
        $payload = $revision?->payload ?? [];
        $formInput = fn ($field, $default = null) => $restoreInput ? old($field, $default) : $default;
    @endphp
    <a href="{{ route('payroll-batches.show', $batch) }}" class="text-teal-800 underline">Back to monthly draft</a>
    <h1 class="mt-5 text-3xl font-bold">Draft inputs · {{ $entry->employee_name }}</h1>
    <p class="mt-3">{{ $entry->employee_number }} · {{ $batch->period_start->format('F Y') }} · {{ $batch->is_demonstration ? 'Demonstration only — not for real payroll' : 'Externally authorized inputs' }}</p>
    <p class="mt-2 text-slate-600">This screen saves inputs only. Review calculated core amounts separately in the monthly draft's calculation previews. Attendance formulas and statutory deductions remain unavailable.</p>
    @if ($sourceIssue)<p class="mt-4 rounded border border-amber-200 p-4 text-amber-900">Salary source needs review: {{ $sourceIssue }} Refresh the employee's saved draft coverage before saving inputs. Input history is retained if coverage is removed and re-added.</p>@endif
    @if ($contextChanged)<p class="mt-4 rounded border border-amber-200 p-4 text-amber-900">These inputs were saved against different employee/salary details. Review and save a new revision before later processing.</p>@endif
    @if ($historical)<a href="{{ route('payroll-inputs.show', [$batch, $entry]) }}" class="mt-4 inline-block text-teal-800 underline">Return to latest inputs</a>@endif

    <section class="mt-6 space-y-4 rounded border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">{{ $revision ? 'Saved revision '.$revision->revision : 'No inputs saved yet' }}</h2>
        @if ($revision)
            <p>Authority: {{ $revision->authority_reference }} · Reason: {{ $revision->change_reason }}</p>
            <p>Salary reference for this revision: PHP {{ $revision->entry_snapshot['monthly_salary'] }} · {{ $revision->entry_snapshot['position'] }}</p>
            <p class="font-semibold">Applicable allowance subtotal: PHP {{ $revision->allowanceTotal() }}</p>
            @foreach ($groups as $group => $heading)
                <h3 class="font-semibold">{{ $heading }}</h3>
                @forelse ($payload[$group] as $line)
                    <div class="rounded bg-slate-50 p-3">
                        <p>{{ $line['label'] }} · @if ($group === 'adjustments'){{ ucfirst($line['direction']) }} · Reference PHP {{ $line['original_amount'] }} → Authorized PHP {{ $line['adjusted_amount'] }}@else PHP {{ $line['amount'] }}@endif</p>
                        @if ($group === 'allowances')<p>{{ $line['is_applicable'] ? 'Applicable' : 'Excluded — not included in allowance subtotal or applicable allowances' }}</p>@endif
                        @if ($group === 'adjustments')<p>Reason: {{ $line['reason'] }}. Reference value was entered manually; no calculated amount is inferred.</p>@endif
                    </div>
                @empty<p class="text-slate-600">None entered.</p>@endforelse
            @endforeach
            <h3 class="font-semibold">Attendance inputs</h3>
            <p>Absence days: {{ $payload['attendance']['absence_days'] }} · Undertime minutes: {{ $payload['attendance']['undertime_minutes'] }}</p>
            @if ($payload['attendance']['notes'])<p>{{ $payload['attendance']['notes'] }}</p>@endif
        @endif
    </section>

    @if (! $historical && ! $sourceIssue)
        @can('update', $batch)
            <form method="POST" action="{{ route('payroll-inputs.update', [$batch, $entry]) }}" class="mt-8 space-y-6" data-payroll-inputs>
                @csrf @method('PUT')
                <input type="hidden" name="record_version" value="{{ $batch->record_version }}">
                <h2 class="text-xl font-semibold">{{ $latest ? 'Edit current draft inputs' : 'Enter draft inputs' }}</h2>
                <p class="text-slate-600">Save replaces the current input set for this employee only and preserves the previous revision. Use Add line for each authorized item; remove unused lines. Each group supports up to 30 lines with distinct descriptions. Empty groups mean none entered.</p>
                <noscript><p class="text-amber-900">JavaScript is required to add or remove input lines. Existing lines and attendance fields can still be edited.</p></noscript>
                @foreach ($groups as $group => $heading)
                    @php
                        $rows = $restoreInput ? old($group, []) : ($latest?->payload[$group] ?? []);
                        $rows = is_array($rows) ? array_values(array_filter($rows, 'is_array')) : [];
                    @endphp
                    <section class="rounded border border-slate-200 bg-white p-5" data-input-group="{{ $group }}">
                        <h3 class="text-lg font-semibold">{{ $heading }}</h3>
                        @if ($group === 'allowances')<p class="mt-2 text-sm text-slate-600">Examples: PERA, hazard, clothing, subsistence, laundry or medical allowances. Applicability is manually authorized; no category entitlement is assumed. Excluded lines retain the entered amount for review.</p>@endif
                        @if ($group === 'loans')<p class="mt-2 text-sm text-slate-600">Enter this month's authorized GSIS, Pag-IBIG, bank or other loan deduction. No balances, amortization or due dates are tracked.</p>@endif
                        <div data-input-rows class="mt-4 space-y-3">
                            @foreach ($rows as $index => $row)@include('payroll-inputs.row', compact('group', 'index', 'row', 'safe'))@endforeach
                        </div>
                        <template data-input-template>@include('payroll-inputs.row', ['group' => $group, 'index' => '__INDEX__', 'row' => [], 'safe' => $safe])</template>
                        <button type="button" data-add-input class="mt-4 text-teal-800 underline">Add {{ strtolower($heading) }} line</button>
                        <p class="input-group-status" data-input-count aria-live="polite" aria-atomic="true"></p>
                    </section>
                @endforeach
                @php
                    $attendance = $formInput('attendance', $latest?->payload['attendance'] ?? ['absence_days' => '0.00', 'undertime_minutes' => 0, 'notes' => '']);
                    $attendance = is_array($attendance) ? $attendance : [];
                @endphp
                <section class="space-y-4 rounded border border-slate-200 bg-white p-5">
                    <h3 class="text-lg font-semibold">Attendance inputs</h3>
                    <label class="block font-semibold">Absence days<input name="attendance[absence_days]" value="{{ $safe($attendance['absence_days'] ?? '') }}" inputmode="decimal" required maxlength="5" class="mt-1 block rounded border border-slate-300 p-2"></label>
                    <label class="block font-semibold">Undertime minutes<input name="attendance[undertime_minutes]" value="{{ $safe($attendance['undertime_minutes'] ?? '') }}" type="number" min="0" max="{{ $batch->period_start->daysInMonth * 1440 }}" step="1" required class="mt-1 block rounded border border-slate-300 p-2"></label>
                    <label class="block font-semibold">Attendance notes<textarea name="attendance[notes]" maxlength="500" class="mt-1 block w-full rounded border border-slate-300 p-2">{{ $safe($attendance['notes'] ?? '') }}</textarea></label>
                    <p class="text-sm text-slate-600">Enter supported records, including explicit zero values. Calendar-month bounds are validation limits, not workday rules. Deduction formulas remain unvalidated and inactive.</p>
                </section>
                <section class="space-y-4 rounded border border-teal-200 bg-white p-5">
                    <label class="block font-semibold">External authority / fictional demonstration reference<input name="authority_reference" value="{{ $safe($formInput('authority_reference', '')) }}" maxlength="255" required class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
                    <label class="block font-semibold">Reason for this saved input revision<textarea name="change_reason" maxlength="500" required class="mt-1 block w-full rounded border border-slate-300 p-2">{{ $safe($formInput('change_reason', '')) }}</textarea></label>
                    <label class="flex items-start gap-3"><input type="checkbox" name="authorization_confirmed" value="1" required class="mt-1"><span>I confirm the entered applicability, amounts, attendance and adjustments have the stated external authorization, or are clearly fictional demonstration inputs.</span></label>
                    <button class="rounded bg-teal-800 px-4 py-3 text-white">Save draft input revision</button>
                </section>
            </form>
        @endcan
    @endif

    <section class="mt-8">
        <h2 class="text-xl font-semibold">Preserved input revisions</h2>
        @forelse ($history as $item)
            <p class="mt-3"><a href="{{ route('payroll-inputs.show', [$batch, $entry, 'revision' => $item->revision]) }}" class="text-teal-800 underline">Revision {{ $item->revision }}</a> · {{ $item->author->name }} · {{ $item->created_at->format('Y-m-d H:i') }} · {{ $item->change_reason }}</p>
        @empty<p class="mt-3">No saved input revisions.</p>@endforelse
        <div class="mt-4">{{ $history->links() }}</div>
    </section>
@endsection
