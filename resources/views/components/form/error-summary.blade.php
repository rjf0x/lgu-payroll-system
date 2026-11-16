@if ($errors->any())
    <x-ui.alert variant="destructive" :dismissible="false" :auto-dismiss="false" title="" id="error-summary" tabindex="-1" aria-labelledby="error-summary-title" data-error-summary class="mb-6">
        <h2 id="error-summary-title" class="font-semibold">There is a problem with your submission</h2>
        <p class="mt-1 text-sm">Check the following fields. Your entered values have been kept.</p>
        <ul class="mt-3 list-inside list-disc space-y-2 text-sm">
            @foreach ($errors->messages() as $field => $messages)
                <li><a href="#{{ str_replace('.', '-', $field) }}" data-error-field="{{ $field }}" class="font-semibold underline underline-offset-4">{{ $messages[0] }}</a></li>
            @endforeach
        </ul>
    </x-ui.alert>
@endif
