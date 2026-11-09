<?php

namespace Tests\Feature;

use App\Models\PayrollRegistration;
use App\Models\PayrollRegistrationVerification;
use App\Models\PayrollRun;
use App\Services\Blockchain\PayrollRegistryClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PayrollRegistryReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['queue.default' => 'database', 'blockchain.registry' => [
            'enabled' => true, 'organization_id' => 'lgu-pilot', 'bridge_url' => 'http://bridge.test', 'bridge_token' => str_repeat('t', 40),
            'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64), 'contract_address' => '0x'.str_repeat('b', 40),
            'code_hash' => '0x'.str_repeat('c', 64), 'signer_address' => '0x'.str_repeat('d', 40), 'deployment_block' => '10',
        ]]);
        Http::preventStrayRequests();
    }

    public function test_readiness_is_read_only_and_reports_a_healthy_empty_backlog(): void
    {
        $this->fakeBridge();

        $code = Artisan::call('blockchain:readiness', ['--json' => true]);
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(0, $code);
        $this->assertTrue($report['ready']);
        $this->assertSame(0, $report['metrics']['pending']);
        $this->assertDatabaseCount('jobs', 0);
        $this->assertDatabaseCount('payroll_registrations', 0);
        Http::assertSentCount(2);
    }

    public function test_stale_backlog_and_expired_leases_are_visible_without_releasing_or_dispatching_them(): void
    {
        $this->fakeBridge();
        $registration = $this->registration();
        $registration->forceFill(['created_at' => now()->subMinutes(20), 'lease_token' => (string) Str::uuid(), 'next_attempt_at' => now()->subMinute()])->save();

        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertFalse($report['checks']['backlog_age']);
        $this->assertFalse($report['checks']['no_overdue_leases']);
        $this->assertSame(1200, $report['metrics']['oldest_pending_seconds']);
        $this->assertSame(1, $report['metrics']['overdue_leases']);
        $this->assertNotNull($registration->fresh()->lease_token);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_only_the_latest_integrity_finding_blocks_readiness(): void
    {
        $this->fakeBridge();
        $registration = $this->registration();
        $attributes = ['registration_id' => $registration->getKey(), 'captured_hash' => $registration->payload_hash,
            'remarks' => 'Synthetic finding', 'verified_at' => now()];
        PayrollRegistrationVerification::create([...$attributes, 'verification_result' => 'MISMATCH']);
        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        $report = json_decode(Artisan::output(), true);
        $this->assertSame(1, $report['metrics']['unresolved_integrity_findings']);
        PayrollRegistrationVerification::create([...$attributes, 'verification_result' => 'UNAVAILABLE']);
        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        PayrollRegistrationVerification::create([...$attributes, 'verification_result' => 'MATCH']);

        $this->assertSame(0, Artisan::call('blockchain:readiness', ['--json' => true]));
    }

    public function test_failed_requests_and_missing_registrar_permission_fail_readiness(): void
    {
        $this->fakeBridge(false);
        $registration = $this->registration();
        $registration->update(['status' => 'FAILED']);

        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        $report = json_decode(Artisan::output(), true);

        $this->assertFalse($report['checks']['registrar_permission']);
        $this->assertFalse($report['checks']['no_failed_requests']);
    }

    public function test_production_readiness_requires_named_owners_and_never_exposes_credentials(): void
    {
        $this->fakeBridge();
        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--production' => true, '--json' => true]));
        $this->assertStringNotContainsString(str_repeat('t', 40), Artisan::output());
        config(['blockchain.registry.operations' => ['validator_owner' => 'Infrastructure', 'signer_owner' => 'Signing administration', 'payroll_owner' => 'Payroll approver']]);

        $this->assertSame(0, Artisan::call('blockchain:readiness', ['--production' => true, '--json' => true]));
    }

    public function test_unavailable_bridge_reports_failure_without_leaking_exception_details(): void
    {
        Http::fake(['http://bridge.test/health' => Http::response(['error' => 'PRIVATE_SENSITIVE_ERROR'], 503)]);

        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        $this->assertStringNotContainsString('PRIVATE_SENSITIVE_ERROR', Artisan::output());
    }

    public function test_deployment_preflight_accepts_versioned_configuration_without_requiring_a_legacy_driver(): void
    {
        config(['blockchain.driver' => 'simulated']);

        Artisan::call('deployment:check', ['--production' => true]);

        $this->assertStringContainsString('PASS Payroll integrity transport configured', Artisan::output());
        Http::assertNothingSent();
    }

    public function test_syncing_nodes_fail_readiness(): void
    {
        $this->fakeBridge(true, true);
        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertFalse($report['checks']['node_synchronized']);
        $this->assertTrue($report['checks']['recent_block']);
    }

    public function test_stale_nodes_fail_readiness(): void
    {
        $this->fakeBridge(true, false, now()->subMinutes(2)->timestamp);
        $this->assertSame(1, Artisan::call('blockchain:readiness', ['--json' => true]));
        $report = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($report['checks']['node_synchronized']);
        $this->assertFalse($report['checks']['recent_block']);
    }

    private function fakeBridge(bool $authorized = true, bool $syncing = false, ?int $timestamp = null): void
    {
        $network = app(PayrollRegistryClient::class)->target();
        unset($network['bridge_url']);
        Http::fake(['http://bridge.test/health' => Http::response(['status' => 'READY', 'network' => $network,
            'node' => ['syncing' => $syncing, 'block_number' => 20, 'block_timestamp' => $timestamp ?? now()->timestamp]]),
            'http://bridge.test/registrars/lgu-pilot' => Http::response(['authorized' => $authorized, 'network' => $network])]);
    }

    private function registration(): PayrollRegistration
    {
        $run = PayrollRun::factory()->create(['status' => 'FINALIZED']);

        return PayrollRegistration::create(['payroll_run_id' => $run->getKey(), 'organization_id' => 'lgu-pilot', 'payroll_reference' => (string) Str::uuid(),
            'revision' => 1, 'hash_schema_version' => 1, 'payload_hash' => str_repeat('e', 64), 'idempotency_key' => hash('sha256', (string) Str::uuid()),
            'canonical_payload' => '{}', 'period_snapshot' => [], 'approval_snapshot' => [], 'target_network' => app(PayrollRegistryClient::class)->target(), 'status' => 'QUEUED']);
    }
}
