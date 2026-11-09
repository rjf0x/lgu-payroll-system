<?php

namespace App\Jobs;

use App\Services\PayrollRegistrationService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SubmitPayrollRegistration implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public int $uniqueFor = 120;

    public function __construct(public int $registrationId)
    {
        $this->onQueue('blockchain');
    }

    public function uniqueId(): string
    {
        return (string) $this->registrationId;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job.
     */
    public function handle(PayrollRegistrationService $registrations): void
    {
        $registrations->synchronize($this->registrationId);
    }
}
