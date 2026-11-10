<?php

namespace App\Http\Controllers;

use App\Models\GeneratedReport;
use App\Models\PayrollDeduction;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['DRAFT', 'PENDING_APPROVAL', 'RETURNED', 'REJECTED', 'APPROVED', 'FINALIZED'])],
        ]);
        $reports = GeneratedReport::query()
            ->with('generatedBy')
            ->orderByDesc('report_id')
            ->paginate(20, ['*'], 'history_page')->withQueryString();

        $runs = PayrollRun::query()
            ->with('payrollPeriod')
            ->withCount('details')
            ->when($filters['q'] ?? null, fn ($query, $search) => $query->whereHas('payrollPeriod', fn ($periods) => $periods->where('period_name', 'like', '%'.$search.'%')))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('payroll_run_id')
            ->paginate(10, ['*'], 'runs_page')->withQueryString();

        $reportRuns = PayrollRun::query()->with('payrollPeriod')
            ->whereIn('payroll_run_id', $reports->getCollection()->map(fn (GeneratedReport $report) => $report->filters['payroll_run_id'] ?? null)->filter(fn ($id) => is_int($id) || (is_string($id) && ctype_digit($id))))
            ->get()->keyBy('payroll_run_id');
        $reportScopes = $reports->getCollection()->mapWithKeys(function (GeneratedReport $report) use ($reportRuns): array {
            $runId = $report->filters['payroll_run_id'] ?? null;

            return [$report->getKey() => (is_int($runId) || (is_string($runId) && ctype_digit($runId))) ? $reportRuns->get($runId) : null];
        });

        return view('reports.index', compact('reports', 'runs', 'reportScopes', 'filters'));
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

    public function deductions(Request $request, PayrollRun $payrollRun): View
    {
        $payrollRun->load(['payrollPeriod', 'details.deductions']);
        $deductions = $payrollRun->details->flatMap(fn (PayrollDetail $detail) => $detail->deductions->map(fn (PayrollDeduction $deduction): array => [
            'deduction_type_id' => $deduction->deduction_type_id,
            'name' => $deduction->deduction_name_snapshot,
            'payroll_detail_id' => $detail->getKey(),
            'employee_number' => $detail->employee_number_snapshot,
            'employee_name' => $detail->employee_name_snapshot,
            'department' => $detail->department_snapshot,
            'amount' => (float) $deduction->amount,
        ]));
        $groups = $deductions->groupBy('deduction_type_id')->map(function (Collection $deductions): array {
            $employees = $deductions->groupBy('payroll_detail_id')->map(fn (Collection $rows): array => [
                ...$rows->first(), 'amount' => round((float) $rows->sum('amount'), 2),
            ])->sortBy('employee_name')->values();

            return [
                'name' => $deductions->first()['name'],
                'employees' => $employees,
                'total' => round((float) $employees->sum('amount'), 2),
            ];
        })->sortBy('name');
        $filters = $request->validate([
            'deduction_type_id' => ['nullable', 'integer', Rule::in($groups->keys()->all())],
        ]);
        $selectedType = $filters['deduction_type_id'] ?? null;

        return view('reports.deductions', [
            'run' => $payrollRun,
            'groups' => $groups,
            'selectedType' => $selectedType,
            'visibleGroups' => $selectedType === null ? $groups : $groups->only($selectedType),
        ]);
    }
}
