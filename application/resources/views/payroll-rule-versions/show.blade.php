@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-rule-versions.index') }}" class="text-teal-800 underline">Back to configurations</a>
    <h1 class="mt-5 text-3xl font-bold">Configuration V{{ $version->id }} · {{ $version->name }}</h1>
    <p class="mt-3">{{ $version->personnel_classification->label() }} · Effective {{ $version->effective_from->format('Y-m-d') }} · {{ $version->is_demonstration ? 'Demonstration only' : 'External approval recorded at activation' }} · {{ $version->activated_at ? 'Activated / locked' : 'Draft — not used for calculation' }}</p>
    <p class="mt-3 font-semibold text-amber-900">Configuration activation does not authorize real payroll. Category applicability, client examples and operational policies still require validation.</p>
    <p class="mt-2">Only new, explicitly saved reference reviews use an applicable activated version. Saved core and statutory results are never silently recalculated. All prior versions stay available.</p>
    @can('create', \App\Models\PayrollRuleVersion::class)
        <a href="{{ route('payroll-rule-versions.create', ['copy' => $version->id]) }}" class="mt-4 inline-block font-semibold text-teal-800 underline">Copy parameters into a new dated draft</a>
        @if (! $version->activated_at)<a href="{{ route('payroll-rule-versions.edit', $version) }}" class="ml-4 font-semibold text-teal-800 underline">Edit draft</a>@endif
    @endcan
    @if ($version->activated_at)
        <p class="mt-4">Activated {{ $version->activated_at->format('Y-m-d H:i') }} by account #{{ $version->activated_by }}. Approval reference: {{ $version->approval_reference }}. Reviewed example / validation reference: {{ $version->validation_reference }}.</p>
        <p>References and confirmation record the operator's authorization; the application does not independently verify external approval.</p>
    @endif
    <section class="mt-6 space-y-4 rounded border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Saved parameters · draft revision {{ $version->record_version }}</h2>
        @foreach ($version->parameters['contributions'] as $component => $rule)
            <h3 class="text-lg font-semibold">{{ strtoupper($component) }}</h3>
            <dl>@foreach ($rule as $field => $value)<div class="calculation-line"><dt>{{ ucfirst(str_replace('_', ' ', $field)) }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>
        @endforeach
        <p>Shared qualifying-benefit exemption: PHP {{ $version->parameters['tax']['bonus_exemption'] }}.</p>
        <p>Annual source: {{ $version->parameters['tax']['source'] }}. Monthly source: {{ $version->parameters['tax']['monthly_source'] }}.</p>
        @foreach (['monthly_brackets' => 'Monthly tax brackets', 'annual_brackets' => 'Annual tax brackets'] as $table => $label)
            <h3 class="text-lg font-semibold">{{ $label }}</h3>
            <div class="table-scroll" tabindex="0" role="region" aria-label="{{ $label }}"><table class="w-full text-left"><thead><tr><th class="p-3">Lower bound</th><th class="p-3">Fixed tax</th><th class="p-3">Marginal rate</th></tr></thead><tbody>@foreach ($version->parameters['tax'][$table] as $row)<tr>@foreach ($row as $value)<td class="p-3">{{ $value }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        @endforeach
    </section>
    @can('update', $version)
        @if (! $version->activated_at)
            <form method="POST" action="{{ route('payroll-rule-versions.activate', $version) }}" class="mt-6 space-y-4 rounded border border-teal-200 bg-white p-6">
                @csrf
                <input type="hidden" name="record_version" value="{{ $version->record_version }}">
                <h2 class="text-xl font-semibold">Activate and lock</h2>
                <p>Activation records authorization for this category and data use from the stated month. Confirm the parameters and reviewed examples before locking. One activated version is permitted per category, data use and effective date.</p>
                @foreach (['approval_reference' => 'External approval / fictional demonstration reference', 'validation_reference' => 'Reviewed test example / validation reference', 'activation_reason' => 'Reason for activation'] as $field => $label)
                    <div><label for="{{ $field }}" class="block font-semibold">{{ $label }}</label><input id="{{ $field }}" name="{{ $field }}" required maxlength="{{ $field === 'activation_reason' ? 500 : 255 }}" class="mt-2 w-full rounded border border-slate-300 p-3"><x-ui.field-error :field="$field" /></div>
                @endforeach
                <label class="flex items-start gap-3"><input type="checkbox" name="authorization_confirmed" value="1" required class="mt-1"><span>I am authorized to activate these reviewed settings, or they are explicitly fictional for demonstration.</span></label>
                @if (! $version->is_demonstration)
                    <label class="flex items-start gap-3"><input type="checkbox" name="client_validation_confirmed" value="1" required class="mt-1"><span>I confirm external client approval and verified examples for this category, bases, rules and rounding are available under the stated references. This does not enable real payroll processing.</span></label>
                    <x-ui.field-error field="client_validation_confirmed" />
                @endif
                <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Activate configuration version</button>
            </form>
        @endif
    @endcan
    <section class="mt-8"><h2 class="text-xl font-semibold">Preserved configuration changes</h2>
        @foreach ($events as $event)
            <article class="mt-4 rounded border border-slate-200 bg-white p-5"><h3 class="font-semibold">{{ ucfirst($event->action) }} · {{ $event->created_at->format('Y-m-d H:i') }} · {{ $event->actor_username }} (account #{{ $event->actor_id }})</h3><p class="mt-2">{{ $event->reason }}</p><p>Effective from: {{ $event->effectiveDate() ?? 'Unavailable' }}</p><details class="mt-3"><summary class="cursor-pointer font-semibold">Saved configuration at this change</summary><pre class="mt-2 overflow-auto whitespace-pre-wrap text-sm">{{ json_encode($event->snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details></article>
        @endforeach
        <div class="mt-5">{{ $events->links() }}</div>
    </section>
@endsection
