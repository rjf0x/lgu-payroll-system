@extends('layouts.print')

@section('title', 'Payslip')

@section('content')
    @php($detail = $payslip->payrollDetail)
    @php($period = $detail->payrollRun->payrollPeriod)

    <div class="border-b border-slate-200 pb-4 text-center">
        <div class="text-sm font-semibold text-emerald-800">Payroll statement · Amounts in PHP</div>
        <h1 class="mt-1 text-xl font-bold">Employee Payslip</h1>
        <p class="text-sm text-slate-500">{{ $period->period_name }} · Pay date {{ $period->pay_date?->format('M d, Y') }}</p>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-2 print:grid-cols-2">
        <div>
            <div class="text-xs uppercase tracking-wide text-slate-500">Employee</div>
            <div class="font-semibold">{{ $detail->employee_name_snapshot }}</div>
            <div class="text-slate-500">{{ $detail->employee_number_snapshot }}</div>
        </div>
        <div class="break-words sm:text-right print:text-right">
            <div class="text-xs uppercase tracking-wide text-slate-500">Payslip No.</div>
            <div class="font-mono text-xs">{{ $payslip->payslip_number }}</div>
            <div class="text-slate-500">{{ $detail->position_snapshot }}</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 text-sm sm:grid-cols-2 print:grid-cols-2">
        <div>
            <h2 class="mb-2 border-b border-slate-200 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">Earnings</h2>
            <table class="w-full">
                <caption class="sr-only">Payslip earnings in PHP</caption>
                <tbody>
                    <tr><td class="py-1">Basic Pay</td><td class="py-1 text-right font-mono">{{ number_format((float) $detail->basic_pay, 2) }}</td></tr>
                    @foreach ($detail->earnings as $earning)
                        <tr><td class="py-1">{{ $earning->earning_name_snapshot }}</td><td class="py-1 text-right font-mono">{{ number_format((float) $earning->amount, 2) }}</td></tr>
                    @endforeach
                    <tr class="border-t border-slate-300 font-semibold"><td class="py-1">Gross / Total Compensation</td><td class="py-1 text-right font-mono">{{ number_format((float) $detail->gross_pay, 2) }}</td></tr>
                </tbody>
            </table>
        </div>

        <div>
            <h2 class="mb-2 border-b border-slate-200 pb-1 text-xs font-bold uppercase tracking-wide text-slate-600">Deductions</h2>
            <table class="w-full">
                <caption class="sr-only">Payslip deductions in PHP</caption>
                <tbody>
                    @foreach ($detail->deductions as $deduction)
                        <tr><td class="py-1">{{ $deduction->deduction_name_snapshot }}</td><td class="py-1 text-right font-mono">{{ number_format((float) $deduction->amount, 2) }}</td></tr>
                    @endforeach
                    <tr class="border-t border-slate-300 font-semibold"><td class="py-1">Total Deductions</td><td class="py-1 text-right font-mono">{{ number_format((float) $detail->total_deductions, 2) }}</td></tr>
                    <tr><td class="py-1">NTHP Before LBP</td><td class="py-1 text-right font-mono">{{ number_format((float) $detail->nthp_before_lbp, 2) }}</td></tr>
                    <tr><td class="py-1">LBP Loan Deduction</td><td class="py-1 text-right font-mono">{{ number_format((float) $detail->lbp_loan_deduction, 2) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 flex items-center justify-between rounded-lg bg-emerald-50 px-4 py-3">
        <span class="text-sm font-semibold text-emerald-800">Net Take-Home Pay</span>
        <span class="font-mono text-lg font-bold text-emerald-800">₱{{ number_format((float) $detail->net_pay, 2) }}</span>
    </div>

    @if ($detail->employerContributions->isNotEmpty())
        <div class="mt-6 text-xs text-slate-500">
            <span class="font-semibold uppercase tracking-wide">Employer contributions (not deducted):</span>
            @foreach ($detail->employerContributions as $contribution)
                {{ $contribution->contribution_type_snapshot }} ₱{{ number_format((float) $contribution->amount, 2) }}@if (! $loop->last) · @endif
            @endforeach
        </div>
    @endif

    <p class="mt-8 text-center text-xs text-muted">
        Generated {{ $payslip->generated_at?->format('M d, Y g:i A') }} · Status: {{ $payslip->publication_status }}
    </p>
@endsection
