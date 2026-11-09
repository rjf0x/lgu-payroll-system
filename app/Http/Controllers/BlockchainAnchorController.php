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
                BlockchainAnchor::STATUS_PENDING => 'Transaction submitted. Use Check confirmation and verify to confirm it on Besu.',
                BlockchainAnchor::STATUS_FAILED => 'The existing transaction failed. Contact the administrator to reconcile it before resubmitting.',
                default => 'Finalized payroll anchored. Only a hash and transaction metadata were submitted.',
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
