<?php

namespace App\Http\Controllers;

use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Services\PayrollCalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PayrollCalculationController extends Controller
{
    public function index(PayrollBatch $batch): View
    {
        Gate::authorize('view', $batch);

        return view('payroll-calculations.index', ['batch' => $batch, 'runs' => PayrollCalculationRun::where('payroll_batch_id', $batch->id)->withCount('items')->orderByDesc('id')->paginate(10)]);
    }

    public function store(Request $request, PayrollBatch $batch, PayrollCalculationService $service): RedirectResponse
    {
        Gate::authorize('update', $batch);
        $data = $request->validate(['record_version' => ['required', 'integer', 'min:1']]);
        $run = $service->calculate($request, $batch, (int) $data['record_version']);

        return redirect()->route('payroll-calculations.show', [$batch, $run])->with('status', 'Demonstration core preview saved. Manual inputs are unchanged.');
    }

    public function show(PayrollBatch $batch, PayrollCalculationRun $run, PayrollCalculationService $service): View
    {
        Gate::authorize('view', $batch);
        abort_unless($run->payroll_batch_id === $batch->id, 404);
        $items = $run->items()->orderBy('employee_id')->paginate(20);
        $issues = $items->mapWithKeys(fn ($item) => [$item->id => $service->itemIssue($item, $batch)]);

        return view('payroll-calculations.show', compact('batch', 'run', 'items', 'issues'));
    }
}
