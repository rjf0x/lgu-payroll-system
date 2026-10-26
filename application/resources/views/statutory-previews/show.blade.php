@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-calculations.show', [$batch, $run]) }}" class="text-teal-800 underline">Back to core preview</a>
    <h1 class="mt-5 text-3xl font-bold">Contribution and withholding reference review</h1>
    <p class="mt-3">{{ $item->entry_snapshot['employee_number'] }} · {{ $item->entry_snapshot['employee_name'] }} · {{ $item->entry_snapshot['personnel_classification'] }} · {{ $batch->period_start->format('F Y') }}</p>
    <p class="mt-4 font-semibold text-amber-900">Demonstration only — not for real payroll. Client-approved category applicability, calculation bases, procedures and rounding are unavailable.</p>
    <p class="mt-2">Core preview {{ $run->id }} remains unchanged. Each review preserves its own inputs, reference parameters and results. No entitlement or membership is inferred from personnel classification.</p>
    @if ($issue || $run->batch_version !== $batch->record_version)
        <p class="mt-4 font-semibold text-amber-900">Needs review: {{ $issue ?? 'Draft changed. Calculate a current core preview before saving.' }}</p>
    @endif
    <section class="mt-6 rounded border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Available reference scenarios</h2>
        <p class="mt-2">{{ $profile['label'] ?? 'Reference profile unavailable' }}. Review year: {{ $profile['review_year'] ?? 'unavailable' }}. These are regulatory references, not approved LGU rules.</p>
        @foreach (($profile['contributions'] ?? []) as $name => $rule)
            <p class="mt-3"><strong>{{ strtoupper($name) }}</strong>: {{ $rule['scope'] ?? 'Scope unavailable' }} <a href="{{ $rule['source'] ?? '#' }}" class="text-teal-800 underline">Reference source</a></p>
        @endforeach
        <p class="mt-3">Rates and limits are recorded with each saved review. All percentage results use decimal arithmetic and half-up rounding to two decimals at the component total. Displayed cumulative averages retain eight decimals; tax liability uses exact bracket arithmetic before final rounding. Client rounding acceptance remains pending.</p>
    </section>
    @can('update', $batch)
        @if ($batch->is_demonstration && ! $issue && $run->batch_version === $batch->record_version)
            @php($saved = $latest?->inputs ?? [])
            <form method="POST" action="{{ route('statutory-previews.store', [$batch, $run, $item]) }}" class="mt-6 space-y-6 rounded border border-slate-200 bg-white p-6">
                @csrf
                <input type="hidden" name="record_version" value="{{ $batch->record_version }}">
                <input type="hidden" name="base_revision" value="{{ $latest?->revision ?? 0 }}">
                <h2 class="text-xl font-semibold">Review a fictional scenario</h2>
                <p>Enter each reviewed monthly contribution basis separately. GSIS uses actual monthly salary, Pag-IBIG uses fund salary and PhilHealth uses monthly basic salary. No basis is copied automatically.</p>
                @foreach (['gsis' => 'GSIS', 'pagibig' => 'Pag-IBIG', 'philhealth' => 'PhilHealth'] as $name => $label)
                    <fieldset class="rounded border border-slate-200 p-4">
                        <legend class="font-semibold">{{ $label }} employee share</legend>
                        <label for="{{ $name }}_coverage" class="block font-semibold">Coverage scenario</label>
                        <select id="{{ $name }}_coverage" name="{{ $name }}_coverage" class="mt-2 w-full rounded border border-slate-300 p-3">
                            @foreach (['unknown' => 'Unknown — leave amount unavailable', 'applicable' => 'Apply the standard reference scenario', 'not_applicable' => 'Explicitly not applicable in this fictional scenario'] as $value => $text)
                                <option value="{{ $value }}" @selected(($saved[$name.'_coverage'] ?? 'unknown') === $value)>{{ $text }}</option>
                            @endforeach
                        </select>
                        <x-ui.field-error :field="$name.'_coverage'" />
                        <label for="{{ $name }}_basis" class="mt-3 block font-semibold">Reviewed monthly basis (PHP)</label>
                        <input id="{{ $name }}_basis" name="{{ $name }}_basis" value="{{ $saved[$name.'_basis'] ?? '' }}" inputmode="decimal" maxlength="13" class="mt-2 w-full rounded border border-slate-300 p-3">
                        <x-ui.field-error :field="$name.'_basis'" />
                    </fieldset>
                @endforeach
                <fieldset class="space-y-4 rounded border border-slate-200 p-4">
                    <legend class="font-semibold">Withholding scenario</legend>
                    <label for="tax_method" class="block font-semibold">Procedure</label>
                    <select id="tax_method" name="tax_method" class="w-full rounded border border-slate-300 p-3">
                        @foreach (['unknown' => 'Unknown — leave tax unavailable', 'monthly_regular' => 'Monthly regular compensation only', 'cumulative' => 'Cumulative monthly average including supplementary compensation', 'annual' => 'December annual reconciliation'] as $value => $label)
                            <option value="{{ $value }}" @selected(($saved['tax_method'] ?? 'unknown') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <x-ui.field-error field="tax_method" />
                    <p>For monthly regular-only: enter this month's taxable regular pay and explicit zeros for other pay, bonuses and prior withholding. Saved bonuses or earning adjustments require a different procedure.</p>
                    <p>For cumulative: enter calendar-year amounts paid through this payroll, including previous employers, and elapsed monthly payroll periods. For December annual: enter full-year amounts. Taxable regular pay must already exclude reviewed non-taxable pay and mandatory contributions. Loans do not reduce taxable pay. No tax classifications or YTD amounts are inferred from core earnings.</p>
                    @foreach (['taxable_regular' => 'Taxable regular compensation, excluding bonuses (PHP)', 'taxable_other' => 'Other fully taxable supplementary compensation, excluding the benefit pool below (PHP)', 'qualifying_bonuses' => 'Total qualifying 13th-month/other benefits in the shared exemption pool (PHP)', 'prior_withheld' => 'Tax previously withheld before this payroll, including previous employers (PHP)'] as $name => $label)
                        <div>
                            <label for="{{ $name }}" class="block font-semibold">{{ $label }}</label>
                            <input id="{{ $name }}" name="{{ $name }}" value="{{ $saved[$name] ?? '' }}" inputmode="decimal" maxlength="13" class="mt-2 w-full rounded border border-slate-300 p-3">
                            <x-ui.field-error :field="$name" />
                        </div>
                    @endforeach
                    <label for="elapsed_periods" class="block font-semibold">Elapsed monthly payroll periods (cumulative method only)</label>
                    <input id="elapsed_periods" name="elapsed_periods" type="number" min="1" max="12" value="{{ $saved['elapsed_periods'] ?? '' }}" class="rounded border border-slate-300 p-3">
                    <x-ui.field-error field="elapsed_periods" />
                    <p>Only qualifying benefits share the configured exemption. Enter other taxable bonuses in supplementary compensation. Taxable excess is added to regular and other taxable compensation once. Manual totals must include this payroll and all relevant employers; they are not a verified annual ledger. Special membership, minimum-wage exemptions, fringe-benefit tax, termination and other tax procedures remain unsupported.</p>
                </fieldset>
                @foreach (['reference' => 'Fictional authority / basis reference', 'reason' => 'Review reason and basis explanation'] as $name => $label)
                    <div><label for="{{ $name }}" class="block font-semibold">{{ $label }}</label><input id="{{ $name }}" name="{{ $name }}" value="" required maxlength="{{ $name === 'reference' ? 255 : 500 }}" class="mt-2 w-full rounded border border-slate-300 p-3"><x-ui.field-error :field="$name" /></div>
                @endforeach
                <label class="flex items-start gap-3"><input type="checkbox" name="demonstration_confirmed" value="1" required class="mt-1"><span>I reviewed the coverage, taxable classification and cumulative/annual amounts for a fictional scenario. I understand that regulatory references do not establish client approval or production readiness.</span></label>
                <x-ui.field-error field="demonstration_confirmed" />
                <p class="text-sm text-slate-600">Rejected submissions do not prefill this form. Review and re-enter corrections; only this employee's saved inputs appear here.</p>
                <button class="rounded bg-teal-800 px-4 py-3 font-semibold text-white">Save reference preview</button>
            </form>
        @endif
    @endcan
    <h2 class="mt-8 text-xl font-semibold">Preserved statutory reviews</h2>
    @forelse ($history as $review)
        @php($result = $review->result)
        <article id="review-{{ $review->id }}" class="mt-5 space-y-4 rounded border border-slate-200 bg-white p-6">
            <h3 class="text-lg font-semibold">Revision {{ $review->revision }} · {{ $result['status'] }} · {{ $review->created_at->format('Y-m-d H:i') }}</h3>
            <p>Engine {{ $result['engine'] }}</p>
            @if ($result['engine'] !== \App\Services\StatutoryCalculator::VERSION)
                <p class="font-semibold text-amber-900">Saved with an earlier calculation engine. Review and save a new reference preview to apply current corrections. This historical result remains unchanged.</p>
            @endif
            <p>Saved by account #{{ $review->created_by }} · Reference: {{ $review->inputs['reference'] }} · Reason: {{ $review->inputs['reason'] }}</p>
            <p class="font-semibold text-amber-900">{{ $result['validation'] }}</p>
            @foreach ($result['issues'] as $message)<p class="text-amber-900">{{ $message }}</p>@endforeach
            <div class="table-scroll" tabindex="0" role="region" aria-label="Contribution breakdown for revision {{ $review->revision }}">
                <table class="w-full text-left"><thead><tr><th class="p-3">Employee contribution</th><th class="p-3">Coverage</th><th class="p-3">Entered / calculation basis</th><th class="p-3">Applied rate</th><th class="p-3">PHP</th></tr></thead><tbody>
                @foreach ($result['contributions'] as $name => $row)
                    <tr><th class="p-3">{{ strtoupper($name) }}</th><td class="p-3">{{ $row['coverage'] }}</td><td class="p-3">{{ $row['entered_basis'] ?? 'Unavailable' }} / {{ $row['calculation_basis'] ?? 'Not used' }}</td><td class="p-3">{{ $row['applied_rate'] ?? 'Not used' }}</td><td class="p-3">{{ $row['amount'] ?? 'Not calculated' }}</td></tr>
                @endforeach
                </tbody></table>
            </div>
            <p>Withholding method: {{ $result['tax']['method'] }}</p>
            @foreach (['taxable_bonus_excess' => 'Taxable excess bonuses included', 'taxable_compensation' => 'Total taxable compensation for this procedure', 'table_basis' => 'Tax table basis', 'liability' => 'Tax liability for the reviewed period(s)', 'prior_withheld' => 'Previously withheld', 'withholding' => 'Additional withholding', 'credit_or_refund_due' => 'Credit / refund requiring review'] as $field => $label)
                <p><strong>{{ $label }}:</strong> {{ $result['tax'][$field] ?? 'Not calculated' }}</p>
            @endforeach
            <p><strong>Total deductions including core loans/adjustments:</strong> {{ $result['total_deductions'] ?? 'Unavailable — incomplete review' }}</p>
            <p><strong>Reference net before any tax refund:</strong> {{ $result['net_before_tax_refund'] ?? 'Unavailable — incomplete review' }}</p>
            @if ($result['net_before_tax_refund'] !== null && str_starts_with($result['net_before_tax_refund'], '-'))<p class="font-semibold text-red-800">Negative reference net. Review deductions; no minimum-net rule or tax collection limit is inferred.</p>@endif
            <p>Credits/refunds are recorded separately and are not automatically added to this payroll. Refund timing, collection shortfalls and minimum-net treatment require client validation.</p>
            <details><summary class="cursor-pointer font-semibold">Preserved inputs, rules, sources and calculation parameters</summary><pre class="mt-3 overflow-auto whitespace-pre-wrap text-sm">{{ json_encode(['inputs' => $review->inputs, 'parameters' => $review->parameters, 'tax_breakdown' => $result['tax']], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre></details>
        </article>
    @empty
        <p class="mt-4">No statutory reference reviews saved for this employee and core preview.</p>
    @endforelse
    <div class="mt-5">{{ $history->links() }}</div>
@endsection
