@extends('layouts.app')
@section('content')
    <a href="{{ route('payroll-calculations.index', $batch) }}" class="text-teal-800 underline">All preserved previews</a>
    <h1 class="mt-5 text-3xl font-bold">Core preview {{ $run->id }} · {{ $batch->period_start->format('F Y') }}</h1>
    <p class="mt-3 font-semibold text-amber-900">Provisional demonstration — not for real payroll. Core net excludes statutory deductions.</p>
    <p class="mt-2">Engine {{ $run->engine_version }} · Draft version {{ $run->batch_version }} · Saved {{ $run->created_at->format('Y-m-d H:i') }}</p>
    @if ($run->batch_version !== $batch->record_version)<p class="mt-4 font-semibold text-amber-900">Draft changed since this preview. Saved results remain unchanged; review inputs and calculate a new version.</p>@endif
    <section class="mt-6 rounded border border-slate-200 bg-white p-6">
        <h2 class="text-xl font-semibold">Saved totals for all covered employees in this preview</h2>
        @include('payroll-calculations.totals', ['totals' => $run->totals])
        <p class="mt-4 text-amber-900">GSIS, Pag-IBIG, PhilHealth and withholding tax: not calculated. Attendance deduction formulas: unavailable. These components are not assumed to be zero.</p>
        <p class="mt-2 text-sm text-slate-600">Amounts use exact two-decimal addition and subtraction. No fractional rate or proration rounding rule is inferred. Source-review notices below cover this displayed page, not overall clearance.</p>
    </section>
    @foreach ($items as $item)
        <article class="mt-6 rounded border border-slate-200 bg-white p-6">
            <h2 class="text-xl font-semibold">{{ $item->entry_snapshot['employee_number'] }} · {{ $item->entry_snapshot['employee_name'] }}</h2>
            <p class="mt-2">{{ $item->entry_snapshot['position'] }} · {{ $item->entry_snapshot['personnel_classification'] }} · Grade {{ $item->entry_snapshot['grade'] }} / Step {{ $item->entry_snapshot['step'] }}</p>
            <p class="mt-2">Input revision {{ $item->input_snapshot['revision'] }} · Authority: {{ $item->input_snapshot['authority_reference'] }} · Reason: {{ $item->input_snapshot['change_reason'] }}</p>
            @if ($issues[$item->id])<p class="mt-4 font-semibold text-amber-900">Needs review: {{ $issues[$item->id] }}</p>@endif
            @if ($item->breakdown['negative_net'])<p class="mt-4 font-semibold text-red-800">Negative core net pay. Review entered deductions; no floor or statutory limit has been applied.</p>@endif
            @include('payroll-calculations.totals', ['totals' => $item->breakdown['totals']])
            <p class="mt-4 text-sm text-slate-600">{{ $item->breakdown['basis'] }}</p>
            <h3 class="mt-4 font-semibold">Line-by-line inputs</h3>
            @forelse ($item->breakdown['lines'] as $line)
                <div class="calculation-line"><div><strong>{{ $line['label'] }}</strong><p>{{ ucfirst(str_replace('_', ' ', $line['bucket'])) }} · {{ $line['included'] ? 'Included' : 'Excluded — not applicable' }}</p>@if ($line['original_amount'] !== null)<p>Original reference PHP {{ $line['original_amount'] }} · Reason: {{ $line['reason'] }}</p>@endif</div><strong>PHP {{ $line['amount'] }}</strong></div>
            @empty<p class="mt-2">No extra earnings, loans or adjustments entered.</p>@endforelse
        </article>
    @endforeach
    <div class="mt-5">{{ $items->links() }}</div>
@endsection
