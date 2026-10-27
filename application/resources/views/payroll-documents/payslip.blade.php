@extends('payroll-documents.layout', ['landscape' => false])
@section('document-title', 'Individual payslip')
@section('document-actions')
    <a href="{{ route('payroll-documents.payslip-pdf', [$batch, $finalization, $row['employee']['employee_id']]) }}">Download PDF</a>
@endsection
@section('document-body')
    <h1>Individual payslip</h1>
    <p class="meta">Payroll month: <strong>{{ $document['period'] }}</strong> / Batch #{{ $batch->id }} / Version {{ $finalization->version }} / All amounts in PHP</p>
    <table class="identity"><tbody>
        <tr><td><strong>Employee</strong><br>{{ $row['employee']['employee_name'] }}</td><td><strong>Employee number</strong><br>{{ $row['employee']['employee_number'] }}</td></tr>
        <tr><td><strong>Position / Office</strong><br>{{ $row['employee']['position'] }}<br>{{ $row['employee']['office'] }}</td><td><strong>Classification / Salary reference</strong><br>{{ \App\Enums\PersonnelClassification::from($row['employee']['personnel_classification'])->label() }}<br>Grade {{ $row['employee']['grade'] }} / Step {{ $row['employee']['step'] }}</td></tr>
    </tbody></table>
    <h2>Earnings</h2>
    <table><thead><tr><th scope="col" class="label">Description</th><th scope="col" class="money">PHP</th></tr></thead><tbody>
        @foreach ($row['earnings'] as $line)<tr><td>{{ $line['label'] }}</td><td class="money">{{ $line['amount'] }}</td></tr>@endforeach
        <tr class="grand-total"><th scope="row">Total earnings</th><td class="money" data-total="total_earnings">{{ $row['amounts']['total_earnings'] }}</td></tr>
    </tbody></table>
    <h2>Deductions</h2>
    <table><thead><tr><th scope="col" class="label">Description</th><th scope="col" class="money">PHP</th></tr></thead><tbody>
        @foreach ($row['deductions'] as $line)<tr><td>{{ $line['label'] }}</td><td class="money">{{ $line['amount'] }}</td></tr>@endforeach
        <tr class="grand-total"><th scope="row">Total deductions</th><td class="money" data-total="total_deductions">{{ $row['amounts']['total_deductions'] }}</td></tr>
    </tbody></table>
    <div class="net"><strong>Net pay: PHP <span data-total="net_pay">{{ $row['amounts']['net_pay'] }}</span></strong></div>
    <p>Only applicable earnings and authorized adjustment replacement amounts are included. Contribution and withholding amounts are preserved reviewed demonstration scenarios.</p>
    <p class="muted">This payslip reproduces finalized demonstration values. It is not proof of payment or confirmation of statutory/client approval.</p>
@endsection
