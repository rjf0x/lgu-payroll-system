@extends('payroll-documents.layout', ['landscape' => true])
@section('document-title', 'Payroll register')
@section('document-actions')
    <a href="{{ route('payroll-documents.register-pdf', [$batch, $finalization]) }}">Download PDF</a>
    <a href="{{ route('payroll-documents.register-csv', [$batch, $finalization]) }}">Export CSV</a>
@endsection
@section('document-body')
    <h1>Payroll register</h1>
    <p class="meta">Payroll month: <strong>{{ $document['period'] }}</strong> / Batch #{{ $batch->id }} / Version {{ $finalization->version }} / {{ count($document['rows']) }} employees / All amounts in PHP</p>
    <p>Preserved finalized values. Other earnings include applicable allowances, bonuses and earning adjustments. Loans and adjustments include manual loan deductions and deduction adjustments.</p>
    <div class="table-scroll">
        <table class="register">
            <thead><tr><th scope="col" class="employee-cell">Employee / Position / Classification</th>@foreach ($document['columns'] as $field => $label)<th scope="col">{{ match ($field) { 'manual_deductions' => 'Loans / adj.', 'withholding' => 'Tax', 'total_deductions' => 'Deductions', 'total_earnings' => 'Earnings', default => $label } }}</th>@endforeach</tr></thead>
            <tbody>
                @foreach ($document['rows'] as $row)
                    <tr data-employee-id="{{ $row['employee']['employee_id'] }}">
                        <th scope="row" class="employee-cell"><strong>{{ $row['employee']['employee_name'] }}</strong><br><span class="muted">{{ $row['employee']['employee_number'] }} / {{ \App\Enums\PersonnelClassification::from($row['employee']['personnel_classification'])->label() }}<br>{{ $row['employee']['position'] }} / {{ $row['employee']['office'] }}<br>Grade {{ $row['employee']['grade'] }} / Step {{ $row['employee']['step'] }}</span></th>
                        @foreach ($row['amounts'] as $field => $amount)<td class="money" data-amount="{{ $field }}">{{ $amount }}</td>@endforeach
                    </tr>
                @endforeach
                <tr class="grand-total"><th scope="row">Batch totals - all employees</th>@foreach ($document['totals'] as $field => $amount)<td class="money" data-total="{{ $field }}">{{ $amount }}</td>@endforeach</tr>
            </tbody>
        </table>
    </div>
    <p class="meta">Finalized demonstration payroll; not proof of payment. Contributions and withholding are preserved reviewed scenarios with unvalidated client applicability.</p>
@endsection
