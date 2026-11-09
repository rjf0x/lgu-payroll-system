<?php

namespace App\Http\Controllers;

use App\Services\BesuService;
use Illuminate\Http\JsonResponse;

class BlockchainStatusController extends Controller
{
    public function show(BesuService $besu): JsonResponse
    {
        $status = $besu->checkConnection();

        return response()->json(
            $status,
            $status['connected'] ? 200 : 503
        );
    }
}
