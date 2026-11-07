<?php

namespace App\Http\Controllers;

use App\Models\BlockchainAnchor;
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
            ->paginate(20);

        return view('blockchain.index', compact('anchors'));
    }

    public function anchor(PayrollRun $payrollRun, BlockchainAnchorService $service): RedirectResponse
    {
        $anchor = $service->anchor($payrollRun, auth()->user());

        return redirect()
            ->route('blockchain-anchors.show', $anchor)
            ->with('status', 'Finalized payroll anchored. Only a hash and transaction metadata were submitted.');
    }

    public function show(BlockchainAnchor $blockchainAnchor): View
    {
        $blockchainAnchor->load(['payrollRun.payrollPeriod', 'anchoredBy', 'verificationLogs.verifiedBy']);

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
