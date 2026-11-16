@php
    $singular = $section === 'earnings' ? 'earning' : 'deduction';
    $entries = $types->map(function (\Illuminate\Database\Eloquent\Model $type) use ($section, $singular, $amounts, $errors): array {
        $typeId = $type->getKey();

        return [
            'id' => $singular.'-'.$typeId,
            'name' => $section.'['.$typeId.']',
            'label' => $type->{$singular.'_name'},
            'category' => $type->{$singular.'_category'} ?: 'OTHER',
            'value' => $amounts[$typeId] ?? '',
            'hint' => $section === 'earnings' ? ($type->is_taxable ? 'Taxable earning' : 'Non-taxable earning') : null,
            'configured' => $amounts->has($typeId) || filled(old($section.'.'.$typeId)) || $errors->has($section.'.'.$typeId),
        ];
    })->toBase();

    if ($section === 'deductions') {
        $entries->push([
            'id' => 'lbp_loan_deduction',
            'name' => 'lbp_loan_deduction',
            'label' => 'LBP Loan Deduction (after NTHP)',
            'category' => 'LBP',
            'value' => $detail->lbp_loan_deduction,
            'hint' => 'Applied after net take-home pay before LBP.',
            'configured' => (float) $detail->lbp_loan_deduction > 0 || filled(old('lbp_loan_deduction')) || $errors->has('lbp_loan_deduction'),
        ]);
    }

    [$configuredEntries, $additionalEntries] = $entries->partition(fn (array $entry): bool => $entry['configured']);
@endphp

<section id="{{ $section }}-panel" data-payroll-panel="{{ $section }}" class="min-w-0 p-4">
    <h3 data-payroll-panel-heading class="mb-3 text-sm font-semibold">{{ ucfirst($section) }}</h3>
    <div data-payroll-search-toolbar hidden class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs text-muted">Configured entries first. Search includes additional fields.</p>
        <div class="flex w-full items-center gap-2 sm:w-auto">
            <label for="{{ $section }}-search" class="sr-only">Search {{ $section }} by name or category</label>
            <input id="{{ $section }}-search" data-payroll-search type="search" autocomplete="off" placeholder="Search {{ $section }}…" aria-controls="{{ $section }}-fields" class="ui-field sm:max-w-64">
            <button type="button" data-payroll-search-clear hidden class="ui-button ui-button-secondary">Clear</button>
        </div>
    </div>
    <p data-payroll-search-status role="status" aria-atomic="true" class="sr-only"></p>
    <div id="{{ $section }}-fields" class="space-y-3">
        @if ($configuredEntries->isNotEmpty())
            <div data-payroll-entry-section>
                <h3 class="mb-2 text-xs font-semibold text-muted">Configured {{ $section }} <span class="font-normal">({{ $configuredEntries->count() }})</span></h3>
                @include('payroll-details._adjustment-groups', ['entries' => $configuredEntries])
            </div>
        @elseif ($additionalEntries->isNotEmpty())
            <p data-payroll-unconfigured class="text-sm text-muted">No configured {{ $section }}. Browse additional entries below.</p>
        @endif

        @if ($additionalEntries->isNotEmpty())
            <details data-payroll-additional data-payroll-entry-section @if ($configuredEntries->isEmpty()) open @endif class="group rounded-lg border border-slate-200">
                <summary class="flex min-h-10 items-center gap-2 rounded-lg bg-slate-50 px-3 py-2 text-sm font-semibold text-brand-ink">
                    <svg class="h-3.5 w-3.5 shrink-0 group-open:rotate-90" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><path d="m7 4 6 6-6 6" /></svg>
                    Additional {{ $section }} <span class="text-xs font-normal text-muted">({{ $additionalEntries->count() }})</span>
                </summary>
                <div class="p-3">
                    @include('payroll-details._adjustment-groups', ['entries' => $additionalEntries])
                </div>
            </details>
        @endif

        <p data-payroll-no-results hidden class="rounded-lg bg-slate-50 px-3 py-4 text-sm text-muted">No matching {{ $section }}. Try another name or category, or clear the search.</p>
        @if ($entries->isEmpty())
            <p class="text-sm text-muted">No active {{ $section }} are available.</p>
        @endif
    </div>
</section>
