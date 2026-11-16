@props(['variant' => 'success'])

<x-ui.alert :variant="$variant" :dismissible="false" :auto-dismiss="false" title="" {{ $attributes }}>{{ $slot }}</x-ui.alert>
