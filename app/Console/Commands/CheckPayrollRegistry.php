<?php

namespace App\Console\Commands;

use App\Models\PayrollRegistration;
use App\Models\PayrollRegistrationVerification;
use App\Services\Blockchain\PayrollRegistryClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('blockchain:readiness {--json : Emit a machine-readable report} {--production : Require named operational owners}')]
#[Description('Read-only registry readiness and backlog checks; does not dispatch or sign transactions')]
class CheckPayrollRegistry extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PayrollRegistryClient $client): int
    {
        $checks = ['capture_enabled' => config('blockchain.registry.enabled') === true,
            'asynchronous_queue' => in_array(config('queue.default'), ['database', 'redis', 'sqs', 'beanstalkd'], true),
            'organization_namespace' => preg_match('/^[a-z0-9][a-z0-9-]{0,62}$/D', (string) config('blockchain.registry.organization_id')) === 1];
        $metrics = [];
        try {
            $migrator = app('migrator');
            $checks['database_schema'] = $migrator->repositoryExists()
                && array_diff(array_keys($migrator->getMigrationFiles(database_path('migrations'))), $migrator->getRepository()->getRan()) === [];
            if ($checks['database_schema']) {
                $active = PayrollRegistration::query()->whereIn('status', PayrollRegistration::ACTIVE);
                $oldest = (clone $active)->min('created_at');
                $metrics['pending'] = $active->count();
                $metrics['oldest_pending_seconds'] = $oldest === null ? 0 : max(0, now()->timestamp - CarbonImmutable::parse($oldest)->timestamp);
                $metrics['overdue_leases'] = PayrollRegistration::query()->whereNotNull('lease_token')->where('next_attempt_at', '<=', now())->count();
                $metrics['failed_or_conflicting'] = PayrollRegistration::query()->whereIn('status', [PayrollRegistration::FAILED, PayrollRegistration::CONFLICT])->count();
                $metrics['unresolved_integrity_findings'] = PayrollRegistration::query()->whereHas('latestIntegrityAssessment',
                    fn (Builder $query) => $query->whereIn('verification_result', [PayrollRegistrationVerification::MISMATCH,
                        PayrollRegistrationVerification::INVALID_EVIDENCE, PayrollRegistrationVerification::NOT_REGISTERED]))->count();
                $metrics['failed_blockchain_jobs'] = DB::table('failed_jobs')->where('queue', 'blockchain')->count();
                $threshold = (int) config('blockchain.registry.operations.stale_after_seconds', 600);
                $checks['backlog_age'] = $threshold >= 60 && $metrics['oldest_pending_seconds'] <= $threshold;
                $checks['no_overdue_leases'] = $metrics['overdue_leases'] === 0;
                $checks['no_failed_requests'] = $metrics['failed_or_conflicting'] === 0;
                $checks['no_unresolved_findings'] = $metrics['unresolved_integrity_findings'] === 0;
                $checks['no_failed_blockchain_jobs'] = $metrics['failed_blockchain_jobs'] === 0;
            }
        } catch (Throwable) {
            $checks['database_schema'] = false;
        }
        try {
            $health = $client->health();
            $checks['bridge_network_and_signer'] = true;
            $checks['registrar_permission'] = $client->registrarAuthorized();
            $node = $health['node'] ?? [];
            $checks['node_synchronized'] = ($node['syncing'] ?? null) === false;
            $timestamp = $node['block_timestamp'] ?? null;
            $metrics['block_age_seconds'] = is_int($timestamp) ? max(0, now()->timestamp - $timestamp) : null;
            $maxAge = (int) config('blockchain.registry.operations.max_block_age_seconds', 60);
            $checks['recent_block'] = is_int($timestamp) && $maxAge >= 10 && abs(now()->timestamp - $timestamp) <= $maxAge;
        } catch (Throwable) {
            $checks['bridge_network_and_signer'] = false;
            $checks['registrar_permission'] = false;
            $checks['node_synchronized'] = false;
            $checks['recent_block'] = false;
        }
        if ($this->option('production') || app()->isProduction()) {
            foreach (['validator_owner', 'signer_owner', 'payroll_owner'] as $owner) {
                $checks[$owner] = filled(config('blockchain.registry.operations.'.$owner));
            }
        }
        $ready = ! in_array(false, $checks, true);
        if ($this->option('json')) {
            $this->line(json_encode(['ready' => $ready, 'checks' => $checks, 'metrics' => $metrics], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $passed) {
                $this->line(($passed ? 'PASS ' : 'FAIL ').$name);
            }
            $this->line('Metrics: '.json_encode($metrics, JSON_THROW_ON_ERROR));
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
