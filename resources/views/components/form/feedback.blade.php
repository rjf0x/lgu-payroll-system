@props(['name', 'id', 'hint' => null])

@if ($hint)
    <p id="{{ $id }}-hint" class="mt-2 text-sm text-muted">{{ $hint }}</p>
@endif
@error($name)
    <p id="{{ $id }}-error" class="mt-2 text-sm font-medium text-rose-800">{{ $message }}</p>
@enderror
