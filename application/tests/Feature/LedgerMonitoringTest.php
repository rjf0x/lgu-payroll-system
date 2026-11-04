<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\LedgerVerification;
use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollCommitment;
use App\Models\User;
use App\Services\BlockchainMonitoringService;
use App\Services\LedgerIntegrityVerificationService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\FinalizedPayrollFixture;
use Tests\TestCase;

class LedgerMonitoringTest extends TestCase
{
    use FinalizedPayrollFixture;

    private User $officer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->withoutVite();
        Http::preventStrayRequests();
        $record = json_decode(file_get_contents(base_path('../blockchain/contracts/deployments/local.json')), true);
        config(['besu.enabled' => true, 'besu.automatic_anchoring' => true, 'besu.chain_id' => 20261030,
            'besu.genesis_hash' => $record['genesisHash'], 'besu.contract_address' => $record['address'],
            'besu.writer' => $record['writer'], 'besu.administrator' => $record['administrator'], 'besu.runtime_code_hash' => $record['runtimeCodeHash']]);
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
    }

    private function actingAdmin(): User
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);

        return $admin;
    }

    private function verifyLedger(int $status = 302)
    {
        return $this->post(route('besu-operations.verify'), ['request_key' => (string) Str::uuid()])->assertStatus($status);
    }

    private function summary(): array
    {
        return app(BlockchainMonitoringService::class)->summary();
    }

    private function freshSummary(): array
    {
        app(BlockchainMonitoringService::class)->forget();

        return $this->summary();
    }

    public function test_empty_ledger_is_operational_and_becomes_verified(): void
    {
        $this->actingAdmin();
        $summary = $this->freshSummary();
        $this->assertTrue($summary['available']);
        $this->assertSame('ledger_operational', $summary['state']);
        $this->assertSame(0, $summary['ledger']['records']);
        $this->get('/dashboard')->assertOk()->assertSee('Ledger Operational');

        $this->verifyLedger()->assertRedirect(route('besu-operations.index'));
        $verification = LedgerVerification::sole();
        $this->assertSame('verified', $verification->status);
        $this->assertSame(0, $verification->records_checked);
        $this->assertSame(0, $verification->issues_found);
        $this->assertSame('integrity_verified', $this->summary()['state']);
        $this->get('/dashboard')->assertOk()->assertSee('Integrity Verified');
    }

    public function test_successful_verification_persists_history_and_updates_navbar(): void
    {
        $this->fixture();
        $this->actingAdmin();

        $this->assertSame('verification_required', $this->freshSummary()['state']);
        $this->get('/dashboard')->assertOk()->assertSee('Verification Required');

        $this->verifyLedger();
        $first = LedgerVerification::sole();
        $this->assertSame('verified', $first->status);
        $this->assertSame(app(LedgerIntegrityVerificationService::class)->ledgerRecordCount(), $first->records_checked);
        $this->assertGreaterThanOrEqual(1, $first->records_checked);
        $this->assertIsArray($first->report['issues']);

        // The verification service invalidates the cached summary on success.
        $this->assertSame('integrity_verified', $this->summary()['state']);
        $this->get('/dashboard')->assertOk()->assertSee('Integrity Verified');

        $this->verifyLedger();
        $this->assertSame(2, LedgerVerification::count());
        $this->assertSame('verified', LedgerVerification::orderBy('id')->first()->status);
        $this->get(route('besu-operations.verifications.show', LedgerVerification::latest('id')->first()))->assertOk()
            ->assertSee('Records checked')->assertSee('No inconsistencies were recorded');
    }

    public function test_modified_audit_record_is_detected_and_never_repaired(): void
    {
        [$batch, $final] = $this->fixture();
        $this->actingAdmin();
        $snapshot = $final->snapshot;
        $snapshot['payroll']['employees'][0]['core_breakdown']['totals']['basic_pay'] = '99999.99';
        $before = DB::table('payroll_finalizations')->where('id', $final->id)->value('snapshot');
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $tampered = DB::table('payroll_finalizations')->where('id', $final->id)->value('snapshot');

        $this->verifyLedger();
        $verification = LedgerVerification::sole();
        $this->assertSame('issues_found', $verification->status);
        $this->assertArrayHasKey('modified_source_snapshot', $verification->report['counts']);
        $this->assertSame('integrity_issue', $this->freshSummary()['state']);
        $this->get('/dashboard')->assertOk()->assertSee('Integrity Issue');
        $this->get(route('besu-operations.verifications.show', $verification))->assertOk()->assertSee('Modified audit record');
        // The integrity check is read-only: the tampered record is reported, never silently fixed.
        $this->assertSame($tampered, DB::table('payroll_finalizations')->where('id', $final->id)->value('snapshot'));
        $this->assertNotSame($before, $tampered);
    }

    public function test_invalid_record_hash_is_detected(): void
    {
        $this->fixture();
        $this->actingAdmin();
        DB::table('payroll_snapshot_archives')->update(['sha256' => str_repeat('0', 64)]);

        $this->verifyLedger();
        $verification = LedgerVerification::sole();
        $this->assertSame('issues_found', $verification->status);
        $this->assertArrayHasKey('invalid_record_hash', $verification->report['counts']);
        $this->get(route('besu-operations.verifications.show', $verification))->assertOk()->assertSee('Invalid record hash');
    }

    public function test_broken_chain_reference_is_detected(): void
    {
        $this->fixture();
        $this->actingAdmin();
        DB::table('payroll_commitments')->update(['sha256' => str_repeat('1', 64)]);

        $this->verifyLedger();
        $verification = LedgerVerification::sole();
        $this->assertSame('issues_found', $verification->status);
        $this->assertArrayHasKey('broken_reference', $verification->report['counts']);
        $this->get(route('besu-operations.verifications.show', $verification))->assertOk()->assertSee('Broken chain reference');
    }

    public function test_unavailable_ledger_degrades_navbar_without_breaking_pages(): void
    {
        $this->actingAdmin();
        Schema::drop('ledger_verifications');

        $summary = $this->freshSummary();
        $this->assertFalse($summary['available']);
        $this->assertSame('service_unavailable', $summary['state']);
        $this->get('/dashboard')->assertOk()->assertSee('Service Unavailable');
        // The monitoring page itself degrades instead of returning an unhandled error.
        $this->get(route('besu-operations.index'))->assertOk()
            ->assertSee('The retained ledger could not be read')
            ->assertSee('No ledger verification has been recorded.');
    }

    public function test_anchor_listing_supports_search_filter_detail_and_permissions(): void
    {
        [$batch, $final] = $this->fixture();
        $this->fixture(period: '2026-11');
        $this->actingAdmin();
        $commitment = PayrollCommitment::where('payroll_finalization_id', $final->id)->sole();

        $page = $this->get(route('besu-operations.index'))->assertOk()
            ->assertSee('Blockchain operations')->assertSee('Payroll hash anchoring')->assertSee($commitment->sha256)
            ->assertDontSee('FICTIONAL LOAN')->assertDontSee('core_breakdown');
        $page->assertSee('2026-11-01');

        $this->get(route('besu-operations.index', ['period' => '2026-10']))->assertOk()
            ->assertSee('2026-10-01')->assertDontSee('2026-11-01');
        $this->get(route('besu-operations.index', ['q' => $commitment->sha256]))->assertOk()->assertSee($commitment->sha256);
        $this->get(route('besu-operations.index', ['status' => 'queued']))->assertOk()->assertSee('Queued');
        $this->get(route('besu-operations.index', ['status' => 'not_queued']))->assertOk();

        $this->get(route('besu-operations.anchors.show', $commitment->id))->assertOk()
            ->assertSee('Payroll hash anchor')->assertSee($commitment->sha256)->assertSee('Finalization #'.$final->id)
            ->assertDontSee('FICTIONAL LOAN');

        foreach ([Role::PayrollOfficer, Role::Approver, Role::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
            $this->get(route('besu-operations.index'))->assertForbidden();
            $this->get(route('besu-operations.anchors.show', $commitment->id))->assertForbidden();
            $this->verifyLedger(403);
        }
        $this->assertSame(0, LedgerVerification::count());
    }

    public function test_signed_payloads_and_foreign_payroll_attempts_are_not_exposed(): void
    {
        [$batch, $final] = $this->fixture();
        $this->actingAdmin();
        $commitment = PayrollCommitment::sole();
        DB::table('besu_anchor_outbox')->update(['encrypted_transaction' => 'SENSITIVE SIGNED PAYLOAD FIXTURE', 'last_error' => 'rpc_unavailable']);
        PayrollAnchorAttempt::forceCreate([
            'payroll_commitment_id' => $commitment->id, 'status' => 'failed', 'network' => 'TEST DOUBLE NETWORK',
            'transaction_reference' => 'fictional-transaction-reference', 'created_by' => $this->approver->id, 'completed_at' => now(),
        ]);

        $this->get(route('besu-operations.index'))->assertOk()
            ->assertSee('rpc unavailable')->assertSee('Rejected')
            ->assertDontSee('SENSITIVE SIGNED PAYLOAD FIXTURE');
        $this->get(route('besu-operations.anchors.show', $commitment->id))->assertOk()
            ->assertDontSee('SENSITIVE SIGNED PAYLOAD FIXTURE');
    }

    public function test_submission_history_reports_unavailable_and_unknown_states(): void
    {
        $this->fixture();
        $this->actingAdmin();
        $commitment = PayrollCommitment::sole();
        PayrollAnchorAttempt::forceCreate([
            'payroll_commitment_id' => $commitment->id, 'status' => 'unavailable', 'created_by' => $this->approver->id, 'completed_at' => now(),
        ]);
        PayrollAnchorAttempt::forceCreate([
            'payroll_commitment_id' => $commitment->id, 'status' => 'unknown', 'created_by' => $this->approver->id, 'completed_at' => now(),
        ]);

        $this->get(route('besu-operations.index', ['submission_status' => 'unavailable']))->assertOk()
            ->assertSee('No authorized submission interface was available')
            ->assertDontSee('Acceptance is uncertain or interrupted');
    }

    public function test_verification_requires_csrf_and_guest_is_redirected(): void
    {
        $this->actingAdmin();
        $this->app->detectEnvironment(fn () => 'production');
        $this->withMiddleware(PreventRequestForgery::class);
        $this->post(route('besu-operations.verify'), ['request_key' => (string) Str::uuid()])->assertStatus(419);
        $this->assertSame(0, LedgerVerification::count());

        auth()->logout();
        $this->get(route('besu-operations.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_administrator_session_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator, 'is_active' => false]);
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $this->get(route('besu-operations.index'))->assertRedirect(route('login'));
        $this->assertSame(0, LedgerVerification::count());
    }

    public function test_monitoring_summary_survives_a_database_cache_round_trip(): void
    {
        $this->fixture();
        $this->actingAdmin();
        $this->verifyLedger();

        // Reproduce the production database cache path (the array store never serializes).
        config(['cache.default' => 'database']);
        Cache::flush();
        $first = app(BlockchainMonitoringService::class)->summary();
        $second = app(BlockchainMonitoringService::class)->summary();

        $this->assertSame($first, $second);
        $this->assertIsString($second['verification']['created_at']);
        $this->assertIsString($second['verification']['last_verified_at']);
        $this->assertSame(1, $second['verification']['id']);
        $this->assertNoObjects($second);
    }

    public function test_cache_is_invalidated_after_a_new_verification(): void
    {
        $this->fixture();
        $this->actingAdmin();
        $this->assertSame('verification_required', $this->freshSummary()['state']);
        // Second read is served from cache and must agree.
        $this->assertSame('verification_required', $this->summary()['state']);
        $this->verifyLedger();
        $this->assertSame('integrity_verified', $this->summary()['state']);
    }

    public function test_ledger_verification_history_is_immutable(): void
    {
        $this->actingAdmin();
        $this->verifyLedger();
        $record = LedgerVerification::sole();
        $this->expectException(\LogicException::class);
        $record->forceFill(['status' => 'issues_found'])->save();
    }

    private function assertNoObjects(mixed $value): void
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->assertNoObjects($item);
            }

            return;
        }
        $this->assertFalse(is_object($value), 'Cached monitoring summaries must contain only scalars.');
    }
}
