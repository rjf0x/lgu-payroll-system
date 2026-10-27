<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollVerification;
use App\Models\User;
use App\Services\Blockchain\BlockchainReader;
use App\Services\Blockchain\BlockchainReceipt;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use App\Services\Blockchain\UnavailableBlockchainReader;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollVerificationService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\FinalizedPayrollFixture;
use Tests\TestCase;

class PayrollVerificationTest extends TestCase
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
        $this->actor($this->officer);
    }

    private function prepare(PayrollBatch $batch, PayrollFinalization $final): PayrollCommitment
    {
        $this->post(route('payroll-anchoring.act', [$batch, $final, 'prepare']), [
            'sha256' => app(PayrollAnchoringService::class)->digest($batch, $final), 'demonstration_confirmed' => 1,
        ])->assertRedirect();

        return PayrollCommitment::where('payroll_finalization_id', $final->id)->sole();
    }

    private function pending(PayrollCommitment $commitment): PayrollAnchorAttempt
    {
        return PayrollAnchorAttempt::forceCreate([
            'payroll_commitment_id' => $commitment->id, 'status' => 'pending',
            'network' => 'TEST DOUBLE NETWORK', 'transaction_reference' => 'fictional-transaction-reference',
            'created_by' => $this->approver->id, 'completed_at' => now(),
        ])->fresh();
    }

    private function check(PayrollBatch $batch, PayrollFinalization $final, ?string $key = null)
    {
        return $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => $key ?? (string) Str::uuid()]);
    }

    private function receipt(PayrollCommitment $commitment, string $status = 'confirmed', ?string $hash = null): BlockchainReceipt
    {
        return new BlockchainReceipt('TEST DOUBLE NETWORK', 'fictional-transaction-reference', $status,
            $hash ?? $commitment->sha256, 'fictional-block-reference', 12);
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_unanchored_local_checks_never_claim_blockchain_verification(PersonnelClassification $category): void
    {
        [$batch, $final] = $this->fixture($category);
        $before = [$batch->getAttributes(), $final->getAttributes()];
        $url = route('payroll-verification.show', [$batch, $final]);
        $this->get($url)->assertOk()->assertSee('No authorized blockchain reader')->assertSee('No integrity checks have been saved.')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertDatabaseCount('payroll_verifications', 0);
        $this->check($batch, $final)->assertRedirect($url);
        $first = PayrollVerification::sole();
        $this->assertSame('not_anchored', $first->status);
        $this->assertSame('not_prepared', $first->local_status);
        $commitment = $this->prepare($batch, $final);
        $this->check($batch, $final)->assertRedirect();
        $second = PayrollVerification::orderByDesc('id')->first();
        $this->assertSame('not_anchored', $second->status);
        $this->assertSame('match', $second->local_status);
        $this->assertSame('not_submitted', $second->reason_code);
        $this->assertSame($commitment->sha256, $second->current_hash);
        $this->assertNull($second->network_hash);
        $this->assertSame($before, [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes()]);
        $this->assertSame($first->getAttributes(), $first->fresh()->getAttributes());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee($url, false);
        Http::assertNothingSent();
    }

    public function test_default_reader_is_unavailable_even_when_a_transaction_reference_is_saved(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $attempt = $this->pending($commitment);
        $this->assertInstanceOf(UnavailableBlockchainReader::class, app(BlockchainReader::class));
        $this->mock(BlockchainSubmitter::class)->shouldNotReceive('submit');
        $this->check($batch, $final)->assertRedirect();
        $check = PayrollVerification::sole();
        $this->assertSame('unavailable', $check->status);
        $this->assertSame('match', $check->local_status);
        $this->assertSame('network_unavailable', $check->reason_code);
        $this->assertNull($check->network_hash);
        $this->assertSame($attempt->getAttributes(), $attempt->fresh()->getAttributes());
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('Unavailable')->assertDontSee('Confirmed match');
        Http::assertNothingSent();
    }

    public static function submissionStates(): array
    {
        return [['unavailable', 'unavailable', 'network_unavailable'], ['failed', 'failed', 'submission_failed'],
            ['submitting', 'pending', 'acceptance_unknown'], ['unknown', 'pending', 'acceptance_unknown']];
    }

    #[DataProvider('submissionStates')]
    public function test_submission_failures_and_uncertainty_are_retained_without_inventing_receipts(string $state, string $expected, string $reason): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $attempt = PayrollAnchorAttempt::forceCreate(['payroll_commitment_id' => $commitment->id, 'status' => $state, 'created_by' => $this->approver->id])->fresh();
        $this->mock(BlockchainReader::class)->shouldNotReceive('receipt');
        $this->check($batch, $final)->assertRedirect();
        $check = PayrollVerification::sole();
        $this->assertSame($expected, $check->status);
        $this->assertSame($reason, $check->reason_code);
        $this->assertNull($check->network_hash);
        $this->assertSame($attempt->getAttributes(), $attempt->fresh()->getAttributes());
    }

    public function test_confirmed_matching_test_receipt_verifies_original_without_modifying_payroll_or_attempt(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $attempt = $this->pending($commitment);
        $before = [$batch->getAttributes(), $final->getAttributes(), $commitment->getAttributes(), $attempt->getAttributes()];
        $this->mock(BlockchainReader::class)->shouldReceive('receipt')->once()->with($attempt->network, $attempt->transaction_reference)->andReturn($this->receipt($commitment));
        $this->check($batch, $final)->assertRedirect();
        $check = PayrollVerification::sole();
        $this->assertSame('verified', $check->status);
        $this->assertSame('confirmed_match', $check->reason_code);
        $this->assertSame($commitment->sha256, $check->network_hash);
        $this->assertSame(12, $check->confirmations);
        $this->assertSame('fictional-block-reference', $check->block_reference);
        $this->assertSame($before, [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), $commitment->fresh()->getAttributes(), $attempt->fresh()->getAttributes()]);
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('Verified')->assertSee('recorded check time')->assertSee('not continuous confirmation');
    }

    public function test_isolated_snapshot_amount_tampering_records_local_mismatch_even_if_arithmetic_is_invalid(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $original = $final->getAttributes();
        $this->check($batch, $final)->assertRedirect();
        $oldCheck = PayrollVerification::sole()->getAttributes();
        $snapshot = $final->snapshot;
        $snapshot['payroll']['employees'][0]['core_breakdown']['totals']['basic_pay'] = '99999.99';
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->mock(BlockchainReader::class)->shouldNotReceive('receipt');
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('A fresh check is required.');
        $this->check($batch, $final)->assertRedirect();
        $changed = PayrollVerification::orderByDesc('id')->first();
        $this->assertSame('mismatched', $changed->status);
        $this->assertSame('snapshot_changed', $changed->reason_code);
        $this->assertNotSame($commitment->sha256, $changed->current_hash);
        $this->assertNull($changed->network_hash);
        $this->assertSame($oldCheck, PayrollVerification::orderBy('id')->first()->getAttributes());
        $this->assertSame($commitment->getAttributes(), $commitment->fresh()->getAttributes());
        // Restore only the isolated test copy, not a production correction/reopen operation.
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => $original['snapshot']]);
        $this->assertSame($original, $final->fresh()->getAttributes());
    }

    public function test_confirmed_different_network_hash_is_mismatched_despite_matching_local_hash(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $this->pending($commitment);
        $other = str_repeat('0', 64);
        $this->mock(BlockchainReader::class)->shouldReceive('receipt')->once()->andReturn($this->receipt($commitment, hash: $other));
        $this->check($batch, $final)->assertRedirect();
        $check = PayrollVerification::sole();
        $this->assertSame('mismatched', $check->status);
        $this->assertSame('match', $check->local_status);
        $this->assertSame('chain_hash_mismatch', $check->reason_code);
        $this->assertSame($other, $check->network_hash);
    }

    public static function receiptStates(): array
    {
        return [['pending', 'pending'], ['failed', 'failed']];
    }

    #[DataProvider('receiptStates')]
    public function test_unconfirmed_or_failed_receipts_cannot_verify_even_with_the_same_hash(string $receiptState, string $expected): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $this->pending($commitment);
        $this->mock(BlockchainReader::class)->shouldReceive('receipt')->once()->andReturn($this->receipt($commitment, $receiptState));
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame($expected, PayrollVerification::sole()->status);
    }

    public function test_receipt_identity_and_provider_exceptions_fail_closed_without_leaking_details(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $this->pending($commitment);
        $reader = $this->mock(BlockchainReader::class);
        $reader->shouldReceive('receipt')->once()->andReturn(new BlockchainReceipt('WRONG TEST NETWORK', 'wrong-test-reference', 'confirmed', $commitment->sha256, 'block', 1));
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame('receipt_identity_mismatch', PayrollVerification::sole()->reason_code);
        $this->assertNull(PayrollVerification::sole()->network_hash);
        $reader->shouldReceive('receipt')->once()->andThrow(new RuntimeException('PRIVATE PROVIDER CREDENTIAL EXAMPLE'));
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame('unavailable', PayrollVerification::orderByDesc('id')->first()->status);
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertDontSee('PRIVATE PROVIDER CREDENTIAL EXAMPLE');
        $this->assertStringNotContainsString('PRIVATE PROVIDER', PayrollVerification::all()->toJson());
    }

    public static function badReceipts(): array
    {
        return [[null, 'block', 1], [str_repeat('a', 64), null, 1], [str_repeat('a', 64), 'block', 0], ['not-sha256', 'block', 1], [str_repeat('a', 64), "invalid\nblock", 1]];
    }

    #[DataProvider('badReceipts')]
    public function test_confirmed_receipt_requires_valid_hash_block_and_positive_confirmations(?string $hash, ?string $block, int $confirmations): void
    {
        $this->expectException(InvalidArgumentException::class);
        new BlockchainReceipt('TEST', 'fictional-reference', 'confirmed', $hash, $block, $confirmations);
    }

    public function test_check_request_is_idempotent_and_a_fresh_request_retains_new_confirmation_evidence(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $this->pending($commitment);
        $reader = $this->mock(BlockchainReader::class);
        $reader->shouldReceive('receipt')->once()->andReturn($this->receipt($commitment, 'pending'));
        $key = (string) Str::uuid();
        $this->check($batch, $final, $key)->assertRedirect();
        $first = PayrollVerification::sole()->getAttributes();
        $this->check($batch, $final, strtoupper($key))->assertRedirect();
        $this->assertDatabaseCount('payroll_verifications', 1);
        $reader->shouldReceive('receipt')->once()->andReturn($this->receipt($commitment));
        $this->check($batch, $final)->assertRedirect();
        $this->assertDatabaseCount('payroll_verifications', 2);
        $this->assertSame($first, PayrollVerification::orderBy('id')->first()->getAttributes());
        $this->assertSame('verified', PayrollVerification::orderByDesc('id')->first()->status);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_existing_read_scope_and_check_roles_are_enforced(Role $role): void
    {
        [$batch, $final] = $this->fixture();
        $user = User::factory()->create(['role' => $role]);
        $this->actor($user);
        $page = $this->get(route('payroll-verification.show', [$batch, $final]))->assertStatus($role === Role::Administrator ? 403 : 200);
        $response = $this->check($batch, $final);
        if (in_array($role, [Role::PayrollOfficer, Role::Approver], true)) {
            $page->assertSee('Run integrity check');
            $response->assertRedirect();
            $this->assertDatabaseCount('payroll_verifications', 1);
        } else {
            $page->assertDontSee('Run integrity check');
            $response->assertForbidden();
            $this->assertDatabaseCount('payroll_verifications', 0);
        }
    }

    public function test_cross_batch_cross_actor_reused_key_and_invalid_requests_are_rejected_without_extra_history(): void
    {
        [$batch, $final] = $this->fixture();
        [$other, $otherFinal] = $this->fixture(period: '2026-11');
        $this->get(route('payroll-verification.show', [$batch, $otherFinal]))->assertNotFound();
        $this->check($batch, $otherFinal)->assertNotFound();
        $this->post(route('payroll-verification.store', [$batch, $final]), ['request_key' => 'invalid'])->assertSessionHasErrors('request_key');
        $key = (string) Str::uuid();
        $this->check($batch, $final, $key)->assertRedirect();
        $this->check($other, $otherFinal, $key)->assertStatus(409);
        $this->actor($this->officer);
        $this->check($batch, $final, $key)->assertStatus(409);
        $this->assertDatabaseCount('payroll_verifications', 1);
    }

    public function test_guest_inactive_revoked_and_unfinalized_or_real_batches_cannot_record_checks(): void
    {
        [$batch, $final] = $this->fixture();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->check($batch, $final)->assertRedirect('/login');
        foreach ([['is_active' => false], ['is_active' => true, 'auth_version' => 2]] as $change) {
            $this->approver->forceFill($change)->save();
            $this->actingAs($this->approver)->withSession(['auth_version' => 1]);
            $this->check($batch, $final)->assertRedirect('/login');
        }
        $this->actor($this->officer);
        DB::table('payroll_batches')->where('id', $batch->id)->update(['status' => 'approved']);
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertNotFound();
        $this->check($batch, $final)->assertForbidden();
        DB::table('payroll_batches')->where('id', $batch->id)->update(['status' => 'finalized', 'is_demonstration' => false]);
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertStatus(409);
        $this->check($batch, $final)->assertStatus(409);
        $this->assertDatabaseCount('payroll_verifications', 0);
    }

    public function test_invalid_snapshot_unsupported_saved_format_and_missing_reference_do_not_call_reader(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $this->mock(BlockchainReader::class)->shouldNotReceive('receipt');
        DB::table('payroll_commitments')->where('id', $commitment->id)->update(['canonicalization' => 'unsupported']);
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame('invalid_commitment', PayrollVerification::sole()->reason_code);
        DB::table('payroll_commitments')->where('id', $commitment->id)->update(['canonicalization' => CanonicalPayrollSnapshot::VERSION]);
        $attempt = $this->pending($commitment);
        DB::table('payroll_anchor_attempts')->where('id', $attempt->id)->update(['transaction_reference' => null]);
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame('invalid_reference', PayrollVerification::orderByDesc('id')->first()->reason_code);
        $snapshot = $final->snapshot;
        $snapshot['audit_float'] = 0.1;
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame('invalid_snapshot', PayrollVerification::orderByDesc('id')->first()->status);
    }

    public function test_history_pagination_boundaries_and_model_retention_are_safe(): void
    {
        [$batch, $final] = $this->fixture();
        $this->check($batch, $final)->assertRedirect();
        $first = PayrollVerification::sole();
        $attributes = $first->getAttributes();
        unset($attributes['id']);
        for ($number = 0; $number < 20; $number++) {
            PayrollVerification::forceCreate([...$attributes, 'request_key' => (string) Str::uuid()]);
        }
        $url = route('payroll-verification.show', [$batch, $final]);
        $this->assertSame(20, substr_count($this->get($url)->assertOk()->getContent(), '<article '));
        $this->assertSame(1, substr_count($this->get($url.'?page=2')->assertOk()->getContent(), '<article '));
        $this->get($url.'?page='.PHP_INT_MAX)->assertNotFound();
        foreach (['update', 'delete', 'rollback'] as $action) {
            try {
                match ($action) {
                    'update' => $first->forceFill(['status' => 'verified'])->save(),
                    'delete' => $first->delete(),
                    'rollback' => (require base_path('database/migrations/2026_10_27_000003_create_payroll_verifications_table.php'))->down(),
                };
                $this->fail('Retained verification history was mutable.');
            } catch (LogicException) {
                $this->assertSame('not_anchored', $first->fresh()->status);
            }
        }
        $this->assertDatabaseCount('payroll_verifications', 21);
    }

    public function test_verification_post_requires_csrf(): void
    {
        [$batch, $final] = $this->fixture();
        $this->app->detectEnvironment(fn () => 'production');
        $this->withMiddleware(PreventRequestForgery::class);
        $this->check($batch, $final)->assertStatus(419);
        $this->assertDatabaseCount('payroll_verifications', 0);
    }

    public function test_shared_encoder_preserves_exact_phase_11_envelope_bytes(): void
    {
        [$batch, $final] = $this->fixture();
        $expected = app(CanonicalPayrollSnapshot::class)->encode([
            'canonicalization' => CanonicalPayrollSnapshot::VERSION,
            'payroll_batch_id' => $batch->id, 'payroll_finalization_id' => $final->id,
            'version' => $final->version, 'snapshot' => $final->snapshot,
        ]);
        $this->assertSame($expected, app(PayrollAnchoringService::class)->bytes($batch, $final));
    }

    public function test_service_rechecks_actor_after_request_creation_before_any_reader_call(): void
    {
        [$batch, $final] = $this->fixture();
        $commitment = $this->prepare($batch, $final);
        $this->pending($commitment);
        $this->mock(BlockchainReader::class)->shouldNotReceive('receipt');
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->approver);
        $request->setLaravelSession(app('session.store'));
        foreach ([['auth_version' => 2], ['auth_version' => 1, 'role' => Role::Viewer->value]] as $change) {
            DB::table('users')->where('id', $this->approver->id)->update($change);
            try {
                app(PayrollVerificationService::class)->check($request, $batch, $final, ['request_key' => (string) Str::uuid()]);
                $this->fail('Revoked actor recorded a verification.');
            } catch (HttpException|AuthorizationException $error) {
                $this->assertTrue($error instanceof AuthorizationException || $error->getStatusCode() === 403);
            }
        }
        $this->assertDatabaseCount('payroll_verifications', 0);
    }

    public function test_history_records_application_check_time_instead_of_database_default_time(): void
    {
        [$batch, $final] = $this->fixture();
        $checkedAt = CarbonImmutable::parse('2026-10-27 12:34:56', config('app.timezone'));
        $this->travelTo($checkedAt);
        $this->check($batch, $final)->assertRedirect();
        $this->assertSame($checkedAt->format('Y-m-d H:i:sP'), PayrollVerification::sole()->created_at->format('Y-m-d H:i:sP'));
        $this->get(route('payroll-verification.show', [$batch, $final]))->assertOk()->assertSee('2026-10-27 12:34:56');
    }
}
