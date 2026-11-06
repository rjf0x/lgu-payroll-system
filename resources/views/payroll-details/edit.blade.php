@extends('layouts.app')

@section('title', 'Edit Payroll Detail')
@section('heading', 'Payroll Detail · '.$detail->employee_name_snapshot)

@section('content')
    <form
        method="POST"
        action="{{ route('payroll-details.update', [$run, $detail]) }}"
        class="space-y-5"
    >
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h3 class="text-sm font-semibold text-slate-700">Employee</h3>
                <dl class="mt-3 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Position</dt><dd>{{ $detail->position_snapshot }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Basic Pay</dt><dd class="font-mono">₱{{ number_format((float) $detail->basic_pay, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Days Present</dt><dd>{{ number_format((float) $detail->days_present, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Days Absent</dt><dd>{{ number_format((float) $detail->days_absent, 2) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Days On Leave</dt><dd>{{ number_format((float) $detail->days_on_leave, 2) }}</dd></div>
                </dl>
                <p class="mt-4 text-xs text-slate-500">
                    Statutory contributions and withholding tax are computed automatically when you save.
                </p>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Earnings</div>
                <div class="divide-y divide-slate-100">
                    @foreach ($earningTypes as $earningType)
                        <div class="flex items-center justify-between gap-3 px-5 py-2">
                            <label for="earning-{{ $earningType->earning_type_id }}" class="text-sm text-slate-700">
                                {{ $earningType->earning_name }}
                                @if ($earningType->is_taxable)<span class="text-[10px] text-amber-600">taxable</span>@endif
                            </label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="earning-{{ $earningType->earning_type_id }}"
                                name="earnings[{{ $earningType->earning_type_id }}]"
                                value="{{ old('earnings.'.$earningType->earning_type_id, $earningAmounts[$earningType->earning_type_id] ?? '') }}"
                                placeholder="0.00"
                                class="w-32 rounded-md border border-slate-300 px-2 py-1 text-right text-sm"
                            >
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="border-b border-slate-100 px-5 py-3 text-sm font-semibold text-slate-700">Deductions</div>
                <div class="max-h-96 divide-y divide-slate-100 overflow-y-auto">
                    @foreach ($deductionTypes as $deductionType)
                        <div class="flex items-center justify-between gap-3 px-5 py-2">
                            <label for="deduction-{{ $deductionType->deduction_type_id }}" class="text-sm text-slate-700">{{ $deductionType->deduction_name }}</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                id="deduction-{{ $deductionType->deduction_type_id }}"
                                name="deductions[{{ $deductionType->deduction_type_id }}]"
                                value="{{ old('deductions.'.$deductionType->deduction_type_id, $deductionAmounts[$deductionType->deduction_type_id] ?? '') }}"
                                placeholder="0.00"
                                class="w-32 rounded-md border border-slate-300 px-2 py-1 text-right text-sm"
                            >
                        </div>
                    @endforeach
                </div>
                <div class="border-t border-slate-100 px-5 py-3">
                    <label for="lbp_loan_deduction" class="text-sm font-medium text-slate-700">LBP Loan Deduction (after NTHP)</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        id="lbp_loan_deduction"
                        name="lbp_loan_deduction"
                        value="{{ old('lbp_loan_deduction', $detail->lbp_loan_deduction) }}"
                        class="mt-1 w-full rounded-md border border-slate-300 px-2 py-1 text-right text-sm"
                    >
                </div>
            </div>
        </div>

        <div class="flex gap-3">
            <button class="rounded-md bg-emerald-700 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Save &amp; recompute</button>
            <a href="{{ route('payroll-runs.show', $run) }}" class="rounded-md border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-50">Cancel</a>
        </div>
    </form>
@endsection
