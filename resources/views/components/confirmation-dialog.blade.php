<dialog id="confirmation-dialog" aria-labelledby="confirmation-title" aria-describedby="confirmation-description confirmation-context" class="m-auto w-[calc(100%_-_2rem)] max-w-lg rounded-xl border border-slate-300 bg-white p-6 text-ink shadow-xl backdrop:bg-slate-950/60">
    <h2 id="confirmation-title" class="text-xl font-semibold"></h2>
    <p id="confirmation-context" class="mt-3 rounded-lg bg-slate-50 p-3 text-sm font-semibold"></p>
    <p id="confirmation-description" class="mt-4 text-muted"></p>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
        <x-button id="confirmation-cancel" autofocus>Cancel</x-button>
        <x-button id="confirmation-accept" variant="primary">Confirm</x-button>
    </div>
</dialog>
