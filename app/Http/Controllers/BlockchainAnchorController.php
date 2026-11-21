<?php

namespace App\Http\Controllers;

use App\Models\BlockchainAnchor;
use App\Models\PayrollRegistration;
use App\Models\PayrollRun;
use App\Services\BlockchainAnchorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BlockchainAnchorController extends Controller
{
    public function index(): View
    {
        $anchors = BlockchainAnchor::query()
            ->with(['payrollRun.payrollPeriod', 'anchoredBy'])
            ->orderByDesc('anchor_id')
            ->paginate(20)->withQueryString();

        $registrations = PayrollRegistration::query()
            ->select(['registration_id', 'payroll_run_id', 'organization_id', 'payroll_reference', 'revision', 'status', 'hash_schema_version', 'period_snapshot'])
            ->with(['payrollRun', 'latestVerification'])->orderByDesc('registration_id')
            ->paginate(20, ['*'], 'registrations_page')->withQueryString();

        return view('blockchain.index', compact('anchors', 'registrations'));
    }

    public function anchor(PayrollRun $payrollRun, BlockchainAnchorService $service): RedirectResponse
    {
        $anchor = $service->anchor($payrollRun, auth()->user());

        return redirect()
            ->route('blockchain-anchors.show', $anchor)
            ->with('status', match ($anchor->anchor_status) {
                BlockchainAnchor::STATUS_PENDING => 'Existing legacy transaction is pending. Check confirmation and verify; no new transaction was submitted.',
                BlockchainAnchor::STATUS_FAILED => 'The existing legacy transaction failed. Preserve its evidence and contact the administrator; legacy resubmission is disabled.',
                default => $anchor->contract_address === null && str_starts_with($anchor->network_name, 'simulated:')
                    ? 'Simulated local proof recorded. No blockchain transaction or independent blockchain evidence was created.'
                    : 'Existing legacy anchor retained. No new transaction was submitted.',
            });
    }

    public function show(BlockchainAnchor $blockchainAnchor): View
    {
        $blockchainAnchor->load(['payrollRun.payrollPeriod', 'anchoredBy', 'verificationLogs' => fn ($query) => $query->orderByDesc('verified_at')->orderByDesc('verification_id'), 'verificationLogs.verifiedBy']);

        return view('blockchain.show', ['anchor' => $blockchainAnchor]);
    }

    public function verify(BlockchainAnchor $blockchainAnchor, BlockchainAnchorService $service): RedirectResponse
    {
        $log = $service->verify($blockchainAnchor, auth()->user());

        return redirect()
            ->route('blockchain-anchors.show', $blockchainAnchor)
            ->with('status', 'Verification result: '.$log->verification_result.'.');
    }
}
