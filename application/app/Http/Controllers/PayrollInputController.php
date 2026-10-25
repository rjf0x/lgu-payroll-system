<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ScopePayrollInputErrors;
use App\Http\Requests\SavePayrollInputsRequest;
use App\Models\PayrollBatch;
use App\Models\PayrollEntry;
use App\Models\PayrollInputRevision;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ViewErrorBag;
use Illuminate\View\View;

class PayrollInputController extends Controller
{
    public function show(Request $request, PayrollBatch $batch, PayrollEntry $entry, PayrollInputService $service, PayrollPreparationService $preparation): View
    {
        Gate::authorize('view', $batch);
        $batch->entries()->whereKey($entry->id)->firstOrFail();
        $latest = $service->revisions($batch, $entry)->first();
        $revision = $latest;
        if ($request->query->has('revision')) {
            $number = $request->query('revision');
            abort_unless(is_string($number) && preg_match('/\A[1-9][0-9]{0,9}\z/', $number), 422, 'Select a valid input revision.');
            $revision = $service->revisions($batch, $entry)->where('revision', $number)->firstOrFail();
        }
        $context = $request->session()->get(ScopePayrollInputErrors::CONTEXT_KEY);
        $restoreInput = is_array($context)
            && ($context['batch_id'] ?? null) === (string) $batch->id
            && ($context['entry_id'] ?? null) === (string) $entry->id
            && ($context['employee_id'] ?? null) === (string) $entry->employee_id;

        return view('payroll-inputs.show', [
            'batch' => $batch, 'entry' => $entry, 'latest' => $latest, 'revision' => $revision,
            'historical' => $request->query->has('revision'), 'groups' => PayrollInputRevision::GROUPS,
            'contextChanged' => $revision ? $service->contextChanged($revision, $entry) : false,
            'sourceIssue' => $preparation->entryIssue($entry, $batch),
            'history' => $service->revisions($batch, $entry)->with('author')->paginate(10)->withQueryString(),
            'restoreInput' => $restoreInput,
            'errors' => $restoreInput ? $request->session()->get('errors', new ViewErrorBag) : new ViewErrorBag,
        ]);
    }

    public function update(SavePayrollInputsRequest $request, PayrollBatch $batch, PayrollEntry $entry, PayrollInputService $service): RedirectResponse
    {
        $service->save($request, $batch, $entry, $request->validated());

        return redirect()->route('payroll-inputs.show', [$batch, $entry])->with('status', 'Draft inputs saved as a new revision. Previous values are preserved.');
    }
}
