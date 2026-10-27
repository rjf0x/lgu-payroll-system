<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('document-title') - {{ $document['period'] }}</title>
    @if (! $pdf) @vite(['resources/js/app.js']) @endif
    <style>
        @page { size: A4 {{ $landscape ? 'landscape' : 'portrait' }}; margin: 27mm 12mm 20mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #172b40; background: white; font-family: 'DejaVu Sans', sans-serif; font-size: {{ $landscape ? '8' : '9' }}pt; line-height: 1.4; }
        h1 { margin: 0 0 4mm; font-size: 19pt; color: #123c50; }
        h2 { margin: 7mm 0 3mm; font-size: 12pt; page-break-after: avoid; }
        p { margin: 0 0 3mm; }
        a { color: #155e75; }
        .document-header { padding-bottom: 3mm; border-bottom: 1px solid #155e75; }
        .document-header strong { font-size: 10pt; }
        .warning { color: #834007; font-weight: bold; font-size: 8pt; }
        .document-footer { color: #546475; font-size: 7pt; }
        .meta { color: #546475; margin: 4mm 0; }
        .muted { color: #546475; font-weight: normal; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        th, td { border-bottom: 1px solid #cbd5df; padding: 2.5mm 1.3mm; text-align: left; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
        th { background: #edf4f7; color: #123c50; }
        .money { text-align: right; font-family: 'DejaVu Sans Mono', monospace; font-size: {{ $landscape ? '6.8' : '9' }}pt; }
        .label { width: 77%; }
        .employee-cell { width: 30%; }
        .register th, .register td { padding: 1.3mm 1mm; font-size: 7pt; line-height: 1.15; }
        .register .money { font-size: 6.8pt; }
        .register tbody th { background: white; }
        .register .grand-total th { background: #edf4f7; }
        .grand-total td, .grand-total th { border-top: 2px solid #155e75; background: #edf4f7; font-weight: bold; }
        .identity td { border: 0; padding: 1mm 2mm 2mm 0; }
        .net { margin: 7mm 0; padding: 5mm; background: #edf4f7; border-top: 3px solid #155e75; font-size: 14pt; }
        .toolbar { margin: 0 0 8mm; padding: 4mm; background: #edf4f7; }
        .toolbar a, .toolbar button { margin-right: 5mm; }
        .toolbar button { border: 0; border-radius: 3px; background: #155e75; color: white; padding: 8px 14px; cursor: pointer; }
        @if ($pdf)
            .document-header { position: fixed; top: -20mm; left: 0; right: 0; }
            .document-footer { position: fixed; bottom: -11mm; left: 0; right: 0; }
        @else
            body { max-width: {{ $landscape ? '1400' : '850' }}px; padding: 24px; margin: auto; }
            .document-header { margin-bottom: 6mm; }
            .document-footer { margin-top: 8mm; }
            .table-scroll { overflow-x: auto; }
            .register { min-width: 1000px; }
            @media print {
                body { max-width: none; padding: 0; }
                .toolbar { display: none; }
                .document-header { position: fixed; top: -20mm; left: 0; right: 0; }
                .document-footer { position: fixed; bottom: -11mm; left: 0; right: 0; margin: 0; }
                .table-scroll { overflow: visible; }
                .register { min-width: 0; }
            }
        @endif
    </style>
</head>
<body>
    @if (! $pdf)
        <nav class="toolbar" aria-label="Document actions">
            <a href="{{ route('payroll-documents.index', $batch) }}">All finalized documents</a>
            <button type="button" data-print-document hidden>Print document</button>
            @yield('document-actions')
            <p class="meta">Use Print document or your browser's Print command. Register: A4 landscape. Payslip: A4 portrait.</p>
        </nav>
    @endif
    <header class="document-header">
        <strong>LGU Payroll Management System / Accounting Office</strong><br>
        <span class="warning">DEMONSTRATION ONLY - NOT FOR REAL PAYROLL</span>
    </header>
    <main>
        @yield('document-body')
    </main>
    <footer class="document-footer">Payroll {{ $document['period'] }} / Finalized reference #{{ $finalization->id }} / Version {{ $finalization->version }}@if (! $landscape && isset($row)) / Employee {{ $row['employee']['employee_number'] }}@endif<br>{{ $finalization->created_at->format('Y-m-d H:i') }} / Prototype layout - client template validation pending.</footer>
</body>
</html>
