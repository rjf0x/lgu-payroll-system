<?php

namespace App\Http\Controllers;

use App\Models\GeneratedReport;
use App\Models\PayrollRun;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(): View
    {
        $reports = GeneratedReport::query()
            ->with('generatedBy')
            ->orderByDesc('report_id')
            ->paginate(20);

        $runs = PayrollRun::query()
            ->with('payrollPeriod')
            ->withCount('details')
            ->orderByDesc('payroll_run_id')
            ->limit(10)
            ->get();

        return view('reports.index', compact('reports', 'runs'));
    }

    public function summary(PayrollRun $payrollRun): View
    {
        $payrollRun->load(['payrollPeriod', 'details']);

        $byDepartment = $payrollRun->details
            ->groupBy('department_snapshot')
            ->map(fn ($group) => [
                'count' => $group->count(),
                'gross' => round((float) $group->sum('gross_pay'), 2),
                'deductions' => round((float) $group->sum('total_deductions'), 2),
                'net' => round((float) $group->sum('net_pay'), 2),
            ])
            ->sortKeys();

        return view('reports.payroll-summary', [
            'run' => $payrollRun,
            'byDepartment' => $byDepartment,
        ]);
    }
}
