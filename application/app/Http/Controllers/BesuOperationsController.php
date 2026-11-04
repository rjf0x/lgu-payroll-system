<?php

namespace App\Http\Controllers;

use App\Models\LedgerVerification;
use App\Services\BlockchainMonitoringService;
use App\Services\LedgerIntegrityVerificationService;
use App\Services\PayrollAnchorQueryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class BesuOperationsController extends Controller
{
    private const HEADERS = [
        'Cache-Control' => 'private, no-store, max-age=0',
        'Pragma' => 'no-cache',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'no-referrer',
    ];

    public function index(Request $request, BlockchainMonitoringService $monitoring, PayrollAnchorQueryService $queries): Response
    {
        Gate::authorize('manage-blockchain');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', PayrollAnchorQueryService::ANCHOR_STATUSES)],
            'period' => ['nullable', 'regex:/\A\d{4}-(?:0[1-9]|1[0-2])\z/'],
            'submission_status' => ['nullable', 'in:'.implode(',', PayrollAnchorQueryService::SUBMISSION_STATUSES)],
        ]);

        $summary = $monitoring->summary();
        $anchors = $queries->anchors($filters, $this->page('page', 15), 15);
        $submissions = $queries->submissions($filters, $this->page('submissions_page', 15), 15);
        // When the retained ledger cannot be read (for example a pending migration),
        // the page still renders a degraded overview instead of an unhandled error.
        $verifications = $summary['available']
            ? LedgerVerification::orderByDesc('id')->paginate(15, ['*'], 'verifications_page', $this->page('verifications_page', 15))->withQueryString()
            : new LengthAwarePaginator([], 0, 15, $this->page('verifications_page', 15));
        $journal = DB::table('besu_anchor_outbox')
            ->select(['id', 'status', 'transaction_hash', 'block_number', 'block_hash', 'last_error', 'retry_count', 'next_attempt_at', 'updated_at'])
            ->orderByDesc('id')->paginate(20, ['*'], 'journal_page', $this->page('journal_page', 20))->withQueryString();
        $requestKey = (string) Str::uuid();

        return response(view('besu-operations.index', compact('summary', 'anchors', 'submissions', 'verifications', 'journal', 'filters', 'requestKey'))->render(), 200, self::HEADERS);
    }

    public function verify(Request $request, LedgerIntegrityVerificationService $service): RedirectResponse
    {
        Gate::authorize('manage-blockchain');
        $data = $request->validate(['request_key' => ['required', 'uuid']]);
        $verification = $service->verify($request->user(), strtolower($data['request_key']));
        $status = $verification->status === 'verified'
            ? 'Ledger integrity verification completed. '.$verification->records_checked.' record(s) checked; no inconsistencies were found.'
            : 'Ledger integrity verification completed. '.$verification->issues_found.' inconsistency(ies) were detected; review the retained report.';

        return redirect()->route('besu-operations.index')->with('status', $status);
    }

    public function showAnchor(int $commitment, PayrollAnchorQueryService $queries): Response
    {
        Gate::authorize('manage-blockchain');
        $detail = $queries->anchorDetail($commitment);
        abort_unless($detail, 404);

        return response(view('besu-operations.anchor', $detail)->render(), 200, self::HEADERS);
    }

    public function showVerification(LedgerVerification $verification): Response
    {
        Gate::authorize('manage-blockchain');

        return response(view('besu-operations.verification', compact('verification'))->render(), 200, self::HEADERS);
    }

    private function page(string $name, int $perPage): int
    {
        $page = Paginator::resolveCurrentPage($name);
        abort_if($page > intdiv(PHP_INT_MAX, $perPage) + 1, 404);

        return $page;
    }
}
