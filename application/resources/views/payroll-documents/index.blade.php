@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-reviews.show', $batch) }}" class="text-teal-800 underline">Payroll review and finalized snapshot</a>
    <h1 class="mt-5 text-3xl font-bold">Finalized payroll documents</h1>
    <p class="mt-3 font-semibold">{{ $document['period'] }} / Batch #{{ $batch->id }} / Finalized version {{ $finalization->version }}</p>
    <p class="mt-3 font-semibold text-amber-900">Demonstration only - not for real payroll. Prototype layouts require client template validation.</p>
    <div class="mt-5 flex flex-wrap gap-4">
        <a class="button button-primary" href="{{ route('payroll-documents.register', [$batch, $finalization]) }}">View / print payroll register</a>
        <a class="button button-secondary" href="{{ route('payroll-documents.register-pdf', [$batch, $finalization]) }}">Download register PDF</a>
        <a class="button button-secondary" href="{{ route('payroll-documents.register-csv', [$batch, $finalization]) }}">Export register CSV</a>
    </div>
    <dl class="mt-6 grid gap-4 sm:grid-cols-3">
        @foreach (['total_earnings' => 'Total earnings', 'total_deductions' => 'Total deductions', 'net_pay' => 'Net pay'] as $field => $label)
            <div class="rounded border bg-white p-4"><dt>{{ $label }}</dt><dd class="font-bold">PHP {{ $document['totals'][$field] }}</dd></div>
        @endforeach
    </dl>
    <h2 class="mt-8 text-xl font-semibold">Individual payslips</h2>
    <div class="mt-4 space-y-3">
        @foreach ($employees as $row)
            <article class="rounded border bg-white p-4"><h3 class="font-semibold">{{ $row['employee']['employee_number'] }} / {{ $row['employee']['employee_name'] }}</h3>
                <p class="mt-2">{{ $row['employee']['position'] }} / Net pay PHP {{ $row['amounts']['net_pay'] }}</p>
                <div class="mt-3 flex flex-wrap gap-4"><a class="text-teal-800 underline" href="{{ route('payroll-documents.payslip', [$batch, $finalization, $row['employee']['employee_id']]) }}">View / print payslip</a><a class="text-teal-800 underline" href="{{ route('payroll-documents.payslip-pdf', [$batch, $finalization, $row['employee']['employee_id']]) }}">Download payslip PDF</a></div>
            </article>
        @endforeach
    </div>
    <div class="mt-5">{{ $employees->links() }}</div>
@endsection
