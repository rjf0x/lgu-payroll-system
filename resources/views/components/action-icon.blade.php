@props(['name'])

@php
    $path = match ($name) {
        'view' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Zm10-3a3 3 0 1 0 0 6 3 3 0 0 0 0-6',
        'edit' => 'm16 3 5 5L8 21H3v-5L16 3Zm-3 3 5 5',
        'delete' => 'M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7M14 10v7',
        'add' => 'M12 4v16M4 12h16',
        'back' => 'm10 5-7 7 7 7M3 12h18',
        'list' => 'M9 6h12M9 12h12M9 18h12M3 6h1M3 12h1M3 18h1',
        'lock' => 'M5 10h14v11H5V10Zm3 0V7a4 4 0 0 1 8 0v3M12 14v3',
        'archive' => 'M3 3h18v5H3V3Zm2 5v13h14V8M9 12h6',
        'download' => 'M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5',
        'print' => 'M7 8V3h10v5M7 17H3V8h18v9h-4M7 14h10v7H7v-7M17 11h1',
        'verify' => 'm12 3-8 4v6c0 4 8 8 8 8s8-4 8-8V7l-8-4Zm-4 9 3 3 5-6',
        'approve' => 'm4 12 5 5L20 6',
        'reject' => 'm5 5 14 14M19 5 5 19',
        'return' => 'm8 4-5 5 5 5M3 9h11a6 6 0 0 1 0 12h-3',
        'send' => 'm3 3 18 9-18 9 4-9-4-9Zm4 9h14',
        'calculate' => 'M5 3h14v18H5V3Zm3 3h8v4H8V6Zm0 8h1m3 0h1m3 0h1M8 17h1m3 0h1m3 0h1',
        'search' => 'M10 3a7 7 0 1 0 0 14 7 7 0 0 0 0-14Zm5 12 6 6',
        'filter' => 'M3 4h18l-7 8v7l-4 2v-9L3 4Z',
        'reset' => 'M3 10a9 9 0 1 1 2 8M3 4v6h6',
        'document' => 'M6 3h9l4 4v14H6V3Zm9 0v5h4M9 12h7M9 16h7',
        default => 'M4 4h16v16H4V4Zm4 8 3 3 5-6',
    };
@endphp

<svg {{ $attributes->class(['size-5 shrink-0']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
