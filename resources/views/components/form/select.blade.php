@props([
    'name',
    'label' => null,
    'options' => [],
    'selected' => null,
    'placeholder' => null,
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

    <select
        id="{{ $fieldId }}"
        name="{{ $name }}"
        @if ($required) required @endif
        aria-invalid="{{ $errors->has($errorKey) ? 'true' : 'false' }}"
        @if ($description !== '') aria-describedby="{{ $description }}" @endif
        {{ $attributes->except(['aria-describedby', 'aria-invalid'])->class(['ui-field']) }}
    >
        @if ($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) old($errorKey, $selected) === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    <x-form.feedback :name="$errorKey" :id="$fieldId" :hint="$hint" />
</div>
