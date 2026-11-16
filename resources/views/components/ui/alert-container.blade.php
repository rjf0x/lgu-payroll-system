@props([
    'presentation' => 'toast',
    'appearance' => 'light',
    'size' => 'md',
    'dismissible' => true,
    'autoDismiss' => null,
    'duration' => null,
])

@php
    $toast = $presentation !== 'inline';
    $messages = [];
    foreach (['success' => 'success', 'status' => 'success', 'error' => 'destructive', 'warning' => 'warning', 'info' => 'info', 'primary' => 'primary', 'secondary' => 'secondary', 'mono' => 'mono'] as $key => $variant) {
        $message = session($key);
        if (is_string($message) && trim($message) !== '') {
            $messages[$variant."\0".$message] = ['variant' => $variant, 'message' => $message];
        }
    }
@endphp

@if ($messages !== [])
    @once
        <section aria-label="Notifications" {{ $attributes->class([
            'no-print flex flex-col gap-3',
            'ui-alert-toasts pointer-events-none fixed right-4 top-40 z-30 max-h-[calc(100dvh-11rem)] w-[calc(100%_-_2rem)] max-w-sm overflow-y-auto p-1 sm:right-6 md:top-24 md:max-h-[calc(100dvh-7rem)]' => $toast,
            'mb-5' => ! $toast,
        ]) }}>
            @foreach ($messages as $notification)
                <x-ui.alert
                    :variant="$notification['variant']"
                    :message="$notification['message']"
                    :appearance="$appearance"
                    :size="$size"
                    :dismissible="$dismissible"
                    :auto-dismiss="$autoDismiss"
                    :duration="$duration"
                    :toast="$toast"
                    :class="$toast ? 'pointer-events-auto shadow-sm' : ''"
                />
            @endforeach
        </section>
    @endonce
@endif
