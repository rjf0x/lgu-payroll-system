@extends('layouts.app')
@section('title', 'System settings')
@section('heading', 'System settings')
@section('content')
    <x-page-header description="Manage the workspace identity and support contact. Every saved change is recorded in the audit trail." />
    <div class="grid min-w-0 gap-6 xl:grid-cols-2">
        <form method="POST" action="{{ route('system-settings.update') }}" data-submit-feedback class="ui-card space-y-5">
            @csrf
            @method('PUT')
            <h2 class="text-lg font-semibold">General settings</h2>
            <x-form.input name="settings[system_name]" label="System name" :value="$settings['system_name']" maxlength="150" required />
            <x-form.input name="settings[organization_name]" label="Organization name" :value="$settings['organization_name']" maxlength="150" required />
            <x-form.input name="settings[support_email]" label="Support email" type="email" :value="$settings['support_email']" maxlength="150" hint="Contact address for system support. This does not change email delivery configuration." />
            <x-button type="submit" variant="primary">Save settings</x-button>
        </form>
        <x-card heading="Enforced configuration">
            <dl class="space-y-4 text-sm">
                @foreach (['Payroll frequency' => 'Monthly', 'Currency' => 'PHP', 'Timezone' => config('app.timezone'), 'Role-based access' => 'Required', 'Audit logging' => 'Required', 'Independent payroll review' => 'Required', 'Return and rejection reasons' => 'Required', 'Blockchain driver' => config('blockchain.driver')] as $label => $value)
                    <div class="flex flex-wrap justify-between gap-2 border-b border-slate-100 pb-3"><dt class="text-muted">{{ $label }}</dt><dd class="font-semibold">{{ $value }}</dd></div>
                @endforeach
            </dl>
            <p class="mt-4 text-sm text-muted">These controls follow the application configuration and workflow rules. Display settings do not change payroll calculations or historical reports.</p>
            @can('audit.view')<x-button :href="route('audit-logs.index', ['module' => 'System Settings'])" class="mt-4">View settings activity</x-button>@endcan
        </x-card>
    </div>
@endsection
