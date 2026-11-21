@props(['employment' => false])

<details class="rounded-lg border border-slate-200 p-4" @if ($errors->hasAny(['change_reason', 'change_effective_date'])) open @endif>
    <summary class="cursor-pointer text-sm font-semibold">Change notes (optional)</summary>
    <div class="mt-4 space-y-4">
        <x-form.textarea name="change_reason" label="Reason for change" maxlength="2000" :rows="2" hint="Record the reason or approval reference for this change. If left blank, the audit records the operation as its reason." />
        @if ($employment)
            <x-form.input name="change_effective_date" label="Change effective date" type="date" hint="For an appointment change or transfer. If omitted, history uses the appointment start or recording date." />
        @endif
    </div>
</details>
