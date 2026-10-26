<dl class="calculation-totals">
    @foreach (['basic_pay' => 'Full-month basic pay reference', 'allowances' => 'Applicable allowances', 'bonuses' => 'Bonuses / additional earnings', 'earning_adjustments' => 'Earning adjustment replacements', 'total_earnings' => 'Core earnings total', 'loans' => 'Manual loan deductions', 'deduction_adjustments' => 'Deduction adjustment replacements', 'total_deductions' => 'Core manual deductions total', 'net_pay' => 'Core net before statutory deductions'] as $field => $label)
        <div @class(['total-highlight' => in_array($field, ['total_earnings', 'total_deductions', 'net_pay'])])><dt>{{ $label }}</dt><dd>PHP {{ $totals[$field] }}</dd></div>
    @endforeach
</dl>
