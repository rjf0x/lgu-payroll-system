<div data-input-row class="grid gap-4 rounded border border-slate-200 p-4 sm:grid-cols-2">
    <div><label class="block font-semibold">{{ $group === 'loans' ? 'Loan / lender description' : 'Description' }}<input name="{{ $group }}[{{ $index }}][label]" value="{{ $safe($row['label'] ?? '') }}" required maxlength="100" class="mt-1 block w-full rounded border border-slate-300 p-2"></label><x-ui.field-error field="{{ $group }}.{{ $index }}.label" /></div>
    @if ($group === 'adjustments')
        <div><label class="block font-semibold">Adjustment affects<select name="{{ $group }}[{{ $index }}][direction]" class="mt-1 block w-full rounded border border-slate-300 p-2"><option value="deduction" @selected($safe($row['direction'] ?? 'deduction') === 'deduction')>Deduction</option><option value="earning" @selected($safe($row['direction'] ?? '') === 'earning')>Earning</option></select></label><x-ui.field-error field="{{ $group }}.{{ $index }}.direction" /></div>
        @foreach (['original_amount' => 'Reference / original amount (PHP)', 'adjusted_amount' => 'Authorized replacement amount (PHP)'] as $field => $label)
            <div><label class="block font-semibold">{{ $label }}<input name="{{ $group }}[{{ $index }}][{{ $field }}]" value="{{ $safe($row[$field] ?? '') }}" inputmode="decimal" required maxlength="13" class="mt-1 block w-full rounded border border-slate-300 p-2"></label><x-ui.field-error field="{{ $group }}.{{ $index }}.{{ $field }}" /></div>
        @endforeach
        <div class="sm:col-span-2"><label class="block font-semibold">Reason for adjustment<textarea name="{{ $group }}[{{ $index }}][reason]" required maxlength="500" class="mt-1 block w-full rounded border border-slate-300 p-2">{{ $safe($row['reason'] ?? '') }}</textarea></label><x-ui.field-error field="{{ $group }}.{{ $index }}.reason" /></div>
    @else
        <div><label class="block font-semibold">{{ $group === 'loans' ? 'Authorized deduction this month (PHP)' : 'Amount (PHP)' }}<input name="{{ $group }}[{{ $index }}][amount]" value="{{ $safe($row['amount'] ?? '') }}" inputmode="decimal" required maxlength="13" class="mt-1 block w-full rounded border border-slate-300 p-2"></label><x-ui.field-error field="{{ $group }}.{{ $index }}.amount" /></div>
        @if ($group === 'allowances')
            <div><label class="block font-semibold">Applicability<select name="{{ $group }}[{{ $index }}][is_applicable]" class="mt-1 block w-full rounded border border-slate-300 p-2"><option value="0" @selected($safe($row['is_applicable'] ?? '0') !== '1')>Excluded — not applicable</option><option value="1" @selected($safe($row['is_applicable'] ?? '') === '1')>Applicable to this employee</option></select></label><x-ui.field-error field="{{ $group }}.{{ $index }}.is_applicable" /></div>
        @endif
    @endif
    <button type="button" data-remove-input class="text-left text-red-800 underline sm:col-span-2">Remove line from current inputs</button>
</div>
