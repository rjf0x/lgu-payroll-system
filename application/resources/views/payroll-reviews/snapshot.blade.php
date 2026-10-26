<section class="mt-6 rounded border border-slate-200 bg-white p-6">
    <h2 class="text-xl font-semibold">{{ $snapshotTitle }}</h2>
    <p class="mt-2 font-semibold text-amber-900">Demonstration only — not for real payroll. Client rules remain unvalidated.</p>
    <p class="mt-2">{{ count($snapshot['employees']) }} employees · Period {{ $snapshot['period'] }}</p>
    <dl class="mt-4 grid gap-4 sm:grid-cols-3">
        @foreach ($snapshot['totals'] as $field => $amount)
            <div><dt>{{ ucfirst(str_replace('_', ' ', $field)) }}</dt><dd class="font-bold">PHP {{ $amount }}</dd></div>
        @endforeach
    </dl>
    <div class="mt-5 overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="mb-3 text-left font-semibold">Employee totals including reviewed contributions and withholding</caption>
            <thead><tr><th scope="col" class="p-2">Employee</th><th scope="col" class="p-2">Earnings</th><th scope="col" class="p-2">Deductions</th><th scope="col" class="p-2">Net pay</th></tr></thead>
            <tbody>
                @foreach ($snapshot['employees'] as $row)
                    <tr class="border-t"><th scope="row" class="p-2">{{ $row['employee']['employee_number'] }} · {{ $row['employee']['employee_name'] }}</th><td class="p-2">{{ $row['core_breakdown']['totals']['total_earnings'] }}</td><td class="p-2">{{ $row['statutory_result']['total_deductions'] }}</td><td class="p-2">{{ $row['statutory_result']['net_before_tax_refund'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @foreach ($snapshot['employees'] as $row)
        <details class="mt-4 rounded border p-4">
            <summary class="cursor-pointer font-semibold">{{ $row['employee']['employee_name'] }} — review calculation and authority</summary>
            <p class="mt-3">{{ $row['employee']['position'] }} · {{ $row['employee']['office'] }} · {{ $row['employee']['personnel_classification'] }} · Grade {{ $row['employee']['grade'] }} / Step {{ $row['employee']['step'] }}</p>
            <p class="mt-2">Input revision {{ $row['inputs']['revision'] }} · Authority: {{ $row['inputs']['authority_reference'] }} · Reason: {{ $row['inputs']['change_reason'] }}</p>
            @include('payroll-calculations.totals', ['totals' => $row['core_breakdown']['totals']])
            @foreach ($row['core_breakdown']['lines'] as $line)
                <p class="mt-2">{{ $line['label'] }} · {{ ucfirst(str_replace('_', ' ', $line['bucket'])) }} · PHP {{ $line['amount'] }} · {{ $line['included'] ? 'Included' : 'Excluded' }}@if ($line['original_amount'] !== null) · Original PHP {{ $line['original_amount'] }} · {{ $line['reason'] }}@endif</p>
            @endforeach
            <p class="mt-3">Absence days: {{ $row['inputs']['payload']['attendance']['absence_days'] }} · Undertime minutes: {{ $row['inputs']['payload']['attendance']['undertime_minutes'] }}</p>
            <p class="mt-3">Statutory review {{ $row['statutory_revision'] }} · Reference: {{ $row['statutory_inputs']['reference'] }} · Reason: {{ $row['statutory_inputs']['reason'] }}</p>
            @foreach ($row['statutory_result']['contributions'] as $name => $component)
                <p class="mt-2">{{ strtoupper($name) }} · {{ $component['coverage'] }} · Entered basis PHP {{ $component['entered_basis'] ?? 'unavailable' }} · PHP {{ $component['amount'] }}</p>
            @endforeach
            <p class="mt-2">Tax procedure: {{ $row['statutory_result']['tax']['method'] }} · Taxable compensation PHP {{ $row['statutory_result']['tax']['taxable_compensation'] }} · Taxable bonus excess PHP {{ $row['statutory_result']['tax']['taxable_bonus_excess'] }} · Withholding PHP {{ $row['statutory_result']['tax']['withholding'] }}</p>
            <p class="mt-2">Configuration: {{ $row['parameters']['id'] }} · {{ $row['parameters']['_configuration']['approval_reference'] ?? 'Unverified built-in reference' }}</p>
            <a class="mt-3 inline-block text-teal-800 underline" href="{{ route('statutory-previews.show', [$batch, $snapshot['calculation_run_id'], $row['calculation_item_id']]) }}">Full contribution parameters and preserved review history</a>
        </details>
    @endforeach
</section>
