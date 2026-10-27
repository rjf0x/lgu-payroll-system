<?php

namespace App\Http\Controllers;

use App\Models\PayrollBatch;
use App\Models\PayrollCorrectionRequest;
use App\Models\PayrollFinalization;
use App\Services\PayrollCorrectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PayrollCorrectionController extends Controller
{
    public function index(PayrollBatch $batch, PayrollCorrectionService $service): Response
    {
        Gate::authorize('view', $batch);
        $source = PayrollFinalization::where('payroll_batch_id', $batch->id)->first();
        $head = $service->family($batch)->orderByDesc('payroll_version')->firstOrFail();
        $digest = null;
        $issue = null;
        if ($source && $batch->is_demonstration) {
            try {
                $digest = $service->digest($batch, $source);
            } catch (HttpException $error) {
                if ($error->getStatusCode() !== 409) {
                    throw $error;
                }
                $issue = $error->getMessage();
            }
        }
        foreach (['versions', 'requests'] as $name) {
            abort_if(Paginator::resolveCurrentPage($name) > intdiv(PHP_INT_MAX, 10) + 1, 404);
        }
        $versions = $service->family($batch)->orderBy('payroll_version')->paginate(10, ['*'], 'versions');
        $finalizations = PayrollFinalization::whereIn('payroll_batch_id', $versions->pluck('id'))->get()->keyBy('payroll_batch_id');
        $requests = PayrollCorrectionRequest::with('decision')->where('source_finalization_id', $source?->id ?? 0)
            ->orderByDesc('revision')->paginate(10, ['*'], 'requests');
        $reviewers = $requests->mapWithKeys(fn ($proposal) => [$proposal->id => $source
            && Gate::allows('reviewCorrection', $batch) && $service->independent(auth()->user(), $batch, $source, $proposal)]);
        $requestKey = (string) Str::uuid();

        return response(view('payroll-corrections.index', compact('batch', 'source', 'head', 'digest', 'issue', 'versions', 'finalizations', 'requests', 'reviewers', 'requestKey'))->render(), 200, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function store(Request $request, PayrollBatch $batch, PayrollFinalization $finalization, PayrollCorrectionService $service): RedirectResponse
    {
        $service->act($request, $batch, $finalization, 'request', $request->only(['request_key', 'source_hash', 'reason', 'authority_reference', 'review_confirmed']));

        return redirect()->route('payroll-corrections.index', $batch);
    }

    public function decide(Request $request, PayrollBatch $batch, PayrollFinalization $finalization, PayrollCorrectionRequest $correction, string $action, PayrollCorrectionService $service): RedirectResponse
    {
        $decision = $service->act($request, $batch, $finalization, $action, $request->only(['source_hash', 'reason', 'authority_reference', 'review_confirmed']), $correction);

        return $decision->correction_batch_id
            ? redirect()->route('payroll-batches.show', $decision->correction_batch_id)
            : redirect()->route('payroll-corrections.index', $batch);
    }
}
