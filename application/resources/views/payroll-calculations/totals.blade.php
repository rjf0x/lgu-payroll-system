<dl class="mt-4 grid gap-3 sm:grid-cols-2">
    @foreach (['basic_pay' => 'Full-month basic pay reference', 'allowances' => 'Applicable allowances', 'bonuses' => 'Bonuses / additional earnings', 'earning_adjustments' => 'Earning adjustment replacements', 'total_earnings' => 'Core earnings total', 'loans' => 'Manual loan deductions', 'deduction_adjustments' => 'Deduction adjustment replacements', 'total_deductions' => 'Core manual deductions total', 'net_pay' => 'Core net before statutory deductions'] as $field => $label)
        <div><dt class="text-sm text-slate-600">{{ $label }}</dt><dd class="font-semibold">PHP {{ $totals[$field] }}</dd></div>
    @endforeach
</dl>
