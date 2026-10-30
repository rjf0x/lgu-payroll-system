<?php

namespace App\Http\Controllers;

use App\Models\BesuAnchor;
use Illuminate\Http\Response;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class BesuOperationsController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-blockchain');
        abort_if(Paginator::resolveCurrentPage() > intdiv(PHP_INT_MAX, 20) + 1, 404);
        $anchors = BesuAnchor::select(['id', 'status', 'transaction_hash', 'block_number', 'block_hash', 'last_error', 'retry_count', 'next_attempt_at', 'updated_at'])
            ->orderByDesc('id')->paginate(20);
        $counts = DB::table('besu_anchor_outbox')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return response(view('besu-operations.index', compact('anchors', 'counts'))->render())->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
