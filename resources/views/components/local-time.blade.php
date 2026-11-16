@props(['value', 'format' => 'M d, Y g:i A', 'fallback' => '', 'showTimezone' => false])

@if ($value !== null)
    @php
        $localTime = $value->copy()->setTimezone(config('app.display_timezone'));
    @endphp
    <time {{ $attributes->merge(['datetime' => $localTime->toIso8601String()]) }}>{{ $localTime->format($format) }}@if ($showTimezone) {{ config('app.display_timezone') }}@endif</time>
@else
    {{ $fallback }}
@endif
