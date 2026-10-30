<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BesuAnchor;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollCommitment;
use App\Models\PayrollCorrectionDecision;
use App\Models\PayrollCorrectionRequest;
use App\Models\PayrollFinalization;
use App\Models\PayrollSnapshotArchive;
use App\Models\PayrollVerification;
use App\Models\User;
use App\Services\BesuAnchoringService;
use App\Services\Blockchain\BesuConnectionException;
use App\Services\Blockchain\BesuJournalRecovery;
use App\Services\Blockchain\BesuRegistry;
use App\Services\Blockchain\BesuRpcClient;
use App\Services\Blockchain\BesuTransactionVerifier;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollCorrectionService;
use App\Services\PayrollDocumentService;
use App\Services\PayrollWorkflowService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\FinalizedPayrollFixture;
use Tests\TestCase;

class PayrollCorrectionTest extends TestCase
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
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
    }

    private function data(PayrollBatch $batch, PayrollFinalization $source, array $overrides = []): array
    {
        return array_replace([
            'request_key' => (string) Str::uuid(),
            'source_hash' => app(PayrollCorrectionService::class)->digest($batch->fresh(), $source->fresh()),
            'reason' => 'FICTIONAL reviewed correction', 'authority_reference' => 'FICTIONAL CORRECTION AUTHORITY',
            'review_confirmed' => 1,
        ], $overrides);
    }

    private function proposal(PayrollBatch $batch, PayrollFinalization $source): PayrollCorrectionRequest
    {
        $this->actor($this->officer);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $this->data($batch, $source))->assertRedirect()->assertSessionHasNoErrors();

        return PayrollCorrectionRequest::where('source_finalization_id', $source->id)->orderByDesc('revision')->firstOrFail();
    }

    private function decide(PayrollBatch $batch, PayrollFinalization $source, PayrollCorrectionRequest $proposal, string $action = 'authorize', array $overrides = [])
    {
        return $this->post(route('payroll-corrections.decide', [$batch, $source, $proposal, $action]), array_replace([
            'source_hash' => $proposal->source_hash, 'reason' => 'FICTIONAL independent review',
            'authority_reference' => $action === 'authorize' ? 'FICTIONAL APPROVAL' : null, 'review_confirmed' => 1,
        ], $overrides));
    }

    private function authorize(PayrollBatch $batch, PayrollFinalization $source): PayrollBatch
    {
        $proposal = $this->proposal($batch, $source);
        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal)->assertRedirect()->assertSessionHasNoErrors();

        return PayrollBatch::findOrFail($proposal->decision->correction_batch_id);
    }

    private function finish(PayrollBatch $batch, int $employeeId, string $loan): PayrollFinalization
    {
        $this->actor($this->officer);
        $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->record_version, 'employee_ids' => [$employeeId]])->assertSessionHasNoErrors();
        $entry = $batch->entries()->sole();
        $this->put(route('payroll-inputs.update', [$batch, $entry]), [
            'record_version' => $batch->fresh()->record_version, 'loans' => [['label' => 'FICTIONAL corrected loan', 'amount' => $loan]],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Reviewed replacement inputs', 'authorization_confirmed' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('payroll-calculations.store', $batch), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::where('payroll_batch_id', $batch->id)->sole();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->fresh()->record_version,
        ]))->assertSessionHasNoErrors();
        $service = app(PayrollWorkflowService::class);
        foreach (['submit', 'approve', 'finalize'] as $action) {
            if ($action !== 'submit') {
                $this->actor($this->approver);
            }
            $this->post(route('payroll-reviews.transition', [$batch, $action]), [
                'record_version' => $batch->fresh()->record_version,
                'submission_id' => $service->latestSubmission($batch)?->id,
                'review_token' => $action === 'submit' ? $service->reviewToken($service->currentSnapshot($batch->fresh())) : null,
                'reason' => 'FICTIONAL correction '.$action, 'review_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }

        return PayrollFinalization::where('payroll_batch_id', $batch->id)->sole();
    }

    public function test_full_replacement_chain_preserves_all_prior_versions_documents_hashes_and_history(): void
    {
        [$original, $first] = $this->fixture();
        $employeeId = $original->entries()->sole()->employee_id;
        $anchoring = app(PayrollAnchoringService::class);
        $firstHash = $anchoring->digest($original, $first);
        $originalArchive = PayrollSnapshotArchive::where('payroll_finalization_id', $first->id)->sole()->getAttributes();
        $this->post(route('payroll-anchoring.act', [$original, $first, 'prepare']), ['sha256' => $firstHash, 'demonstration_confirmed' => 1])->assertRedirect();
        $this->post(route('payroll-verification.store', [$original, $first]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $preserved = [$original->getAttributes(), $first->getAttributes(), PayrollCommitment::sole()->getAttributes(), PayrollVerification::sole()->getAttributes()];
        $documents = app(PayrollDocumentService::class);
        $csv = $documents->csv($documents->prepare($original, $first), $first);
        $secondBatch = $this->authorize($original, $first);
        $this->assertSame(2, $secondBatch->payroll_version);
        $this->assertSame('draft', $secondBatch->status);
        $this->assertSame(0, $secondBatch->entries()->count());
        $this->assertSame($original->id, $secondBatch->root_payroll_batch_id);
        $this->assertSame($first->id, $secondBatch->correction_of_finalization_id);
        $this->get(route('payroll-documents.index', $secondBatch))->assertNotFound();
        $second = $this->finish($secondBatch, $employeeId, '100.01');
        $this->assertSame(2, $second->version);
        $this->assertSame(2, $second->snapshot['version']);
        $this->assertSame($firstHash, $second->snapshot['payroll']['correction']['source_sha256']);
        $this->assertSame($this->approver->id, $second->snapshot['payroll']['correction']['authorized_by']);
        $this->assertSame(bcsub($first->snapshot['payroll']['totals']['net_pay'], '100.01', 2), $second->snapshot['payroll']['totals']['net_pay']);
        $secondHash = $anchoring->digest($secondBatch->fresh(), $second);
        $this->assertNotSame($firstHash, $secondHash);
        $this->post(route('payroll-anchoring.act', [$secondBatch, $second, 'prepare']), ['sha256' => $secondHash, 'demonstration_confirmed' => 1])->assertRedirect();
        $this->assertDatabaseCount('payroll_commitments', 2);
        $thirdBatch = $this->authorize($secondBatch->fresh(), $second);
        $third = $this->finish($thirdBatch, $employeeId, '200.02');
        $this->assertDatabaseCount('payroll_snapshot_archives', 3);
        $this->assertSame($originalArchive, PayrollSnapshotArchive::where('payroll_finalization_id', $first->id)->sole()->getAttributes());
        $this->assertSame($secondHash, PayrollSnapshotArchive::where('payroll_finalization_id', $second->id)->sole()->sha256);
        $this->assertSame(3, $third->version);
        $this->assertSame($original->id, $thirdBatch->root_payroll_batch_id);
        $this->assertSame($secondHash, $third->snapshot['payroll']['correction']['source_sha256']);
        $this->assertSame($preserved, [$original->fresh()->getAttributes(), $first->fresh()->getAttributes(), PayrollCommitment::orderBy('id')->first()->getAttributes(), PayrollVerification::sole()->getAttributes()]);
        $this->assertArrayNotHasKey('correction', $first->fresh()->snapshot['payroll']);
        $this->assertSame($firstHash, $anchoring->digest($original->fresh(), $first->fresh()));
        $this->assertSame($csv, $documents->csv($documents->prepare($original->fresh(), $first->fresh()), $first));
        foreach ([[$original, $first], [$secondBatch, $second], [$thirdBatch, $third]] as [$batch, $final]) {
            $this->get(route('payroll-corrections.index', $batch))->assertOk()->assertSee('Version 1')->assertSee('Version 2')->assertSee('Version 3');
            $this->get(route('payroll-reviews.show', $batch))->assertOk();
            $this->get(route('payroll-documents.index', $batch))->assertOk();
            $this->get(route('payroll-documents.register-csv', [$batch, $final]))->assertOk();
            $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk();
            $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk();
        }
        $this->actor($this->officer);
        $this->post('/payroll-batches', ['period' => '2026-10', 'is_demonstration' => 1, 'payroll_version' => 4])->assertSessionHasErrors('period');
        $this->assertDatabaseCount('payroll_batches', 3);
    }

    public function test_automatic_correction_anchoring_preserves_original_hash_journal_and_checks(): void
    {
        config(['besu.enabled' => true, 'besu.automatic_anchoring' => true]);
        [$original, $first] = $this->fixture();
        $firstAnchor = BesuAnchor::sole();
        $firstCommitment = PayrollCommitment::sole()->getAttributes();
        $snapshot = $first->getAttributes();
        $archive = PayrollSnapshotArchive::sole()->getAttributes();
        $this->post(route('payroll-verification.store', [$original, $first]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $check = PayrollVerification::sole()->getAttributes();
        $correctedBatch = $this->authorize($original, $first);
        $second = $this->finish($correctedBatch, $original->entries()->sole()->employee_id, '100.01');
        $this->assertSame(2, $second->version);
        $anchors = BesuAnchor::orderBy('id')->get();
        $this->assertCount(2, $anchors);
        $this->assertNotSame($anchors[0]->reference, $anchors[1]->reference);
        $this->assertNotSame($firstCommitment['sha256'], PayrollCommitment::orderByDesc('id')->first()->sha256);
        $this->assertSame($snapshot, $first->fresh()->getAttributes());
        $this->assertSame($archive, PayrollSnapshotArchive::orderBy('id')->first()->getAttributes());
        $this->assertSame($check, PayrollVerification::sole()->getAttributes());
        $this->assertSame($firstCommitment, PayrollCommitment::orderBy('id')->first()->getAttributes());
        $this->assertSame($firstAnchor->getAttributes(), $firstAnchor->fresh()->getAttributes());
        $this->get(route('payroll-anchoring.show', [$original, $first]))->assertOk()->assertSee('Automatic anchoring: Queued');
        $this->get(route('payroll-anchoring.show', [$correctedBatch, $second]))->assertOk()->assertSee('Automatic anchoring: Queued');
        $this->mock(BesuJournalRecovery::class)->shouldReceive('find')->andReturn(null);
        $registry = $this->mock(BesuRegistry::class);
        $registry->shouldReceive('health')->andReturn(['writer' => config('besu.writer')]);
        $signed = [];
        $registry->shouldReceive('sign')->twice()->andReturnUsing(function ($reference, $digest, $nonce) use (&$signed) {
            $signed[] = [$reference, $digest, $nonce];

            return ['raw' => '0x1234', 'hash' => '0x'.str_pad(dechex($nonce + 1), 64, '0', STR_PAD_LEFT)];
        });
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->with('eth_getBlockByNumber', ['latest', false])->andReturnUsing(fn () => ['timestamp' => '0x'.dechex(time())]);
        $rpc->shouldReceive('request')->with('eth_getTransactionCount', \Mockery::any())->andReturn('0x0');
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->twice()->andReturn([
            'status' => 'confirmed', 'block_number' => 10, 'block_hash' => '0x'.str_repeat('a', 64), 'confirmed_at' => now(),
        ]);
        foreach ($anchors as $anchor) {
            $this->assertSame('confirmed', app(BesuAnchoringService::class)->process($anchor->id));
        }
        $this->assertSame([$anchors[0]->reference, $firstCommitment['sha256'], 0], $signed[0]);
        $this->assertSame([$anchors[1]->reference, PayrollCommitment::orderByDesc('id')->first()->sha256, 1], $signed[1]);
        $this->assertDatabaseCount('besu_anchor_transactions', 2);
        $this->assertSame($snapshot, $first->fresh()->getAttributes());
        $this->assertSame($check, PayrollVerification::sole()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_live_besu08_outage_recovery_corrected_versions_and_tamper_detection(): void
    {
        if (getenv('BESU_LIVE_DEMO') !== '1') {
            $this->markTestSkipped('Opt-in BESU-08 demonstration: fictional SQLite payroll, two real chain commitments.');
        }
        $this->runBesu08Scenarios(true);
    }

    public function test_besu08_scenarios_with_controlled_chain_responses(): void
    {
        $deployment = json_decode(file_get_contents(base_path('../blockchain/contracts/deployments/local.json')), true);
        config(['besu.chain_id' => 20261030, 'besu.genesis_hash' => $deployment['genesisHash'],
            'besu.contract_address' => $deployment['address'], 'besu.runtime_code_hash' => $deployment['runtimeCodeHash'],
            'besu.administrator' => $deployment['administrator'], 'besu.writer' => $deployment['writer']]);
        $records = [];
        $this->mock(BesuJournalRecovery::class)->shouldReceive('find')->andReturnUsing(function () {
            if (config('besu.rpc_url') === 'http://127.0.0.1:1') {
                throw new BesuConnectionException('rpc_unavailable');
            }

            return null;
        });
        $registry = $this->mock(BesuRegistry::class);
        $registry->shouldReceive('health')->andReturnUsing(fn () => ['writer' => config('besu.writer')]);
        $registry->shouldReceive('sign')->twice()->andReturnUsing(function ($reference, $digest, $nonce) use (&$records) {
            $records[$reference] = ['exists' => true, 'sha256' => $digest, 'writer' => config('besu.writer')];

            return ['raw' => '0x1234', 'hash' => '0x'.str_pad(dechex($nonce + 1), 64, '0', STR_PAD_LEFT)];
        });
        $registry->shouldReceive('lookup')->andReturnUsing(function ($reference) use (&$records) {
            return $records[$reference];
        });
        $rpc = $this->mock(BesuRpcClient::class);
        $rpc->shouldReceive('request')->with('eth_getBlockByNumber', ['latest', false])->andReturnUsing(fn () => ['timestamp' => '0x'.dechex(time())]);
        $rpc->shouldReceive('request')->with('eth_getTransactionCount', \Mockery::any())->andReturn('0x0');
        $rpc->shouldNotReceive('broadcast');
        $this->mock(BesuTransactionVerifier::class)->shouldReceive('confirm')->andReturn([
            'status' => 'confirmed', 'block_number' => 10, 'block_hash' => '0x'.str_repeat('a', 64), 'confirmed_at' => now(),
        ]);
        $this->runBesu08Scenarios(false);
        Http::assertNothingSent();
    }

    private function runBesu08Scenarios(bool $live): void
    {
        config(['besu.enabled' => true, 'besu.automatic_anchoring' => true]);
        if ($live) {
            Http::allowStrayRequests();
        }
        $rpc = app(BesuRpcClient::class);
        app(BesuRegistry::class)->health();
        $head = $rpc->request('eth_getBlockByNumber', ['latest', false]);
        $age = time() - (int) hexdec(substr($head['timestamp'], 2));
        $this->assertTrue($age >= -30 && $age <= 120, 'Live demonstration blocked: chain clock/head is not healthy; no transaction submitted.');
        [$original, $first] = $this->fixture();
        $firstAnchor = BesuAnchor::sole();
        $snapshot = $first->getAttributes();
        $archive = PayrollSnapshotArchive::sole()->getAttributes();
        $commitment = PayrollCommitment::sole()->getAttributes();
        $endpoint = config('besu.rpc_url');
        config(['besu.rpc_url' => 'http://127.0.0.1:1']);
        try {
            $this->assertSame('incomplete', app(BesuAnchoringService::class)->process($firstAnchor->id));
            $this->assertSame('rpc_unavailable', $firstAnchor->fresh()->last_error);
            $this->assertNull($firstAnchor->fresh()->transaction_hash);
            $this->assertSame($snapshot, $first->fresh()->getAttributes());
        } finally {
            config(['besu.rpc_url' => $endpoint]);
        }
        $confirm = function (BesuAnchor $anchor): void {
            for ($poll = 0; $poll < 24; $poll++) {
                $status = app(BesuAnchoringService::class)->process($anchor->id);
                if (in_array($status, ['confirmed', 'failed'], true)) {
                    break;
                }
                usleep(500000);
            }
            $this->assertSame('confirmed', $status, 'Live confirmation did not complete; preserve the chain/journal for investigation.');
            $this->assertSame('confirmed', app(BesuAnchoringService::class)->process($anchor->id));
        };
        $confirm($firstAnchor);
        $firstJournal = $firstAnchor->fresh()->getAttributes();
        $this->post(route('payroll-verification.store', [$original, $first]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $firstCheck = PayrollVerification::sole()->getAttributes();
        $this->assertSame('verified', $firstCheck['status']);
        $corrected = $this->authorize($original, $first);
        $second = $this->finish($corrected, $original->entries()->sole()->employee_id, '100.01');
        $secondCommitment = PayrollCommitment::where('payroll_finalization_id', $second->id)->sole();
        $secondAnchor = BesuAnchor::where('payroll_commitment_id', $secondCommitment->id)->sole();
        $this->assertSame(2, $second->version);
        $this->assertNotSame($commitment['sha256'], $secondCommitment->sha256);
        $this->assertNotSame($firstAnchor->reference, $secondAnchor->reference);
        $confirm($secondAnchor);
        $nonce = $rpc->request('eth_getTransactionCount', [config('besu.writer'), 'pending']);
        foreach ([[$original, $first], [$corrected, $second]] as [$batch, $final]) {
            $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => (string) Str::uuid()])->assertRedirect();
            $this->assertSame('verified', PayrollVerification::latest('id')->first()->status);
        }
        $tampered = $second->snapshot;
        $tampered['payroll']['employees'][0]['core_breakdown']['totals']['basic_pay'] = '99999.99';
        DB::table('payroll_finalizations')->where('id', $second->id)->update(['snapshot' => json_encode($tampered)]);
        $this->post(route('payroll-verification.store', [$corrected, $second]), ['request_key' => (string) Str::uuid()])->assertRedirect();
        $this->assertSame('mismatched', PayrollVerification::latest('id')->first()->status);
        $tamperCheck = PayrollVerification::latest('id')->first();
        $this->assertSame('snapshot_changed', $tamperCheck->reason_code);
        $chainCommitment = app(BesuRegistry::class)->lookup($secondAnchor->reference);
        $this->assertTrue($chainCommitment['exists']);
        $this->assertSame($secondCommitment->sha256, $chainCommitment['sha256']);
        $this->assertNotNull($tamperCheck->current_hash);
        $this->assertNotSame($chainCommitment['sha256'], $tamperCheck->current_hash);
        $this->assertSame($snapshot, $first->fresh()->getAttributes());
        $this->assertSame($archive, PayrollSnapshotArchive::where('payroll_finalization_id', $first->id)->sole()->getAttributes());
        $this->assertSame($commitment, PayrollCommitment::findOrFail($firstAnchor->payroll_commitment_id)->getAttributes());
        $this->assertSame($firstJournal, $firstAnchor->fresh()->getAttributes());
        $this->assertSame($firstCheck, PayrollVerification::orderBy('id')->first()->getAttributes());
        $this->assertSame($nonce, $rpc->request('eth_getTransactionCount', [config('besu.writer'), 'pending']));
        $this->assertDatabaseCount('besu_anchor_transactions', 2);
        if ($live) {
            fwrite(STDOUT, json_encode(['scenario' => 'BESU-08 A/B/C/D', 'transactions' => BesuAnchor::orderBy('id')->get(['reference', 'transaction_hash', 'block_number', 'block_hash'])->toArray()], JSON_THROW_ON_ERROR).PHP_EOL);
        }
    }

    public function test_exact_retries_are_idempotent_and_pending_requests_and_ancestor_forks_are_blocked(): void
    {
        [$batch, $source] = $this->fixture();
        $this->actor($this->officer);
        $data = $this->data($batch, $source);
        $url = route('payroll-corrections.store', [$batch, $source]);
        $this->post($url, $data)->assertRedirect();
        $proposal = PayrollCorrectionRequest::sole();
        $this->post($url, $data)->assertRedirect();
        $this->post($url, array_replace($data, ['reason' => 'Changed retry']))->assertStatus(409);
        $this->post($url, $this->data($batch, $source))->assertStatus(409);
        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal)->assertRedirect();
        $this->decide($batch, $source, $proposal)->assertRedirect();
        $this->decide($batch, $source, $proposal, 'authorize', ['reason' => 'Changed retry'])->assertStatus(409);
        $this->decide($batch, $source, $proposal, 'return')->assertStatus(409);
        $this->actor($this->officer);
        $this->post($url, $data)->assertRedirect();
        $this->post($url, $this->data($batch, $source))->assertStatus(409);
        $this->assertDatabaseCount('payroll_correction_requests', 1);
        $this->assertDatabaseCount('payroll_correction_decisions', 1);
        $this->assertDatabaseCount('payroll_batches', 2);
    }

    public function test_return_retains_reason_and_allows_a_new_revision_without_opening_a_draft(): void
    {
        [$batch, $source] = $this->fixture();
        $proposal = $this->proposal($batch, $source);
        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal, 'return')->assertRedirect();
        $this->decide($batch, $source, $proposal, 'return')->assertRedirect();
        $this->assertDatabaseCount('payroll_batches', 1);
        $next = $this->proposal($batch, $source);
        $this->assertSame(2, $next->revision);
        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal)->assertStatus(409);
        $this->decide($batch, $source, $next)->assertRedirect();
        $this->assertDatabaseCount('payroll_correction_decisions', 2);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_role_access_and_read_only_history(Role $role): void
    {
        [$batch, $source] = $this->fixture();
        $proposal = $this->proposal($batch, $source);
        $this->actor(User::factory()->create(['role' => $role]));
        $this->get(route('payroll-corrections.index', $batch))->assertStatus($role === Role::Administrator ? 403 : 200);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $this->data($batch, $source))->assertStatus($role === Role::PayrollOfficer ? 409 : 403);
        $this->decide($batch, $source, $proposal)->assertStatus($role === Role::Approver ? 302 : 403);
        $this->assertDatabaseCount('payroll_correction_requests', 1);
        $this->assertDatabaseCount('payroll_correction_decisions', $role === Role::Approver ? 1 : 0);
    }

    public function test_role_changes_do_not_allow_requester_or_source_preparer_to_review(): void
    {
        [$batch, $source] = $this->fixture();
        $otherOfficer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actor($otherOfficer);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $this->data($batch, $source))->assertRedirect();
        $proposal = PayrollCorrectionRequest::sole();
        foreach ([$this->officer, $otherOfficer] as $preparer) {
            $preparer->forceFill(['role' => Role::Approver])->save();
            $this->actor($preparer);
            $this->decide($batch, $source, $proposal)->assertForbidden();
            $this->decide($batch, $source, $proposal, 'return')->assertForbidden();
        }
        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal)->assertStatus(409);
        $this->decide($batch, $source, $proposal, 'return')->assertRedirect();
        $this->assertDatabaseCount('payroll_batches', 1);
    }

    public static function invalidInputs(): array
    {
        return [
            'uuid' => ['request_key', 'invalid'], 'hash' => ['source_hash', 'NOT A HASH'],
            'reason required' => ['reason', ''], 'reason length' => ['reason', str_repeat('r', 501)],
            'authority required' => ['authority_reference', ''], 'authority length' => ['authority_reference', str_repeat('a', 256)],
            'review' => ['review_confirmed', 0], 'encoding' => ['reason', "invalid\xFF"],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_request_input_cannot_create_history(string $field, mixed $value): void
    {
        [$batch, $source] = $this->fixture();
        $this->actor($this->officer);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $this->data($batch, $source, [$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('payroll_correction_requests', 0);
        $this->assertDatabaseCount('payroll_batches', 1);
    }

    public function test_stale_hash_and_cross_bound_routes_do_not_create_corrections(): void
    {
        [$batch, $source] = $this->fixture();
        [$other, $otherSource] = $this->fixture(period: '2026-11');
        $this->actor($this->officer);
        $this->post(route('payroll-corrections.store', [$batch, $otherSource]), $this->data($other, $otherSource))->assertNotFound();
        $this->post(route('payroll-corrections.store', [$batch, $source]), $this->data($batch, $source, ['source_hash' => str_repeat('a', 64)]))->assertStatus(409);
        $proposal = $this->proposal($batch, $source);
        $this->actor($this->approver);
        $this->decide($other, $otherSource, $proposal)->assertNotFound();
        $this->decide($batch, $source, $proposal, 'authorize', ['source_hash' => str_repeat('b', 64)])->assertStatus(409);
        $this->decide($batch, $source, $proposal, 'authorize', ['authority_reference' => ''])->assertSessionHasErrors('authority_reference');
        $this->decide($batch, $source, $proposal, 'authorize', ['review_confirmed' => 0])->assertSessionHasErrors('review_confirmed');
        $this->assertDatabaseCount('payroll_correction_decisions', 0);
    }

    public function test_tampered_source_blocks_authorization_but_can_be_returned_without_repairing_source(): void
    {
        [$batch, $source] = $this->fixture();
        $hash = app(PayrollAnchoringService::class)->digest($batch, $source);
        $this->post(route('payroll-anchoring.act', [$batch, $source, 'prepare']), ['sha256' => $hash, 'demonstration_confirmed' => 1])->assertRedirect();
        $proposal = $this->proposal($batch, $source);
        $snapshot = $source->snapshot;
        $snapshot['workflow'][0]['reason'] = 'ISOLATED TEST TAMPERING';
        DB::table('payroll_finalizations')->where('id', $source->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal)->assertStatus(409);
        $this->get(route('payroll-corrections.index', $batch))->assertOk()->assertSee('Integrity review required')->assertDontSee('Authorize correction draft');
        $this->decide($batch, $source, $proposal, 'return')->assertRedirect();
        $this->assertSame($snapshot, $source->fresh()->snapshot);
        $this->assertSame($hash, PayrollCommitment::sole()->sha256);
        $this->assertDatabaseCount('payroll_batches', 1);
    }

    public function test_changed_source_without_commitment_displays_warning_and_only_allows_return(): void
    {
        [$batch, $source] = $this->fixture();
        $proposal = $this->proposal($batch, $source);
        $capturedRequest = $proposal->getAttributes();
        $this->assertDatabaseCount('payroll_commitments', 0);
        $this->actor($this->approver);
        $url = route('payroll-corrections.index', $batch);
        $this->get($url)->assertOk()->assertSee('Authorize correction draft')->assertSee('Return correction request');

        // Isolated tampering: finalized sources cannot be edited through the application.
        $snapshot = $source->snapshot;
        $snapshot['workflow'][0]['reason'] = 'ISOLATED SOURCE CHANGE WITHOUT COMMITMENT';
        DB::table('payroll_finalizations')->where('id', $source->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->get($url)->assertOk()->assertSee('Integrity review required: The finalized source differs from its preserved snapshot.')
            ->assertSee('Return correction request')->assertDontSee('Authorize correction draft');
        $this->actor(User::factory()->create(['role' => Role::Viewer]));
        $this->get($url)->assertOk()->assertSee('Integrity review required: The finalized source differs from its preserved snapshot.')
            ->assertDontSee('Authorize correction draft')->assertDontSee('Return correction request');
        $this->assertSame($capturedRequest, $proposal->fresh()->getAttributes());
        $this->assertDatabaseCount('payroll_correction_decisions', 0);

        $this->actor($this->approver);
        $this->decide($batch, $source, $proposal)->assertStatus(409);
        $this->assertDatabaseCount('payroll_batches', 1);
        $this->assertDatabaseCount('payroll_correction_decisions', 0);
        $this->decide($batch, $source, $proposal, 'return')->assertRedirect();
        // Returning a request does not clear the new independent archived-byte mismatch.
        $this->get($url)->assertOk()->assertSee('The finalized source differs from its preserved snapshot.');
        $this->assertSame('return', $proposal->fresh()->decision->action);
        $this->assertSame($snapshot, $source->fresh()->snapshot);
        $this->assertSame($capturedRequest, $proposal->fresh()->getAttributes());
        $this->assertDatabaseCount('payroll_batches', 1);
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_source_change_after_authorization_blocks_correction_snapshot(): void
    {
        [$batch, $source] = $this->fixture();
        $replacement = $this->authorize($batch, $source);
        $snapshot = $source->snapshot;
        $snapshot['workflow'][0]['reason'] = 'ISOLATED CHANGE AFTER AUTHORIZATION';
        DB::table('payroll_finalizations')->where('id', $source->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->expectException(HttpException::class);
        app(PayrollCorrectionService::class)->context($replacement);
    }

    public function test_authorization_is_not_finalization_and_requester_remains_a_preparer(): void
    {
        [$batch, $source] = $this->fixture();
        $replacement = $this->authorize($batch, $source);
        $this->post(route('payroll-reviews.transition', [$replacement, 'finalize']), [])->assertForbidden();
        $this->assertDatabaseCount('payroll_finalizations', 1);
        $this->actor($this->officer);
        $this->post(route('payroll-reviews.transition', [$replacement, 'submit']), [
            'record_version' => 1, 'review_token' => str_repeat('a', 64), 'reason' => 'FICTIONAL attempt', 'review_confirmed' => 1,
        ])->assertSessionHasErrors('review');
        $final = $this->finish($replacement, $batch->entries()->sole()->employee_id, '100.00');
        $submission = app(PayrollWorkflowService::class)->latestSubmission($replacement);
        $this->assertFalse(app(PayrollWorkflowService::class)->isIndependent($this->officer, $replacement, $submission));
        $this->assertTrue(app(PayrollWorkflowService::class)->isIndependent($this->approver, $replacement, $submission));
        $this->assertSame(2, $final->version);
    }

    public function test_authorization_insert_failure_rolls_back_the_new_batch_and_keeps_request_pending(): void
    {
        [$batch, $source] = $this->fixture();
        $proposal = $this->proposal($batch, $source);
        $this->actor($this->approver);
        PayrollCorrectionDecision::creating(fn () => throw new RuntimeException('Isolated history storage fault'));
        $this->withoutExceptionHandling();
        try {
            $this->decide($batch, $source, $proposal);
            $this->fail('A storage fault was hidden.');
        } catch (RuntimeException $error) {
            $this->assertSame('Isolated history storage fault', $error->getMessage());
        } finally {
            PayrollCorrectionDecision::flushEventListeners();
            PayrollCorrectionDecision::clearBootedModels();
        }
        $this->assertDatabaseCount('payroll_batches', 1);
        $this->assertDatabaseCount('payroll_correction_decisions', 0);
        $this->assertNull($proposal->fresh()->decision);
        $this->assertSame('finalized', $batch->fresh()->status);
    }

    public function test_service_rechecks_stale_sessions_and_inactive_actors_and_requesters(): void
    {
        [$batch, $source] = $this->fixture();
        $proposal = $this->proposal($batch, $source);
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->approver);
        $session = new Store('correction-check', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->approver->auth_version);
        $request->setLaravelSession($session);
        $service = app(PayrollCorrectionService::class);
        $data = $this->data($batch, $source);
        foreach ([['auth_version' => $this->approver->auth_version + 1], ['is_active' => false]] as $change) {
            $this->approver->fresh()->forceFill($change)->save();
            try {
                $service->act($request, $batch, $source, 'authorize', $data, $proposal);
                $this->fail('Stale/inactive reviewer was accepted.');
            } catch (HttpException $error) {
                $this->assertSame(403, $error->getStatusCode());
            }
            DB::table('users')->where('id', $this->approver->id)->update(['auth_version' => $this->approver->auth_version, 'is_active' => true]);
        }
        $this->officer->forceFill(['is_active' => false])->save();
        $this->actor($this->approver->fresh());
        $this->decide($batch, $source, $proposal)->assertStatus(409);
        $this->decide($batch, $source, $proposal, 'return')->assertRedirect();
        $this->assertDatabaseCount('payroll_batches', 1);
    }

    public function test_missing_or_wrong_authorization_context_cannot_be_used_for_a_correction(): void
    {
        [$batch, $source] = $this->fixture();
        $replacement = $this->authorize($batch, $source);
        $service = app(PayrollCorrectionService::class);
        $this->assertSame($source->id, $service->context($replacement)['source_finalization_id']);
        DB::table('payroll_batches')->where('id', $replacement->id)->update(['root_payroll_batch_id' => $replacement->id]);
        try {
            $service->context($replacement->fresh());
            $this->fail('Wrong lineage was accepted.');
        } catch (HttpException $error) {
            $this->assertSame(409, $error->getStatusCode());
        }
        DB::table('payroll_correction_decisions')->where('correction_batch_id', $replacement->id)->update(['correction_batch_id' => null]);
        $this->expectException(HttpException::class);
        $service->context($replacement->fresh());
    }

    public function test_empty_migration_can_roll_back_and_reapply_but_retained_parent_cannot_be_deleted(): void
    {
        $migration = require database_path('migrations/2026_10_27_000004_create_payroll_correction_versions.php');
        $migration->down();
        $migration->up();
        [$batch, $source] = $this->fixture();
        $replacement = $this->authorize($batch, $source);
        try {
            DB::table('payroll_finalizations')->where('id', $source->id)->delete();
            $this->fail('The parent finalization was removed.');
        } catch (QueryException) {
            $this->assertDatabaseHas('payroll_finalizations', ['id' => $source->id]);
        }
        $this->assertSame(2, $replacement->fresh()->payroll_version);
    }

    public function test_history_and_lineage_are_immutable_and_rollback_is_refused(): void
    {
        [$batch, $source] = $this->fixture();
        $replacement = $this->authorize($batch, $source);
        $proposal = PayrollCorrectionRequest::sole();
        $decision = PayrollCorrectionDecision::sole();
        foreach ([$proposal, $decision] as $record) {
            foreach (['update', 'delete'] as $action) {
                try {
                    $action === 'update' ? $record->forceFill(['reason' => 'overwrite'])->save() : $record->delete();
                    $this->fail('Correction audit history was mutable.');
                } catch (LogicException) {
                    $this->assertNotSame('overwrite', $record->fresh()->reason);
                }
            }
        }
        foreach (['payroll_version' => 99, 'root_payroll_batch_id' => null, 'correction_of_finalization_id' => null, 'period_start' => '2026-11-01', 'is_demonstration' => false] as $field => $value) {
            try {
                $replacement->fresh()->forceFill([$field => $value])->save();
                $this->fail('Correction lineage was mutable.');
            } catch (LogicException) {
                $this->assertSame(2, $replacement->fresh()->payroll_version);
            }
        }
        foreach (['delete', 'rollback'] as $action) {
            try {
                $action === 'delete' ? $replacement->delete() : (require database_path('migrations/2026_10_27_000004_create_payroll_correction_versions.php'))->down();
                $this->fail('Retained history was removable.');
            } catch (LogicException) {
                $this->assertDatabaseCount('payroll_correction_decisions', 1);
            }
        }
    }

    public function test_read_only_history_escapes_metadata_and_has_safe_pagination_and_application_times(): void
    {
        [$batch, $source] = $this->fixture();
        $this->travelTo(now()->setDate(2027, 1, 2)->setTime(3, 4, 5));
        $this->actor($this->officer);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $this->data($batch, $source, ['reason' => '<script>fictional()</script>']))->assertRedirect();
        $this->actor($this->approver);
        $this->decide($batch, $source, PayrollCorrectionRequest::sole(), 'return')->assertRedirect();
        $this->assertSame('2027-01-02 03:04:05', PayrollCorrectionRequest::sole()->created_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-02 03:04:05', PayrollCorrectionDecision::sole()->created_at->format('Y-m-d H:i:s'));
        $url = route('payroll-corrections.index', $batch);
        $this->get($url)->assertOk()->assertSee('&lt;script&gt;fictional()&lt;/script&gt;', false)->assertDontSee('<script>fictional()</script>', false)->assertHeader('X-Content-Type-Options', 'nosniff');
        $first = PayrollCorrectionRequest::sole();
        for ($revision = 2; $revision <= 13; $revision++) {
            $extra = PayrollCorrectionRequest::forceCreate([
                'source_finalization_id' => $source->id, 'revision' => $revision, 'request_key' => (string) Str::uuid(),
                'source_hash' => $first->source_hash, 'reason' => 'FICTIONAL pagination fixture',
                'authority_reference' => 'FICTIONAL AUTHORITY', 'requested_by' => $this->officer->id, 'created_at' => now(),
            ]);
            PayrollCorrectionDecision::forceCreate([
                'payroll_correction_request_id' => $extra->id, 'action' => 'return', 'reason' => 'FICTIONAL pagination return',
                'created_by' => $this->approver->id, 'created_at' => now(),
            ]);
        }
        $this->assertSame(10, substr_count($this->get($url)->assertOk()->getContent(), 'Request revision '));
        $this->assertSame(3, substr_count($this->get($url.'?requests=2')->assertOk()->getContent(), 'Request revision '));
        foreach (['requests', 'versions'] as $name) {
            $this->get($url.'?'.$name.'='.PHP_INT_MAX)->assertNotFound();
        }
        $this->assertDatabaseCount('payroll_correction_requests', 13);
        $this->assertDatabaseCount('payroll_correction_decisions', 13);
        $this->travelBack();
    }

    public function test_csrf_guest_and_inactive_session_are_blocked(): void
    {
        [$batch, $source] = $this->fixture();
        $data = $this->data($batch, $source);
        $url = route('payroll-corrections.store', [$batch, $source]);
        auth()->forgetGuards();
        $this->post($url, $data)->assertRedirect(route('login'));
        $this->officer->forceFill(['is_active' => false])->save();
        $this->actor($this->officer);
        $this->post($url, $data)->assertRedirect(route('login'));
        $this->officer->forceFill(['is_active' => true])->save();
        $this->actor($this->officer);
        $this->app->detectEnvironment(fn () => 'production');
        $this->withMiddleware(PreventRequestForgery::class);
        $this->post($url, $data)->assertStatus(419);
        $this->assertDatabaseCount('payroll_correction_requests', 0);
    }

    public function test_draft_and_real_sources_cannot_start_a_correction(): void
    {
        [$batch, $source] = $this->fixture();
        $data = $this->data($batch, $source);
        $this->actor($this->officer);
        DB::table('payroll_batches')->where('id', $batch->id)->update(['status' => 'draft']);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $data)->assertForbidden();
        DB::table('payroll_batches')->where('id', $batch->id)->update(['status' => 'finalized', 'is_demonstration' => false]);
        $this->post(route('payroll-corrections.store', [$batch, $source]), $data)->assertForbidden();
        $this->assertDatabaseCount('payroll_correction_requests', 0);
    }
}
