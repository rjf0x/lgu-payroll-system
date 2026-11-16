<?php

return [
    'non_regular' => [
        'SUPPLEMENTAL' => [
            'policy_reference' => env('PAYROLL_SUPPLEMENTAL_POLICY_REFERENCE'),
            'calculation_method' => env('PAYROLL_SUPPLEMENTAL_CALCULATION_METHOD'),
        ],
        'ADJUSTMENT' => [
            'policy_reference' => env('PAYROLL_ADJUSTMENT_POLICY_REFERENCE'),
            'calculation_method' => env('PAYROLL_ADJUSTMENT_CALCULATION_METHOD'),
        ],
    ],
];
