@props(['label' => 'Row actions'])

@if (trim((string) $slot) !== '')
    @php($menuId = 'action-menu-'.Illuminate\Support\Str::uuid())

    <div data-action-menu {{ $attributes->class(['no-print inline-block align-middle']) }}>
        <button type="button" class="ui-button ui-button-secondary ui-button-compact w-9 px-0" popovertarget="{{ $menuId }}" aria-controls="{{ $menuId }}" aria-label="{{ $label }}" title="{{ $label }}" data-action-menu-trigger>
            <svg class="size-5 shrink-0" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
                <circle cx="12" cy="5" r="1.75" />
                <circle cx="12" cy="12" r="1.75" />
                <circle cx="12" cy="19" r="1.75" />
            </svg>
        </button>
        <div id="{{ $menuId }}" popover="auto" role="group" aria-label="{{ $label }}" class="ui-action-menu-panel" data-action-menu-panel>
            {{ $slot }}
        </div>
    </div>
@endif
