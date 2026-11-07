@php
    $routeName = request()->route()?->getName();
    $resource = explode('.', $routeName ?? '')[0];
    $parentResource = match ($resource) {
        'payroll-details', 'payslips' => 'payroll-runs',
        'blockchain-anchors' => 'blockchain',
        default => $resource,
    };
    $parentRoute = $parentResource.'.index';
    $hasParent = $routeName !== $parentRoute && \Illuminate\Support\Facades\Route::has($parentRoute);
    $parentLabel = match ($parentResource) {
        'payroll-runs' => 'Monthly Payroll',
        'audit-logs' => 'Audit trail',
        'blockchain' => 'Blockchain Verification',
        default => \Illuminate\Support\Str::headline($parentResource),
    };
@endphp

@if ($routeName !== 'dashboard')
    <nav aria-label="Breadcrumb" class="mb-4 text-sm text-muted">
        <ol class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <li><a href="{{ route('dashboard') }}" class="font-medium text-brand hover:underline">Dashboard</a></li>
            @if ($hasParent)
                <li aria-hidden="true">/</li>
                <li><a href="{{ route($parentRoute) }}" class="font-medium text-brand hover:underline">{{ $parentLabel }}</a></li>
            @endif
            <li aria-hidden="true">/</li>
            <li aria-current="page">@yield('title', 'Current page')</li>
        </ol>
    </nav>
@endif
