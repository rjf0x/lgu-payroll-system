@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'required' => false,
    'step' => null,
    'id' => null,
    'hint' => null,
])

@php
    $fieldId = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $description = trim(implode(' ', array_filter([$attributes->get('aria-describedby'), $hint ? $fieldId.'-hint' : null, $errors->has($name) ? $fieldId.'-error' : null])));
@endphp

<div class="min-w-0">
    @if ($label)
        <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
            {{ $label }}@if ($required)<span class="text-rose-700" aria-hidden="true"> *</span><span class="sr-only"> (required)</span>@endif
        </label>
    @endif

    <input
        type="{{ $type }}"
        id="{{ $fieldId }}"
        name="{{ $name }}"
        value="{{ old($name, $value) }}"
        @if ($required) required @endif
        @if ($step) step="{{ $step }}" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($description !== '') aria-describedby="{{ $description }}" @endif
        {{ $attributes->except(['aria-describedby', 'aria-invalid'])->class(['ui-field']) }}
    >

    <x-form.feedback :name="$name" :id="$fieldId" :hint="$hint" />
</div>
