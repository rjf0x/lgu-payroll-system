@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-rule-versions.index') }}" class="text-teal-800 underline">Back to configurations</a>
    <h1 class="mt-5 text-3xl font-bold">{{ $version->exists ? 'Edit configuration draft V'.$version->id : 'Create configuration draft' }}</h1>
    <p class="mt-3">Maintain the supported contribution methods and six-bracket tax tables. Enter rates as fractions: 0.09 means 9%. Supported parameters have at most two decimal places. No formula or payroll eligibility is inferred.</p>
    <p class="mt-2 text-amber-900">Review template values against your source. A draft is not active. Rejected submissions do not prefill this form; saved values remain available.</p>
    <form method="POST" action="{{ $version->exists ? route('payroll-rule-versions.update', $version) : route('payroll-rule-versions.store') }}" class="mt-6 space-y-6 rounded border border-slate-200 bg-white p-6">
        @csrf
        @if ($version->exists) @method('PUT') @endif
        <input type="hidden" name="submission_key" value="{{ $version->submission_key }}">
        <input type="hidden" name="record_version" value="{{ $version->record_version }}">
        <div><label for="name" class="block font-semibold">Version name</label><input id="name" name="name" value="{{ $version->name }}" required maxlength="150" class="mt-2 w-full rounded border border-slate-300 p-3"><x-ui.field-error field="name" /></div>
        <div><label for="classification" class="block font-semibold">Covered personnel classification</label><select id="classification" name="personnel_classification" required class="mt-2 w-full rounded border border-slate-300 p-3"><option value="">Select a classification</option>@foreach ($classifications as $classification)<option value="{{ $classification->value }}" @selected($version->personnel_classification === $classification)>{{ $classification->label() }}</option>@endforeach</select><x-ui.field-error field="personnel_classification" /></div>
        <div><label for="data_use" class="block font-semibold">Data use</label><select id="data_use" name="is_demonstration" class="mt-2 w-full rounded border border-slate-300 p-3"><option value="1" @selected($version->is_demonstration)>Demonstration only</option><option value="0" @selected(! $version->is_demonstration)>Externally approved configuration — real processing stays blocked</option></select><x-ui.field-error field="is_demonstration" /></div>
        <div><label for="effective_from" class="block font-semibold">Effective from (first day of a month)</label><input id="effective_from" name="effective_from" type="date" value="{{ $version->effective_from?->format('Y-m-d') ?? today()->startOfMonth()->format('Y-m-d') }}" min="1000-01-01" max="9999-12-31" required class="mt-2 rounded border border-slate-300 p-3"><x-ui.field-error field="effective_from" /><p class="mt-2">This version's reference review year is the year of its effective date. A later year requires its own reviewed version. First-day selection avoids assuming mid-month rule treatment.</p></div>
        @foreach (['gsis' => ['rate' => 'Employee rate'], 'pagibig' => ['threshold' => 'Lower salary tier threshold (PHP)', 'low_rate' => 'Lower-tier employee rate', 'rate' => 'Upper-tier employee rate', 'ceiling' => 'Maximum fund salary (PHP)'], 'philhealth' => ['rate' => 'Total premium rate', 'employee_share' => 'Employee share of total premium', 'floor' => 'Monthly basic salary floor (PHP)', 'ceiling' => 'Monthly basic salary ceiling (PHP)']] as $ruleName => $fields)
            <fieldset class="space-y-4 rounded border border-slate-200 p-4"><legend class="font-semibold">{{ strtoupper($ruleName) }}</legend>
                @foreach ($fields as $field => $label)
                    @php($path = 'parameters.contributions.'.$ruleName.'.'.$field)
                    <div><label for="{{ $ruleName }}_{{ $field }}" class="block font-semibold">{{ $label }}</label><input id="{{ $ruleName }}_{{ $field }}" name="parameters[contributions][{{ $ruleName }}][{{ $field }}]" value="{{ $version->parameters['contributions'][$ruleName][$field] ?? '' }}" inputmode="decimal" required maxlength="13" class="mt-2 w-full rounded border border-slate-300 p-3"><x-ui.field-error :field="$path" /></div>
                @endforeach
                @foreach (['source' => 'Regulatory / approved source reference', 'scope' => 'Covered membership scenario and limitations'] as $field => $label)
                    @php($path = 'parameters.contributions.'.$ruleName.'.'.$field)
                    <div><label for="{{ $ruleName }}_{{ $field }}" class="block font-semibold">{{ $label }}</label><input id="{{ $ruleName }}_{{ $field }}" name="parameters[contributions][{{ $ruleName }}][{{ $field }}]" value="{{ $version->parameters['contributions'][$ruleName][$field] ?? '' }}" required maxlength="255" class="mt-2 w-full rounded border border-slate-300 p-3"><x-ui.field-error :field="$path" /></div>
                @endforeach
                <x-ui.field-error :field="'parameters.contributions.'.$ruleName" />
            </fieldset>
        @endforeach
        <fieldset class="space-y-4 rounded border border-slate-200 p-4"><legend class="font-semibold">Withholding parameters</legend>
            @foreach (['bonus_exemption' => 'Shared qualifying-benefit exemption (PHP)', 'source' => 'Annual tax / procedure source reference', 'monthly_source' => 'Monthly tax table source reference'] as $field => $label)
                @php($path = 'parameters.tax.'.$field)
                <div><label for="tax_{{ $field }}" class="block font-semibold">{{ $label }}</label><input id="tax_{{ $field }}" name="parameters[tax][{{ $field }}]" value="{{ $version->parameters['tax'][$field] ?? '' }}" required maxlength="{{ $field === 'bonus_exemption' ? 13 : 255 }}" class="mt-2 w-full rounded border border-slate-300 p-3"><x-ui.field-error :field="$path" /></div>
            @endforeach
            @foreach (['monthly_brackets' => 'Monthly brackets', 'annual_brackets' => 'Annual brackets'] as $table => $label)
                <h2 class="text-lg font-semibold">{{ $label }}</h2>
                <p>The first row is 0.00 / 0.00 / 0.00. Enter increasing lower bounds, the fixed tax at each bound and the marginal rate.</p>
                <div class="table-scroll" tabindex="0" role="region" aria-label="{{ $label }}"><table class="w-full text-left"><thead><tr><th class="p-3">Lower bound (PHP)</th><th class="p-3">Fixed tax (PHP)</th><th class="p-3">Marginal rate</th></tr></thead><tbody>
                @for ($row = 0; $row < 6; $row++)
                    <tr>@foreach (['Lower bound', 'Fixed tax', 'Marginal rate'] as $column => $fieldLabel)
                        @php($path = 'parameters.tax.'.$table.'.'.$row.'.'.$column)
                        <td class="p-3"><input aria-label="{{ $label }} row {{ $row + 1 }} {{ $fieldLabel }}" name="parameters[tax][{{ $table }}][{{ $row }}][{{ $column }}]" value="{{ $version->parameters['tax'][$table][$row][$column] ?? '' }}" inputmode="decimal" required maxlength="13" class="w-full rounded border border-slate-300 p-3"><x-ui.field-error :field="$path" /></td>
                    @endforeach</tr>
                @endfor
                </tbody></table></div>
                <x-ui.field-error :field="'parameters.tax.'.$table" />
            @endforeach
        </fieldset>
        <div><label for="change_reason" class="block font-semibold">Reason for creating / revising this draft</label><textarea id="change_reason" name="change_reason" required maxlength="500" class="mt-2 w-full rounded border border-slate-300 p-3"></textarea><x-ui.field-error field="change_reason" /></div>
        <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Save configuration draft</button>
    </form>
@endsection
