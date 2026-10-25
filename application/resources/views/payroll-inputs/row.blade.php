<div data-input-row class="grid gap-3 rounded border border-slate-200 p-4 sm:grid-cols-2">
    <label class="block font-semibold">{{ $group === 'loans' ? 'Loan / lender description' : 'Description' }}<input name="{{ $group }}[{{ $index }}][label]" value="{{ $safe($row['label'] ?? '') }}" required maxlength="100" class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
    @if ($group === 'adjustments')
        <label class="block font-semibold">Adjustment affects<select name="{{ $group }}[{{ $index }}][direction]" class="mt-1 block w-full rounded border border-slate-300 p-2"><option value="deduction" @selected($safe($row['direction'] ?? 'deduction') === 'deduction')>Deduction</option><option value="earning" @selected($safe($row['direction'] ?? '') === 'earning')>Earning</option></select></label>
        @foreach (['original_amount' => 'Reference / original amount (PHP)', 'adjusted_amount' => 'Authorized replacement amount (PHP)'] as $field => $label)
            <label class="block font-semibold">{{ $label }}<input name="{{ $group }}[{{ $index }}][{{ $field }}]" value="{{ $safe($row[$field] ?? '') }}" inputmode="decimal" required maxlength="13" class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
        @endforeach
        <label class="block font-semibold sm:col-span-2">Reason for adjustment<textarea name="{{ $group }}[{{ $index }}][reason]" required maxlength="500" class="mt-1 block w-full rounded border border-slate-300 p-2">{{ $safe($row['reason'] ?? '') }}</textarea></label>
    @else
        <label class="block font-semibold">{{ $group === 'loans' ? 'Authorized deduction this month (PHP)' : 'Amount (PHP)' }}<input name="{{ $group }}[{{ $index }}][amount]" value="{{ $safe($row['amount'] ?? '') }}" inputmode="decimal" required maxlength="13" class="mt-1 block w-full rounded border border-slate-300 p-2"></label>
        @if ($group === 'allowances')
            <label class="block font-semibold">Applicability<select name="{{ $group }}[{{ $index }}][is_applicable]" class="mt-1 block w-full rounded border border-slate-300 p-2"><option value="0" @selected($safe($row['is_applicable'] ?? '0') !== '1')>Excluded — not applicable</option><option value="1" @selected($safe($row['is_applicable'] ?? '') === '1')>Applicable to this employee</option></select></label>
        @endif
    @endif
    <button type="button" data-remove-input class="text-left text-red-800 underline sm:col-span-2">Remove line from current inputs</button>
</div>
