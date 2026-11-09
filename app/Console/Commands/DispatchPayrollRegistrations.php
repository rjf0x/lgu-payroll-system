<?php

namespace App\Console\Commands;

use App\Jobs\SubmitPayrollRegistration;
use App\Models\PayrollRegistration;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('blockchain:dispatch-registrations {--limit=100 : Maximum due requests to dispatch}')]
#[Description('Dispatch durable payroll registry requests for submission or reconciliation')]
class DispatchPayrollRegistrations extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! config('blockchain.registry.enabled')) {
            $this->info('Payroll registry capture and dispatch are disabled.');

            return self::SUCCESS;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('The limit must be between 1 and 1000.');

            return self::FAILURE;
        }
        $ids = PayrollRegistration::query()->whereIn('status', PayrollRegistration::ACTIVE)
            ->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('registration_id')->limit($limit)->pluck('registration_id');
        foreach ($ids as $id) {
            SubmitPayrollRegistration::dispatch((int) $id)->afterCommit();
        }
        $this->info('Dispatched '.$ids->count().' payroll registration requests.');

        return self::SUCCESS;
    }
}
