@props(['run', 'organizationName'])

<x-card class="mb-5 print-keep">
    <p class="mb-3 break-words font-semibold">{{ $organizationName }}</p>
    <dl class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
        <div><dt class="font-semibold">Payroll period</dt><dd>{{ $run->payrollPeriod->period_name }} · Run #{{ $run->run_number }}</dd></div>
        <div><dt class="font-semibold">Coverage / pay date</dt><dd>{{ $run->payrollPeriod->start_date?->format('M d, Y') }}–{{ $run->payrollPeriod->end_date?->format('M d, Y') }}<br>Pay date {{ $run->payrollPeriod->pay_date?->format('M d, Y') }}</dd></div>
        <div><dt class="font-semibold">Run status</dt><dd><x-status :value="$run->status" /></dd></div>
    </dl>
    <p class="mt-3 text-sm text-muted">Viewed <x-local-time :value="now()" show-timezone /> · {{ $run->details->count() }} employees · Amounts in PHP.</p>
    @if ($run->status !== 'FINALIZED')
        <p class="mt-2 font-semibold text-amber-900">Working copy. Figures may change until this payroll run is finalized.</p>
    @endif
</x-card>
