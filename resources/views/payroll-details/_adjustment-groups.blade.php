<div class="grid items-start gap-x-5 gap-y-3 xl:grid-cols-2">
    @foreach ($entries->groupBy('category') as $category => $categoryEntries)
        @php
            $categoryLabel = match ($category) {
                'ALLOWANCE' => 'Allowances',
                'BENEFIT' => 'Benefits',
                'PREMIUM' => 'Premiums',
                'GSIS_LOAN' => 'GSIS loans',
                'PAGIBIG_LOAN' => 'Pag-IBIG loans & savings',
                'GABE' => 'GABE',
                'PLI_DUES' => 'Other loans & dues',
                'LBP' => 'Land Bank loan',
                'OTHER' => 'Other adjustments',
                default => ucwords(strtolower(str_replace('_', ' ', $category))),
            };
        @endphp
        <fieldset data-payroll-category class="min-w-0">
            <legend class="w-full border-b border-slate-200 pb-1 text-xs font-semibold text-muted">{{ $categoryLabel }}</legend>
            <div class="divide-y divide-slate-100">
                @foreach ($categoryEntries as $entry)
                    <div data-payroll-entry data-search-text="{{ $entry['label'].' '.$categoryLabel.' '.$entry['hint'] }}" class="flex items-start justify-between gap-3 py-1.5">
                        <label for="{{ $entry['id'] }}" class="flex min-h-9 min-w-0 flex-1 flex-col justify-center break-words text-sm text-ink">
                            <span>{{ $entry['label'] }}</span>
                            @if ($entry['hint'])
                                <span id="{{ $entry['id'] }}-classification" class="text-xs font-normal text-muted">{{ $entry['hint'] }}</span>
                            @endif
                        </label>
                        <div class="w-32 shrink-0 sm:w-40">
                            <x-form.input :name="$entry['name']" :id="$entry['id']" type="number" step="0.01" min="0" :value="$entry['value']" inputmode="decimal" class="text-right tabular-nums" :aria-describedby="$entry['hint'] ? $entry['id'].'-classification' : null" />
                        </div>
                    </div>
                @endforeach
            </div>
        </fieldset>
    @endforeach
</div>
