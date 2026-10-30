<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BesuAnchor;
use App\Models\PayrollCommitment;
use App\Models\PayrollVerification;
use App\Models\User;
use App\Services\BesuAnchoringService;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use App\Services\PayrollVerificationService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\FinalizedPayrollFixture;
use Tests\TestCase;

class BesuIntegrityUiTest extends TestCase
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

    private function journal(string $state = 'confirmed'): BesuAnchor
    {
        $anchor = BesuAnchor::sole();
        $identity = array_intersect_key(config('besu'), array_flip(['chain_id', 'genesis_hash', 'contract_address', 'runtime_code_hash', 'administrator', 'writer']));
        $identity = array_map(fn ($value) => is_string($value) ? strtolower($value) : $value, $identity);
        DB::table('besu_anchor_outbox')->where('id', $anchor->id)->update(['status' => $state, 'identity' => json_encode($identity),
            'transaction_hash' => '0x'.str_repeat('b', 64), 'encrypted_transaction' => Crypt::encryptString('0x1234')]);

        return $anchor->fresh();
    }

    private function proof(string $status = 'confirmed'): void
    {
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->once()->andReturn([
            'status' => $status, 'block_hash' => '0x'.str_repeat('a', 64), 'block_number' => 256,
        ]);
        $this->mock(BesuRegistry::class)->shouldReceive('lookup')->andReturn([
            'exists' => true, 'sha256' => PayrollCommitment::sole()->sha256, 'writer' => config('besu.writer'), 'anchoredAt' => '42',
        ]);
    }

    public function test_verified_ui_uses_fresh_chain_proof_retains_history_and_never_mutates_payroll_or_journal(): void
    {
        [$batch, $final] = $this->fixture();
        $anchor = $this->journal();
        $before = [$batch->getAttributes(), $final->getAttributes(), $anchor->getAttributes()];
        Process::fake();
        $url = route('payroll-verification.show', [$batch, $final]);
        $this->get($url)->assertOk()->assertSee('Verify Integrity')->assertSee($anchor->transaction_hash)->assertSee('Pending');
        $this->assertDatabaseCount('payroll_verifications', 0);
        Http::assertNothingSent();
        $this->proof();
        $key = (string) Str::uuid();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => $key])->assertRedirect($url);
        $check = PayrollVerification::sole();
        $this->assertSame('verified', $check->status);
        $this->assertSame($anchor->id, $check->besu_anchor_id);
        $this->assertNull($check->payroll_anchor_attempt_id);
        $this->assertSame($check->current_hash, $check->network_hash);
        $local = app(PayrollVerificationService::class)->inspect($batch->fresh(), $final->fresh());
        $this->assertFalse(app(PayrollVerificationService::class)->isStale($check, $local), json_encode([$check->toArray(), $local]));
        $this->get($url)->assertOk()->assertSee('Verified')->assertSee('On-chain SHA-256')->assertSee('Last checked')->assertDontSee('0x1234')
            ->assertDontSee('The transaction is pending. It must not be treated as verified.');
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Verified')->assertSee($anchor->transaction_hash)->assertDontSee('Blockchain anchoring has not been performed.');
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => $key])->assertRedirect();
        $this->assertDatabaseCount('payroll_verifications', 1);
        $this->assertSame($before, [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), $anchor->fresh()->getAttributes()]);
        Process::assertNothingRan();
    }

    public function test_altered_isolated_snapshot_cannot_reuse_a_previously_verified_badge(): void
    {
        [$batch, $final] = $this->fixture();
        $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $original = PayrollVerification::sole()->getAttributes();
        $snapshot = $final->snapshot;
        $snapshot['payroll']['employees'][0]['core_breakdown']['totals']['basic_pay'] = '99999.99';
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Integrity Mismatch')->assertDontSee('Blockchain integrity: Verified');
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('Integrity Mismatch')->assertSee('A fresh check is required.');
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('mismatched', PayrollVerification::latest('id')->first()->status);
        $this->assertSame($original, PayrollVerification::orderBy('id')->first()->getAttributes());
    }

    public static function unconfirmed(): array
    {
        return [['queued'], ['pending'], ['failed']];
    }

    #[DataProvider('unconfirmed')]
    public function test_queued_pending_and_failed_anchors_are_never_verified(string $state): void
    {
        [$batch, $final] = $this->fixture();
        if ($state !== 'queued') {
            $this->journal($state);
        }
        if ($state === 'pending') {
            $this->proof('pending');
        } else {
            $this->mock(BesuTransactionVerifier::class)->shouldNotReceive('confirm');
        }
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame($state === 'failed' ? 'failed' : 'pending', PayrollVerification::sole()->status);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertDontSee('Blockchain integrity: Verified');
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertDontSee('A fresh check is required.');
    }

    public function test_outage_after_confirmation_is_saved_and_displayed_as_unavailable_without_secret_leaks(): void
    {
        [$batch, $final] = $this->fixture();
        $anchor = $this->journal();
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->once()->andThrow(new RuntimeException('PRIVATE RPC CREDENTIAL TEST'));
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('unavailable', PayrollVerification::sole()->status);
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('Unavailable')->assertDontSee('PRIVATE RPC CREDENTIAL TEST')->assertDontSee('0x1234');
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Unavailable');
        $this->assertSame($anchor->getAttributes(), $anchor->fresh()->getAttributes());
    }

    public function test_changed_network_identity_cannot_verify_or_contact_the_provider(): void
    {
        [$batch, $final] = $this->fixture();
        $this->journal();
        config(['besu.contract_address' => '0x'.str_repeat('1', 40)]);
        $this->mock(BesuTransactionVerifier::class)->shouldNotReceive('confirm');
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('invalid_reference', PayrollVerification::sole()->reason_code);
        Http::assertNothingSent();
    }

    public static function changedJournalMetadata(): array
    {
        return [['journal'], ['configuration'], ['both']];
    }

    #[DataProvider('changedJournalMetadata')]
    public function test_changed_identity_invalidates_verified_summary_without_rewriting_history(string $change): void
    {
        [$batch, $final] = $this->fixture();
        $anchor = $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $verified = PayrollVerification::sole()->getAttributes();
        if ($change !== 'configuration') {
            $identity = $anchor->identity;
            $identity['contract_address'] = '0x'.str_repeat('1', 40);
            DB::table('besu_anchor_outbox')->where('id', $anchor->id)->update(['identity' => json_encode($identity)]);
        }
        if ($change !== 'journal') {
            config(['besu.contract_address' => '0x'.str_repeat('1', 40)]);
        }
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertDontSee('Blockchain integrity: Verified')->assertSee('The saved check is stale.');
        $page = $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('A fresh check is required.');
        $summary = explode('</section>', explode('aria-label="Integrity verification result">', $page->getContent())[1])[0];
        $this->assertStringNotContainsString('>Verified<', $summary);
        $this->assertSame($verified, PayrollVerification::sole()->getAttributes());
        $this->assertDatabaseCount('payroll_verifications', 1);
        Http::assertNothingSent();
    }

    public function test_equivalent_json_key_order_keeps_verified_result_current(): void
    {
        [$batch, $final] = $this->fixture();
        $anchor = $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $identity = array_reverse($anchor->identity, true);
        DB::table('besu_anchor_outbox')->where('id', $anchor->id)->update(['identity' => json_encode($identity)]);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Verified')->assertDontSee('The saved check is stale.');
    }

    public function test_changed_batch_reference_invalidates_previous_verified_result(): void
    {
        [$batch, $final] = $this->fixture();
        $anchor = $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        DB::table('besu_anchor_outbox')->where('id', $anchor->id)->update(['reference' => '0x'.str_repeat('c', 64)]);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertDontSee('Blockchain integrity: Verified')->assertSee('The saved check is stale.');
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('invalid_reference', PayrollVerification::latest('id')->first()->reason_code);
        $this->assertSame('verified', PayrollVerification::orderBy('id')->first()->status);
        Http::assertNothingSent();
    }

    public function test_older_linked_history_requires_fresh_context_without_rewriting_the_old_check(): void
    {
        [$batch, $final] = $this->fixture();
        $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        // Simulate a linked check retained before the nullable context column was added.
        DB::table('payroll_verifications')->update(['besu_anchor_fingerprint' => null]);
        $old = PayrollVerification::sole()->getAttributes();
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('The saved check is stale.')->assertDontSee('Blockchain integrity: Verified');
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Verified');
        $this->assertNotNull(PayrollVerification::latest('id')->first()->besu_anchor_fingerprint);
        $this->assertSame($old, PayrollVerification::orderBy('id')->first()->getAttributes());
        $this->expectException(\LogicException::class);
        (require base_path('database/migrations/2026_10_30_000004_preserve_besu_verification_context.php'))->down();
    }

    public function test_different_on_chain_hash_is_retained_as_integrity_mismatch(): void
    {
        [$batch, $final] = $this->fixture();
        $this->journal();
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->once()->andReturn([
            'status' => 'confirmed', 'block_hash' => '0x'.str_repeat('a', 64), 'block_number' => 256,
        ]);
        $this->mock(BesuRegistry::class)->shouldReceive('lookup')->once()->andReturn([
            'exists' => true, 'sha256' => str_repeat('f', 64), 'writer' => config('besu.writer'), 'anchoredAt' => '42',
        ]);
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $check = PayrollVerification::sole();
        $this->assertSame('mismatched', $check->status);
        $this->assertSame('chain_hash_mismatch', $check->reason_code);
        $this->assertSame(str_repeat('f', 64), $check->network_hash);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Integrity Mismatch')->assertDontSee('Blockchain integrity: Verified');
    }

    public function test_new_check_replaces_verified_banner_with_pending_and_preserves_old_history(): void
    {
        [$batch, $final] = $this->fixture();
        $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $verified = PayrollVerification::sole()->getAttributes();
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->once()->andReturn(['status' => 'pending']);
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Pending')->assertDontSee('Blockchain integrity: Verified');
        $this->assertSame($verified, PayrollVerification::orderBy('id')->first()->getAttributes());
    }

    public function test_besu_verification_history_is_linked_and_cannot_be_rolled_back(): void
    {
        [$batch, $final] = $this->fixture();
        $anchor = $this->journal();
        $this->proof();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame($anchor->id, PayrollVerification::sole()->besu_anchor_id);
        $this->expectException(\LogicException::class);
        (require base_path('database/migrations/2026_10_30_000003_link_besu_verification_history.php'))->down();
    }

    public function test_viewer_cannot_invoke_besu_checks_and_cross_batch_requests_are_rejected(): void
    {
        [$batch, $final] = $this->fixture();
        $this->journal();
        $this->mock(BesuTransactionVerifier::class)->shouldNotReceive('confirm');
        $viewer = User::factory()->create(['role' => Role::Viewer]);
        $this->actor($viewer);
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertDontSee('Verify Integrity');
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertForbidden();
        $this->actor($this->approver);
        [$other, $otherFinal] = $this->fixture(period: '2026-11');
        $this->post(route('payroll-verification.store', [$batch, $otherFinal]), ['request_key' => (string) Str::uuid()])->assertNotFound();
        $this->assertDatabaseCount('payroll_verifications', 0);
    }

    public function test_live_besu_http_verification_shows_verified_then_isolated_integrity_mismatch(): void
    {
        if (getenv('BESU_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Opt-in real Besu UI integration with synthetic isolated payroll.');
        }
        Http::allowStrayRequests();
        [$batch, $final] = $this->fixture();
        $id = BesuAnchor::sole()->id;
        for ($poll = 0; $poll < 12; $poll++) {
            $status = app(BesuAnchoringService::class)->process($id);
            if (in_array($status, ['confirmed', 'failed'], true)) {
                break;
            }
            usleep(500000);
        }
        $this->assertSame('confirmed', $status);
        $before = BesuAnchor::sole()->getAttributes();
        $nonceBeforeCheck = app(BesuRpcClient::class)->request('eth_getTransactionCount', [config('besu.writer'), 'pending']);
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('verified', PayrollVerification::sole()->status);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Verified');
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('On-chain SHA-256')->assertSee($before['transaction_hash']);
        $rpcUrl = config('besu.rpc_url');
        config(['besu.rpc_url' => 'http://127.0.0.1:1']);
        try {
            $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
            $this->assertSame('unavailable', PayrollVerification::latest('id')->first()->status);
            $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Unavailable');
        } finally {
            config(['besu.rpc_url' => $rpcUrl]);
        }
        $snapshot = $final->snapshot;
        $snapshot['payroll']['employees'][0]['core_breakdown']['totals']['basic_pay'] = '99999.99';
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('mismatched', PayrollVerification::latest('id')->first()->status);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Blockchain integrity: Integrity Mismatch')->assertDontSee('Blockchain integrity: Verified');
        $this->assertSame($before, BesuAnchor::sole()->getAttributes());
        $this->assertSame($nonceBeforeCheck, app(BesuRpcClient::class)->request('eth_getTransactionCount', [config('besu.writer'), 'pending']));
    }
}
