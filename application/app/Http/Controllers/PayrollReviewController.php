<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayrollWorkflowRequest;
use App\Models\PayrollBatch;
use App\Models\PayrollFinalization;
use App\Models\PayrollSubmission;
use App\Models\PayrollVerification;
use App\Models\PayrollWorkflowEvent;
use App\Services\PayrollVerificationService;
use App\Services\PayrollWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PayrollReviewController extends Controller
{
    public function show(PayrollBatch $batch, PayrollWorkflowService $service): View
    {
        Gate::authorize('view', $batch);
        $submission = $service->latestSubmission($batch);
        $independentReviewer = $submission
            && (Gate::allows('review', $batch) || Gate::allows('finalize', $batch))
            && $service->isIndependent(auth()->user(), $batch, $submission);
        $finalization = PayrollFinalization::where('payroll_batch_id', $batch->id)->first();
        $integrity = null;
        if ($finalization && $batch->status === 'finalized' && $batch->is_demonstration) {
            $verification = app(PayrollVerificationService::class);
            $local = $verification->inspect($batch, $finalization);
            $latest = PayrollVerification::where('payroll_finalization_id', $finalization->id)->orderByDesc('id')->first();
            $integrity = ['local' => $local, 'latest' => $latest, 'stale' => $verification->isStale($latest, $local)];
        }
        $snapshot = $finalization?->snapshot['payroll'] ?? $submission?->snapshot;
        $token = null;
        $issue = null;
        if (in_array($batch->status, ['draft', 'returned'], true)) {
            $snapshot = null;
            try {
                $snapshot = $service->currentSnapshot($batch);
                $token = $service->reviewToken($snapshot);
            } catch (ValidationException $exception) {
                $issue = collect($exception->errors())->flatten()->first();
            }
        }
        $events = PayrollWorkflowEvent::where('payroll_batch_id', $batch->id)->orderByDesc('id')->paginate(10, ['*'], 'events');
        $submissions = PayrollSubmission::where('payroll_batch_id', $batch->id)->orderByDesc('revision')->paginate(5, ['*'], 'submissions');

        return view('payroll-reviews.show', compact('batch', 'submission', 'independentReviewer', 'finalization', 'snapshot', 'token', 'issue', 'events', 'submissions', 'integrity'));
    }

    public function transition(PayrollWorkflowRequest $request, PayrollBatch $batch, string $action, PayrollWorkflowService $service): RedirectResponse
    {
        $service->transition($request, $batch, $action, $request->validated());

        return redirect()->route('payroll-reviews.show', $batch)->with('status', 'Demonstration payroll workflow saved. Review the status and retained history below.');
    }
}
