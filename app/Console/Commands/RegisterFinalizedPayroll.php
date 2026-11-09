<?php

namespace App\Console\Commands;

use App\Services\PayrollRegistrationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('blockchain:register-finalized {payroll : Finalized payroll run ID}')]
#[Description('Capture the current baseline of an existing finalized payroll for asynchronous blockchain registration')]
class RegisterFinalizedPayroll extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PayrollRegistrationService $registrations): int
    {
        $id = filter_var($this->argument('payroll'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            $this->error('A positive payroll run ID is required.');

            return self::FAILURE;
        }
        $registration = $registrations->registerExisting($id);
        $this->info('Registration '.$registration->getKey().': '.$registration->status);
        $this->line('This baseline proves integrity from registration, not from the original finalization date.');

        return self::SUCCESS;
    }
}
