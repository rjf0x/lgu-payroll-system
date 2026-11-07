<?php

return [
    'queue_payslips' => (bool) env('QUEUE_PAYSLIP_GENERATION', false),
    'trusted_proxies' => array_values(array_filter(explode(',', (string) env('TRUSTED_PROXIES', '')))),
    'bootstrap_admin' => [
        'username' => env('INITIAL_ADMIN_USERNAME'),
        'email' => env('INITIAL_ADMIN_EMAIL'),
        'password' => env('INITIAL_ADMIN_PASSWORD'),
    ],
];
