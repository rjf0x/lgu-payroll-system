<?php

namespace App\Jobs;

use App\Models\PayrollRun;
use App\Models\User;
use App\Services\PayslipGenerationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeneratePayslips implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 3600;

    public function __construct(public int $payrollRunId, public int $requestedBy) {}

    public function uniqueId(): string
    {
        return (string) $this->payrollRunId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function handle(PayslipGenerationService $generation): void
    {
        $user = User::query()->findOrFail($this->requestedBy);
        abort_unless($user->isActive() && $user->can('reports.generate'), 403, 'Report generation access has been revoked.');
        $generation->generate(PayrollRun::query()->findOrFail($this->payrollRunId), $this->requestedBy);
    }
}
