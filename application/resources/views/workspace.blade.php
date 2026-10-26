@extends('layouts.app')
@section('title', $role->label().' workspace · LGU Payroll')
@section('content')
    <div class="page-heading"><div><p class="eyebrow">Your assigned tools</p><h1>{{ $role->label() }} workspace</h1><p>{{ match ($role) {
        \App\Enums\Role::Administrator => 'Manage account access and authorized salary schedules.',
        \App\Enums\Role::PayrollOfficer => 'Keep employee records, monthly inputs and calculation previews connected.',
        \App\Enums\Role::Approver => 'View employee sources, draft inputs and preserved calculation previews.',
        \App\Enums\Role::Viewer => 'Find employee information and view saved payroll preparation records.',
    } }}</p></div></div>
    @include('partials.workspace-cards')
    <section class="workspace-guidance"><x-ui.icon name="shield" /><div><h2>{{ $role === \App\Enums\Role::Administrator ? 'Account and configuration responsibilities' : 'A clear view of preparation' }}</h2><p>{{ $role === \App\Enums\Role::PayrollOfficer ? 'Start with employee information and authorized salary sources. Select monthly coverage, save each employee’s inputs, then review a demonstration core preview.' : ($role === \App\Enums\Role::Administrator ? 'Payroll preparation and employee records are assigned to separate roles. Use User accounts to manage access.' : 'Your access is read-only. Approval and finalization actions are not available in the current prototype.') }}</p></div></section>
@endsection
