<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BesuAnchor;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\User;
use App\Services\BesuAnchoringService;
use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Mockery;
use RuntimeException;
use Tests\Concerns\FinalizedPayrollFixture;
use Tests\TestCase;

class BesuAnchoringTest extends TestCase
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

    private function transport(array $results, bool $ambiguous = false): array
    {
        $registry = $this->mock(BesuRegistry::class);
        $registry->shouldReceive('health')->andReturn(['writer' => config('besu.writer')]);
        $registry->shouldReceive('sign')->andReturnUsing(fn ($reference, $digest, $nonce) => [
            'raw' => '0x1234', 'hash' => '0x'.str_pad(dechex($nonce + 1), 64, '0', STR_PAD_LEFT),
            'from' => config('besu.writer'), 'nonce' => $nonce,
        ]);
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->with('eth_getTransactionCount', Mockery::any())->andReturn('0x0');
        $rpc->shouldReceive('request')->with('eth_getTransactionByHash', Mockery::any())->andReturn(null);
        $rpc->shouldReceive('broadcast')->andReturnUsing(function ($raw) use ($ambiguous) {
            $anchor = BesuAnchor::sole();
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('0x1234', Crypt::decryptString($anchor->encrypted_transaction));
            $this->assertSame('signed', $anchor->status);
            if ($ambiguous) {
                throw new RuntimeException('secret provider details');
            }

            return $anchor->transaction_hash;
        });
        $verifier = $this->mock(BesuTransactionVerifier::class);
        $verifier->shouldReceive('confirm')->andReturn(...$results);

        return [$registry, $rpc, $verifier];
    }

    public function test_authorized_finalization_reserves_exact_hash_atomically_without_network_io(): void
    {
        Process::fake();
        [$batch, $final] = $this->fixture();
        $anchor = BesuAnchor::sole();
        $commitment = PayrollCommitment::sole();
        $this->assertSame($final->id, $commitment->payroll_finalization_id);
        $this->assertSame(DB::table('payroll_snapshot_archives')->value('sha256'), $commitment->sha256);
        $this->assertSame('queued', $anchor->status);
        $this->assertNull($anchor->transaction_hash);
        DB::transaction(fn () => app(BesuAnchoringService::class)->enqueue($final));
        $this->assertDatabaseCount('besu_anchor_outbox', 1);
        $this->assertDatabaseCount('besu_anchor_events', 1);
        Http::assertNothingSent();
        Process::assertNothingRan();
    }

    public function test_disabled_automatic_anchoring_preserves_existing_finalization_behavior(): void
    {
        config(['besu.automatic_anchoring' => false]);
        $this->fixture();
        $this->assertDatabaseCount('besu_anchor_outbox', 0);
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_command_rotates_pending_and_incomplete_entries_without_starving_queued_work(): void
    {
        $this->freezeTime();
        $this->fixture();
        $this->fixture(period: '2026-11');
        $this->fixture(period: '2026-12');
        [$pending, $incomplete, $queued] = BesuAnchor::orderBy('id')->get()->all();
        DB::table('besu_anchor_outbox')->where('id', $pending->id)->update(['status' => 'pending']);
        $calls = [];
        $this->mock(BesuAnchoringService::class)->shouldReceive('process')->andReturnUsing(function ($id) use (&$calls, $pending) {
            $calls[] = $id;

            return $id === $pending->id ? 'pending' : 'incomplete';
        });
        foreach ([0, 1, 1] as $expectedExit) {
            $this->travel(1)->minutes();
            $this->assertSame($expectedExit, Artisan::call('besu:anchor', ['--limit' => 1]));
        }
        $this->assertSame([$pending->id, $incomplete->id, $queued->id], $calls);
        $this->assertSame('queued', $queued->fresh()->status);
        $this->assertSame('pending', $pending->fresh()->status);
        $this->travel(1)->minutes();
        $this->assertSame(0, Artisan::call('besu:anchor', ['--limit' => 1]));
        $this->assertSame([$pending->id, $incomplete->id, $queued->id, $pending->id], $calls);
    }

    public function test_outbox_failure_rolls_back_finalization_archive_and_workflow_event(): void
    {
        $this->mock(BesuAnchoringService::class)->shouldReceive('enqueue')->once()->andThrow(new RuntimeException('Injected outbox failure'));
        $this->withoutExceptionHandling();
        try {
            $this->fixture();
            $this->fail('Finalization committed without its outbox');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected outbox failure', $e->getMessage());
        }
        $this->assertDatabaseCount('payroll_finalizations', 0);
        $this->assertDatabaseCount('payroll_snapshot_archives', 0);
        $this->assertDatabaseCount('besu_anchor_outbox', 0);
        $this->assertSame(0, DB::table('payroll_workflow_events')->where('action', 'finalize')->count());
    }

    public function test_journal_precedes_broadcast_and_confirmation_is_idempotent(): void
    {
        $this->fixture();
        $this->transport([['status' => 'pending'], ['status' => 'confirmed', 'block_number' => 10, 'block_hash' => '0x'.str_repeat('a', 64), 'confirmed_at' => now()]]);
        $service = app(BesuAnchoringService::class);
        $id = BesuAnchor::sole()->id;
        $this->assertSame('pending', $service->process($id));
        $this->assertNotSame('0x1234', BesuAnchor::sole()->encrypted_transaction);
        $this->assertArrayNotHasKey('encrypted_transaction', BesuAnchor::sole()->toArray());
        $this->assertSame('confirmed', $service->process($id));
        $this->assertSame('confirmed', $service->process($id));
        $this->assertSame(1, DB::table('besu_anchor_events')->where('event', 'signed')->count());
        $this->assertSame(1, DB::table('besu_anchor_events')->where('event', 'confirmed')->count());
        $this->assertSame(1, DB::table('besu_signer_nonces')->value('next_nonce'));
    }

    public function test_ambiguous_send_retains_same_signed_transaction_and_never_reports_success(): void
    {
        $this->fixture();
        [$registry] = $this->transport([['status' => 'pending']], true);
        $service = app(BesuAnchoringService::class);
        $id = BesuAnchor::sole()->id;
        $this->assertSame('incomplete', $service->process($id));
        $hash = BesuAnchor::sole()->transaction_hash;
        $this->assertSame('incomplete', $service->process($id));
        $this->assertSame($hash, BesuAnchor::sole()->transaction_hash);
        $this->assertNull(BesuAnchor::sole()->confirmed_at);
        $this->assertSame(1, DB::table('besu_anchor_events')->where('event', 'signed')->count());
        $this->assertSame(2, DB::table('besu_anchor_events')->where('event', 'check_incomplete')->count());
    }

    public function test_failed_receipt_is_terminal_and_not_confirmed(): void
    {
        $this->fixture();
        $this->transport([['status' => 'failed', 'block_number' => 10, 'block_hash' => '0x'.str_repeat('a', 64)]]);
        $service = app(BesuAnchoringService::class);
        $id = BesuAnchor::sole()->id;
        $this->assertSame('failed', $service->process($id));
        $this->assertSame('failed', $service->process($id));
        $this->assertNull(BesuAnchor::sole()->confirmed_at);
    }

    public function test_revoked_writer_can_be_reconciled_but_cannot_rebroadcast_a_missing_transaction(): void
    {
        $this->fixture();
        $this->transport([['status' => 'pending']]);
        $id = BesuAnchor::sole()->id;
        $this->assertSame('pending', app(BesuAnchoringService::class)->process($id));
        $hash = BesuAnchor::sole()->transaction_hash;
        $this->mock(BesuRegistry::class)->shouldReceive('health')->once()->andThrow(new BesuConnectionException('writer_unauthorized'));
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->with('eth_getTransactionByHash', [$hash])->once()->andReturn(null);
        $rpc->shouldNotReceive('broadcast');
        $this->assertSame('incomplete', app(BesuAnchoringService::class)->process($id));
        $this->assertSame($hash, BesuAnchor::sole()->transaction_hash);
        $this->assertNull(BesuAnchor::sole()->confirmed_at);
        $this->assertSame(1, DB::table('besu_anchor_events')->where('event', 'broadcast_requested')->count());
    }

    public function test_signer_failure_and_tampered_archive_do_not_broadcast(): void
    {
        $this->fixture();
        $this->mock(BesuRegistry::class)->shouldReceive('health')->andThrow(new BesuConnectionException('rpc_unavailable'));
        $this->mock(BesuRpcClient::class)->shouldNotReceive('broadcast');
        $id = BesuAnchor::sole()->id;
        $this->assertSame('incomplete', app(BesuAnchoringService::class)->process($id));
        $this->assertNull(BesuAnchor::sole()->transaction_hash);
        DB::table('payroll_snapshot_archives')->update(['sha256' => str_repeat('0', 64)]);
        $this->assertSame('incomplete', app(BesuAnchoringService::class)->process($id));
        $this->assertNull(BesuAnchor::sole()->confirmed_at);
    }

    public function test_processor_refuses_outer_transaction_and_disabled_command(): void
    {
        $this->fixture();
        DB::beginTransaction();
        try {
            try {
                app(BesuAnchoringService::class)->process(BesuAnchor::sole()->id);
                $this->fail('Sent inside outer transaction');
            } catch (\LogicException $e) {
                $this->assertStringContainsString('outer database transaction', $e->getMessage());
            }
        } finally {
            DB::rollBack();
        }
        config(['besu.automatic_anchoring' => false]);
        $this->artisan('besu:anchor')->assertFailed();
    }

    public function test_nonce_cursor_reserves_distinct_nonces_for_two_batch_versions(): void
    {
        $this->fixture();
        $this->fixture(period: '2026-11');
        [$registry] = $this->transport([['status' => 'failed', 'block_number' => 10, 'block_hash' => '0x'.str_repeat('a', 64)]]);
        foreach (BesuAnchor::orderBy('id')->pluck('id') as $id) {
            $this->assertSame('failed', app(BesuAnchoringService::class)->process($id));
        }
        $this->assertSame([0, 1], BesuAnchor::orderBy('id')->pluck('nonce')->all());
        $this->assertSame(2, DB::table('besu_signer_nonces')->value('next_nonce'));
        $this->assertSame(2, BesuAnchor::distinct()->count('transaction_hash'));
    }

    public function test_journal_identity_tolerates_json_key_order_but_rejects_changed_contract(): void
    {
        $this->fixture();
        [, $rpc] = $this->transport([['status' => 'pending']]);
        $service = app(BesuAnchoringService::class);
        $anchor = BesuAnchor::sole();
        $this->assertSame('pending', $service->process($anchor->id));
        $identity = array_reverse($anchor->fresh()->identity, true);
        DB::table('besu_anchor_outbox')->where('id', $anchor->id)->update(['identity' => json_encode($identity)]);
        $identity['contract_address'] = '0x'.str_repeat('1', 40);
        // A valid reordered identity reaches the verifier rather than failing a PHP array-order comparison.
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->once()->andReturn(['status' => 'failed', 'block_number' => 10, 'block_hash' => '0x'.str_repeat('a', 64)]);
        $this->assertSame('failed', app(BesuAnchoringService::class)->process($anchor->id));
        // Reset only the synthetic journal fixture to exercise configuration drift with the original signature.
        DB::table('besu_anchor_outbox')->where('id', $anchor->id)->update(['status' => 'signed', 'identity' => json_encode($identity)]);
        $this->assertSame('incomplete', app(BesuAnchoringService::class)->process($anchor->id));
        $rpc->shouldHaveReceived('broadcast')->once();
        $this->assertNull($anchor->fresh()->confirmed_at);
    }

    public function test_live_besu_finalized_payroll_is_confirmed_once(): void
    {
        if (getenv('BESU_LIVE_TEST') !== '1') {
            $this->markTestSkipped('Opt in with BESU_LIVE_TEST=1; uses synthetic in-memory payroll and real Besu.');
        }
        Http::allowStrayRequests();
        $nonceBefore = app(BesuRpcClient::class)->request('eth_getTransactionCount', [config('besu.writer'), 'pending']);
        [$batch, $final] = $this->fixture();
        $service = app(BesuAnchoringService::class);
        $id = BesuAnchor::sole()->id;
        for ($poll = 0; $poll < 12; $poll++) {
            $status = $service->process($id);
            if (in_array($status, ['confirmed', 'failed'], true)) {
                break;
            }
            usleep(500000);
        }
        $anchor = BesuAnchor::sole();
        $this->assertSame('confirmed', $status, json_encode(DB::table('besu_anchor_events')->pluck('event')));
        $this->assertNotNull($anchor->confirmed_at);
        $this->assertSame(PayrollCommitment::sole()->sha256, app(BesuRegistry::class)->lookup($anchor->reference)['sha256']);
        $this->assertSame('confirmed', $service->process($id));
        $this->assertDatabaseCount('besu_anchor_outbox', 1);
        $this->assertSame(1, DB::table('besu_anchor_events')->where('event', 'signed')->count());
        $this->assertSame(1, DB::table('besu_anchor_events')->where('event', 'confirmed')->count());
        $this->assertSame($final->snapshot, PayrollFinalization::findOrFail($final->id)->snapshot);
        $nonceAfter = app(BesuRpcClient::class)->request('eth_getTransactionCount', [config('besu.writer'), 'pending']);
        $this->assertSame(1, (int) hexdec(substr($nonceAfter, 2)) - (int) hexdec(substr($nonceBefore, 2)));
        fwrite(STDOUT, json_encode(['transaction_hash' => $anchor->transaction_hash, 'reference' => $anchor->reference,
            'block_number' => $anchor->block_number, 'block_hash' => $anchor->block_hash, 'broadcast_count' => 1], JSON_THROW_ON_ERROR).PHP_EOL);
    }
}
