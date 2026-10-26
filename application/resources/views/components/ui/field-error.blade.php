@props(['field'])
@error($field)
    <p class="field-error" id="field-error-{{ str_replace(['.', '[', ']'], '-', $field) }}" data-field-error="{{ $field }}">{{ $message }}</p>
@enderror
