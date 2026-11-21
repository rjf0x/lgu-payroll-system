@extends('layouts.app')

@section('title', 'Audit event #'.$log->audit_id)
@section('heading', 'Audit event #'.$log->audit_id)

@section('content')
    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <p class="text-sm text-slate-600">Compare the saved before and after values for this event. Secrets are redacted.</p>
        <x-button icon="back" href="{{ route('audit-logs.index') }}">Back to audit trail</x-button>
    </div>
    <dl class="ui-card mb-5 grid gap-4 text-sm sm:grid-cols-2">
        <div><dt class="font-semibold">Action</dt><dd class="break-words">{{ $log->module }} / {{ $log->action }}</dd></div>
        <div><dt class="font-semibold">Recorded at</dt><dd><x-local-time :value="$log->created_at" format="M d, Y H:i:s" /></dd></div>
        <div><dt class="font-semibold">Recorded by</dt><dd>{{ $after['audit_actor']['username'] ?? $log->user?->username ?? 'System / unavailable user' }}</dd></div>
        <div><dt class="font-semibold">Record</dt><dd>{{ $log->record_table ?? 'Not applicable' }} #{{ $log->record_id }}</dd></div>
    </dl>
    <div class="grid min-w-0 gap-5 lg:grid-cols-2">
        @foreach (['Before' => $before, 'After' => $after] as $label => $values)
            <section class="ui-card min-w-0" aria-label="{{ $label }} values">
                <h2 class="mb-3 font-semibold">{{ $label }}</h2>
                @if ($values === null)
                    <p class="text-sm text-slate-600">No values recorded.</p>
                @else
                    <pre tabindex="0" aria-label="{{ $label }} recorded values" class="max-w-full overflow-x-auto whitespace-pre-wrap break-words text-sm">{{ json_encode($values, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) }}</pre>
                @endif
            </section>
        @endforeach
    </div>
@endsection
