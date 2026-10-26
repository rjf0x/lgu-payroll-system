<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewStatutoryRequest;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationItem;
use App\Models\PayrollCalculationRun;
use App\Models\StatutoryPreview;
use App\Services\PayrollCalculationService;
use App\Services\PayrollRuleVersionService;
use App\Services\StatutoryPreviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StatutoryPreviewController extends Controller
{
    private function scope(PayrollBatch $batch, PayrollCalculationRun $run, PayrollCalculationItem $item): void
    {
        Gate::authorize('view', $batch);
        abort_unless($run->payroll_batch_id === $batch->id && $item->payroll_calculation_run_id === $run->id, 404);
    }

    public function show(PayrollBatch $batch, PayrollCalculationRun $run, PayrollCalculationItem $item, PayrollCalculationService $service): View
    {
        $this->scope($batch, $run, $item);
        $history = StatutoryPreview::where('payroll_calculation_item_id', $item->id)->orderByDesc('revision')->paginate(5);
        $latest = StatutoryPreview::where('payroll_calculation_item_id', $item->id)->orderByDesc('revision')->first();
        $issue = $service->itemIssue($item, $batch);
        $profile = app(PayrollRuleVersionService::class)->profile($item->entry_snapshot['personnel_classification'], $batch->period_start->format('Y-m-d'), $batch->is_demonstration);

        return view('statutory-previews.show', compact('batch', 'run', 'item', 'history', 'latest', 'issue', 'profile'));
    }

    public function store(PreviewStatutoryRequest $request, PayrollBatch $batch, PayrollCalculationRun $run, PayrollCalculationItem $item, StatutoryPreviewService $service): RedirectResponse
    {
        $this->scope($batch, $run, $item);
        $preview = $service->save($request, $batch, $run, $item, $request->validated());

        return redirect()->route('statutory-previews.show', [$batch, $run, $item])->withFragment('review-'.$preview->id)
            ->with('status', 'Reference scenario saved. Real payroll remains blocked; core inputs and previews are unchanged.');
    }
}
