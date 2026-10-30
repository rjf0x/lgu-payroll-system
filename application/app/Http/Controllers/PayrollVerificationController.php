<?php

namespace App\Http\Controllers;

use App\Models\PayrollBatch;
use App\Models\PayrollFinalization;
use App\Models\PayrollVerification;
use App\Services\Blockchain\BlockchainReader;
use App\Services\Blockchain\UnavailableBlockchainReader;
use App\Services\PayrollVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PayrollVerificationController extends Controller
{
    public function show(PayrollBatch $batch, PayrollFinalization $finalization, PayrollVerificationService $service): Response
    {
        Gate::authorize('view', $batch);
        $local = $service->inspect($batch, $finalization);
        $page = Paginator::resolveCurrentPage();
        abort_if($page > intdiv(PHP_INT_MAX, 20) + 1, 404);
        $checks = PayrollVerification::where('payroll_finalization_id', $finalization->id)->orderByDesc('id')->paginate(20, ['*'], 'page', $page);
        $latest = PayrollVerification::where('payroll_finalization_id', $finalization->id)->orderByDesc('id')->first();
        $stale = $service->isStale($latest, $local);
        $displayStatus = $latest && ! $stale ? $latest->status : $local['status'];
        $readerConfigured = $local['besu_anchor_id'] ? (bool) config('besu.enabled') : ! app(BlockchainReader::class) instanceof UnavailableBlockchainReader;
        $requestKey = (string) Str::uuid();

        return response(view('payroll-verification.show', compact('batch', 'finalization', 'local', 'checks', 'latest', 'stale', 'displayStatus', 'readerConfigured', 'requestKey'))->render(), 200, [
            'Cache-Control' => 'private, no-store, max-age=0', 'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function store(Request $request, PayrollBatch $batch, PayrollFinalization $finalization, PayrollVerificationService $service): RedirectResponse
    {
        $service->check($request, $batch, $finalization, $request->only('request_key'));

        return redirect()->route('payroll-verification.show', [$batch, $finalization]);
    }
}
