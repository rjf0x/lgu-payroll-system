@props(['name'])

@php
    $path = match ($name) {
        'Dashboard' => 'M3 10 12 3l9 7M5 9v12h5v-7h4v7h5V9',
        'Organization' => 'M3 21h18M5 21V7h8v14M13 11h6v10M8 10h2M8 14h2M8 18h2M16 14h1M16 18h1',
        'Compensation' => 'M3 7h16v4M3 7V5h16M3 7v13h18V9H3m13 4h5v4h-5z',
        'Earnings & Deductions' => 'M4 7h15m-4-4 4 4-4 4M20 17H5m4-4-4 4 4 4',
        'Statutory' => 'M6 3h9l4 4v14H6zM15 3v5h4M9 12h7M9 16l2 2 4-4',
        'Attendance' => 'M4 5h16v16H4zM8 3v4M16 3v4M4 10h16M8 14h2M14 14h2M8 18h2',
        'Leave' => 'M12 3a9 9 0 1 0 0 18 9 9 0 0 0 0-18ZM12 7v5l3 2',
        'Payroll' => 'M3 6h18v13H3zM3 10h18M7 15h4M16 15h1',
        'Administration' => 'M12 3 4 7v6c0 4 8 8 8 8s8-4 8-8V7l-8-4Zm-4 9 3 3 5-6',
        default => 'M5 4h14v16H5zM9 8h6M9 12h6M9 16h4',
    };
@endphp

<svg {{ $attributes->class(['h-5 w-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
