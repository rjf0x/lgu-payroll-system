<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BlockchainAnchor;
use App\Models\PayrollApproval;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\BlockchainAnchorService;
use App\Services\PayrollRegistrationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class LegacyAnchorBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = Role::PAYROLL_OFFICER): User
    {
        $this->freezeTime();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    private function finalizedRun(): PayrollRun
    {
        $run = PayrollRun::factory()->create(['status' => 'FINALIZED', 'submitted_by' => auth()->id(), 'submitted_at' => now()->subMinutes(2), 'finalized_by' => auth()->id(), 'finalized_at' => now()]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);

        return $run;
    }

    /** @param array<string, mixed> $attributes */
    private function historicalAnchor(PayrollRun $run, array $attributes = []): BlockchainAnchor
    {
        return BlockchainAnchor::create([
            'payroll_run_id' => $run->getKey(), 'payload_hash' => app(BlockchainAnchorService::class)->hashPayrollRun($run),
            'hash_algorithm' => 'SHA-256', 'network_name' => 'besu:1337',
            'transaction_id' => '0x'.str_repeat('a', 64), 'contract_address' => '0x'.str_repeat('b', 40),
            'anchor_status' => 'ANCHORED', 'anchored_by' => auth()->id(), 'anchored_at' => now(), ...$attributes,
        ]);
    }

    /** @return array<string, array{string, bool}> */
    public static function unsupportedSettings(): array
    {
        return ['besu' => ['besu', false], 'testnet' => ['testnet', false], 'registry with simulation' => ['simulated', true], 'registry with besu' => ['besu', true]];
    }

    #[DataProvider('unsupportedSettings')]
    public function test_new_legacy_network_or_registry_bypass_requests_return_422_without_network_or_local_writes(string $driver, bool $registry): void
    {
        $this->signIn();
        $run = $this->finalizedRun();
        $before = $run->refresh()->getAttributes();
        config(['blockchain.driver' => $driver, 'blockchain.registry.enabled' => $registry]);
        Http::preventStrayRequests();
        Http::fake();

        $this->postJson(route('payroll-runs.anchor', $run))->assertUnprocessable()->assertSee('New blockchain proofs require versioned payroll registration.');
        $this->postJson(route('payroll-runs.anchor', $run))->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertSame($before, $run->fresh()->getAttributes());
        $this->assertDatabaseCount('blockchain_anchors', 0);
        $this->assertDatabaseCount('blockchain_verification_logs', 0);
        $this->assertDatabaseCount('payroll_registrations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Legacy network submission is disabled.')
            ->assertDontSee('action="'.route('payroll-runs.anchor', $run).'"', false);
    }

    /** @return array<string, array{string}> */
    public static function historicalStates(): array
    {
        return ['pending' => ['PENDING'], 'confirmed' => ['ANCHORED'], 'failed' => ['FAILED']];
    }

    #[DataProvider('historicalStates')]
    public function test_repeat_requests_keep_existing_legacy_evidence_without_broadcast_or_replacement(string $status): void
    {
        $this->signIn();
        $run = $this->finalizedRun();
        $anchor = $this->historicalAnchor($run, ['anchor_status' => $status]);
        $before = $anchor->fresh()->getAttributes();
        config(['blockchain.driver' => 'besu']);
        Http::fake();

        $this->post(route('payroll-runs.anchor', $run))->assertRedirect(route('blockchain-anchors.show', $anchor));

        Http::assertNothingSent();
        $this->assertSame($before, $anchor->fresh()->getAttributes());
        $this->assertDatabaseCount('blockchain_anchors', 1);
        $this->assertDatabaseCount('payroll_registrations', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_simulation_is_explicitly_local_in_the_form_response_and_proof_views(): void
    {
        $this->signIn();
        config(['blockchain.driver' => 'simulated', 'blockchain.registry.enabled' => false]);
        $run = $this->finalizedRun();
        Http::fake();

        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Record simulated local proof')->assertSee('Local simulation only');
        $this->post(route('payroll-runs.anchor', $run))->assertRedirect()
            ->assertSessionHas('status', 'Simulated local proof recorded. No blockchain transaction or independent blockchain evidence was created.');

        $anchor = $run->anchor()->sole();
        $this->assertStringStartsWith('simulated:', $anchor->network_name);
        $this->assertNull($anchor->contract_address);
        $this->get(route('blockchain.index'))->assertOk()->assertSee('Simulated local proof');
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Simulated local proof: no independent blockchain evidence.');
        $this->get(route('blockchain-anchors.show', $anchor))->assertOk()->assertSee('no independent blockchain evidence')->assertSee('A legacy MATCH is limited to this scope.');
        Http::assertNothingSent();
    }

    public function test_simulation_audit_failure_rolls_back_the_local_record_and_retry_creates_only_one(): void
    {
        $this->signIn();
        config(['blockchain.driver' => 'simulated', 'blockchain.registry.enabled' => false]);
        $run = $this->finalizedRun();
        Http::fake();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Synthetic legacy audit failure');
        });

        try {
            $this->postJson(route('payroll-runs.anchor', $run))->assertServerError();
            $this->assertDatabaseCount('blockchain_anchors', 0);
            $this->assertDatabaseCount('audit_logs', 0);
        } finally {
            AuditLog::flushEventListeners();
        }
        $this->post(route('payroll-runs.anchor', $run))->assertRedirect();
        $this->post(route('payroll-runs.anchor', $run))->assertRedirect();

        $this->assertDatabaseCount('blockchain_anchors', 1);
        $this->assertDatabaseCount('audit_logs', 1);
        Http::assertNothingSent();
    }

    /** @return array<string, array{string, ?string, string}> */
    public static function legacyIdentities(): array
    {
        return [
            'chain' => ['besu:1337', '0x'.str_repeat('b', 40), 'Legacy blockchain proof'],
            'missing chain contract' => ['besu:1337', null, 'Legacy blockchain proof'],
            'simulation' => ['simulated:QA', null, 'Simulated local proof'],
            'unknown' => ['unknown-network', null, 'Unclassified legacy proof'],
        ];
    }

    #[DataProvider('legacyIdentities')]
    public function test_historical_identity_is_labeled_without_rewriting_its_evidence(string $network, ?string $contract, string $label): void
    {
        $this->signIn();
        $run = $this->finalizedRun();
        $anchor = $this->historicalAnchor($run, ['network_name' => $network, 'contract_address' => $contract]);
        $before = $anchor->fresh()->getAttributes();

        $this->get(route('blockchain.index'))->assertOk()->assertSee($label);
        $this->get(route('blockchain-anchors.show', $anchor))->assertOk()->assertSee('Legacy digest scope');

        $this->assertSame($before, $anchor->fresh()->getAttributes());
    }

    public function test_unknown_network_cannot_fall_back_to_a_simulated_match(): void
    {
        $this->signIn();
        $run = $this->finalizedRun();
        $anchor = $this->historicalAnchor($run, ['network_name' => 'unknown-network', 'contract_address' => null]);
        config(['blockchain.driver' => 'besu']);
        Http::fake();

        $this->postJson(route('blockchain-anchors.verify', $anchor))->assertServiceUnavailable();

        $this->assertDatabaseCount('blockchain_verification_logs', 0);
        Http::assertNothingSent();
    }

    public function test_legacy_hash_scope_remains_unchanged_and_its_exclusions_are_visible(): void
    {
        $this->signIn();
        $run = $this->finalizedRun();
        $anchor = $this->historicalAnchor($run, ['network_name' => 'simulated:QA', 'contract_address' => null]);
        $detail = $run->details()->sole();
        $detail->update(['payable_days' => 0, 'department_snapshot' => 'Changed legacy metadata']);

        $this->assertSame($anchor->payload_hash, app(BlockchainAnchorService::class)->hashPayrollRun($run));
        $this->get(route('blockchain-anchors.show', $anchor))->assertOk()->assertSee('It excludes employer contributions, period and approval evidence, payable days');
    }

    public function test_retrospective_versioned_registration_preserves_the_original_legacy_proof(): void
    {
        $user = $this->signIn();
        $run = $this->finalizedRun();
        $run->details()->sole()->update(['department_snapshot' => 'Finance', 'position_snapshot' => 'Payroll Clerk', 'salary_rate_snapshot' => '45000.00', 'pay_basis_snapshot' => 'MONTHLY', 'input_recorded_by' => $user->getKey()]);
        PayrollApproval::create(['payroll_run_id' => $run->getKey(), 'approval_step' => 'APPROVED', 'action' => 'APPROVED', 'acted_by' => $user->getKey(), 'acted_at' => now()->subMinute()]);
        $anchor = $this->historicalAnchor($run, ['network_name' => 'simulated:QA', 'contract_address' => null]);
        $before = $anchor->fresh()->getAttributes();
        config(['blockchain.registry' => [
            'enabled' => true, 'organization_id' => 'lgu-example', 'bridge_url' => 'http://bridge.test',
            'bridge_token' => str_repeat('t', 40), 'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64),
            'contract_address' => '0x'.str_repeat('b', 40), 'code_hash' => '0x'.str_repeat('c', 64),
            'signer_address' => '0x'.str_repeat('d', 40), 'deployment_block' => '10',
        ]]);
        Http::fake();

        $registration = app(PayrollRegistrationService::class)->registerExisting($run->getKey());

        $this->assertSame(1, $registration->hash_schema_version);
        $this->assertSame('QUEUED', $registration->status);
        $this->assertSame($before, $anchor->fresh()->getAttributes());
        $this->assertDatabaseCount('blockchain_anchors', 1);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_REGISTRATION_RETROSPECTIVE']);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('View registration')->assertDontSee('Record simulated local proof');
        Http::assertNothingSent();
    }

    public function test_viewer_cannot_create_a_simulation_or_read_legacy_evidence(): void
    {
        $this->signIn(Role::VIEWER);
        $run = $this->finalizedRun();

        $this->postJson(route('payroll-runs.anchor', $run))->assertForbidden();
        $this->get(route('blockchain.index'))->assertForbidden();

        $this->assertDatabaseCount('blockchain_anchors', 0);
    }

    public function test_guest_cannot_request_legacy_submission(): void
    {
        $run = PayrollRun::factory()->create(['status' => 'FINALIZED']);

        $this->post(route('payroll-runs.anchor', $run))->assertRedirect(route('login'));

        $this->assertDatabaseCount('blockchain_anchors', 0);
    }
}
