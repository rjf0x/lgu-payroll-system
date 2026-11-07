@props([
    'name',
    'label' => null,
    'value' => null,
    'rows' => 3,
    'required' => false,
    'id' => null,
    'hint' => null,
])

@php
    $errorKey = str_replace(['[', ']'], ['.', ''], $name);
    $fieldId = $id ?? str_replace(['[', ']', '.'], ['-', '', '-'], $name);
    $description = trim(implode(' ', array_filter([$attributes->get('aria-describedby'), $hint ? $fieldId.'-hint' : null, $errors->has($errorKey) ? $fieldId.'-error' : null])));
@endphp

<div class="min-w-0">
    @if ($label)
        <label for="{{ $fieldId }}" class="mb-1.5 block text-sm font-semibold text-slate-700">
            {{ $label }}@if ($required)<span class="text-rose-700" aria-hidden="true"> *</span><span class="sr-only"> (required)</span>@endif
        </label>
    @endif

    <textarea
        id="{{ $fieldId }}"
        name="{{ $name }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
        @if ($description !== '') aria-describedby="{{ $description }}" @endif
        {{ $attributes->except(['aria-describedby', 'aria-invalid'])->class(['ui-field']) }}
    >{{ old($errorKey, $value) }}</textarea>

    <x-form.feedback :name="$errorKey" :id="$fieldId" :hint="$hint" />
</div>
