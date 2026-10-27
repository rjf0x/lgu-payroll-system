<?php

namespace App\Http\Controllers;

use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollSubmission;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;

class PayrollAnchoringController extends Controller
{
    public function show(PayrollBatch $batch, PayrollFinalization $finalization, PayrollAnchoringService $service): Response
    {
        Gate::authorize('view', $batch);
        $digest = $service->digest($batch, $finalization);
        $commitment = PayrollCommitment::where('payroll_finalization_id', $finalization->id)->first();
        $page = Paginator::resolveCurrentPage();
        // Reject unrepresentable offsets before the query builder multiplies them.
        abort_if($page > intdiv(PHP_INT_MAX, 20) + 1, 404);
        $attempts = PayrollAnchorAttempt::where('payroll_commitment_id', $commitment?->id ?? 0)->orderByDesc('id')->paginate(20, ['*'], 'page', $page);
        $latest = $commitment ? PayrollAnchorAttempt::where('payroll_commitment_id', $commitment->id)->orderByDesc('id')->first() : null;
        $matches = ! $commitment || ($commitment->canonicalization === CanonicalPayrollSnapshot::VERSION
            && $commitment->algorithm === 'sha256' && hash_equals($commitment->sha256, $digest));
        $canAct = $matches && auth()->user()->can('anchor', $batch)
            && app(PayrollWorkflowService::class)->isIndependent(auth()->user(), $batch, PayrollSubmission::findOrFail($finalization->payroll_submission_id));

        return response(view('payroll-anchoring.show', compact('batch', 'finalization', 'digest', 'commitment', 'attempts', 'latest', 'matches', 'canAct'))->render(), 200, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function act(Request $request, PayrollBatch $batch, PayrollFinalization $finalization, string $action, PayrollAnchoringService $service): RedirectResponse
    {
        $service->act($request, $batch, $finalization, $action, $request->only(['sha256', 'demonstration_confirmed']));

        return redirect()->route('payroll-anchoring.show', [$batch, $finalization]);
    }
}
