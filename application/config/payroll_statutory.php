<?php

// Reference scenarios only. No client-approved configuration or production activation.
return [
    'id' => 'ph-reference-2026-v1',
    'label' => '2026 regulatory reference - client applicability unverified',
    'review_year' => 2026,
    'rounding' => 'half_up_2_at_component_total',
    'category_validation' => [
        'permanent' => 'unverified',
        'coterminous' => 'unverified',
        'part_time' => 'unverified',
    ],
    'contributions' => [
        'gsis' => [
            'method' => 'percentage', 'rate' => '0.09',
            'scope' => 'Ordinary covered GSIS member; excludes special membership cases.',
            'source' => 'https://www.gsis.gov.ph/active-members/contributions/',
        ],
        'pagibig' => [
            'method' => 'tiered_percentage', 'threshold' => '1500.00',
            'low_rate' => '0.01', 'rate' => '0.02', 'ceiling' => '10000.00',
            'scope' => 'Employee mandatory savings only; no voluntary additional savings.',
            'source' => 'https://naro.law.upd.edu.ph/storage/01JQWKTTA92H57M9EWV31W8F1D.pdf',
        ],
        'philhealth' => [
            'method' => 'shared_premium', 'rate' => '0.05', 'employee_share' => '0.50',
            'floor' => '10000.00', 'ceiling' => '100000.00',
            'scope' => 'Employed direct contributor with equal employer sharing only.',
            'source' => 'https://www.philhealth.gov.ph/circulars/2020/circ2020-0005.pdf',
        ],
    ],
    'tax' => [
        'bonus_exemption' => '90000.00',
        'source' => 'https://bir-cdn.bir.gov.ph/local/pdf/RR%20No.%2011-2018.pdf',
        'monthly_source' => 'https://bir-cdn.bir.gov.ph/local/pdf/Annex%20E%20RR%2011-2018.pdf',
        // [lower bound, fixed tax, marginal rate]. Highest applicable lower bound wins.
        'monthly_brackets' => [
            ['0.00', '0.00', '0.00'], ['20833.00', '0.00', '0.15'],
            ['33333.00', '1875.00', '0.20'], ['66667.00', '8541.80', '0.25'],
            ['166667.00', '33541.80', '0.30'], ['666667.00', '183541.80', '0.35'],
        ],
        'annual_brackets' => [
            ['0.00', '0.00', '0.00'], ['250000.00', '0.00', '0.15'],
            ['400000.00', '22500.00', '0.20'], ['800000.00', '102500.00', '0.25'],
            ['2000000.00', '402500.00', '0.30'], ['8000000.00', '2202500.00', '0.35'],
        ],
    ],
];
