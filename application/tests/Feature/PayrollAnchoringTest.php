<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollAnchorAttempt;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollCommitment;
use App\Models\PayrollFinalization;
use App\Models\PayrollWorkflowEvent;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\Blockchain\BlockchainRejected;
use App\Services\Blockchain\BlockchainSubmission;
use App\Services\Blockchain\BlockchainSubmitter;
use App\Services\Blockchain\BlockchainUnavailable;
use App\Services\Blockchain\CanonicalPayrollSnapshot;
use App\Services\Blockchain\UnavailableBlockchainSubmitter;
use App\Services\PayrollAnchoringService;
use App\Services\PayrollWorkflowService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollAnchoringTest extends TestCase
{
    private User $officer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        // Real commit boundaries are part of the submission contract. The base
        // TestCase pins this disposable database to in-memory SQLite before migrations.
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->withoutVite();
        Http::preventStrayRequests();
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
        $this->actor($this->officer);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function fixture(PersonnelClassification $category = PersonnelClassification::Permanent, string $period = '2026-10'): array
    {
        $this->actor($this->officer);
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::where('personnel_classification', $category)->first();
        if (! $schedule) {
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL ANCHOR SOURCE', 'personnel_classification' => $category, 'is_demonstration' => true, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
            $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
            $schedule->forceFill(['published_at' => now(), 'authority_reference' => 'FICTIONAL AUTHORITY'])->save();
        }
        $rate = $schedule->rates()->sole();
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => '2020-01-01',
            'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Clerk',
            'monthly_salary' => '30000.00', 'is_demonstration' => true, 'step_basis_date' => '2020-01-01',
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Fictional anchoring fixture', 'created_by' => $this->officer->id,
        ]);
        $this->post('/payroll-batches', ['period' => $period, 'is_demonstration' => 1])->assertSessionHasNoErrors();
        $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $entry = $batch->entries()->sole();
        $this->put(route('payroll-inputs.update', [$batch, $entry]), [
            'record_version' => $batch->fresh()->record_version, 'loans' => [['label' => 'FICTIONAL LOAN', 'amount' => '0.00']],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional anchoring inputs', 'authorization_confirmed' => 1,
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
                'reason' => 'FICTIONAL anchoring '.$action, 'review_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }

        return [$batch->fresh(), PayrollFinalization::where('payroll_batch_id', $batch->id)->sole()];
    }

    private function act(PayrollBatch $batch, PayrollFinalization $final, string $action = 'prepare', array $overrides = [])
    {
        return $this->post(route('payroll-anchoring.act', [$batch, $final, $action]), array_replace([
            'sha256' => app(PayrollAnchoringService::class)->digest($batch, $final), 'demonstration_confirmed' => 1,
        ], $overrides));
    }

    public function test_canonical_bytes_and_sha256_have_an_independently_reproducible_vector(): void
    {
        $encoder = new CanonicalPayrollSnapshot;
        $this->assertSame('{"a":1,"b":2}', $encoder->encode(['b' => 2, 'a' => 1]));
        $this->assertSame('43258cff783fe7036d8a43033f830adfc60ec037382473548ac742b888292777', hash('sha256', $encoder->encode(['b' => 2, 'a' => 1])));
        $this->assertSame('{"a":[{"x":null,"z":true},"30000.00"],"b":"Peña/測試"}',
            $encoder->encode(['b' => 'Peña/測試', 'a' => [['z' => true, 'x' => null], '30000.00']]));
        $this->assertNotSame($encoder->encode(['a' => [1, 2]]), $encoder->encode(['a' => [2, 1]]));
        $this->assertNotSame($encoder->encode(['a' => '1.00']), $encoder->encode(['a' => 1]));
        $this->assertSame('{"0":"first","1":"second"}', $encoder->encode([1 => 'second', 0 => 'first']));
    }

    public function test_canonical_format_rejects_floating_point_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new CanonicalPayrollSnapshot)->encode(['nested' => ['amount' => 0.1]]);
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_local_preparation_is_idempotent_preserves_payroll_and_never_claims_an_anchor(PersonnelClassification $category): void
    {
        [$batch, $final] = $this->fixture($category);
        $before = [$batch->getAttributes(), $final->getAttributes(), PayrollWorkflowEvent::count()];
        $url = route('payroll-anchoring.show', [$batch, $final]);
        $this->get($url)->assertOk()->assertSee('Not prepared')->assertSee('Not submitted')->assertSee('No authorized blockchain test network');
        $this->assertDatabaseCount('payroll_commitments', 0);
        $this->act($batch, $final)->assertRedirect($url);
        $commitment = PayrollCommitment::sole();
        $this->act($batch, $final)->assertRedirect($url);
        $this->assertDatabaseCount('payroll_commitments', 1);
        $this->assertSame($commitment->getAttributes(), $commitment->fresh()->getAttributes());
        $this->assertDatabaseCount('payroll_anchor_attempts', 0);
        $this->get($url)->assertOk()->assertSee($commitment->sha256)->assertSee('Local interface only')->assertSee('Not submitted')
            ->assertHeader('Cache-Control', 'max-age=0, no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertSame($before, [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), PayrollWorkflowEvent::count()]);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee($url, false);
        Http::assertNothingSent();
    }

    public function test_default_submission_records_unavailable_without_network_or_fabricated_transaction(): void
    {
        [$batch, $final] = $this->fixture();
        $this->assertInstanceOf(UnavailableBlockchainSubmitter::class, app(BlockchainSubmitter::class));
        $this->act($batch, $final)->assertRedirect();
        $this->act($batch, $final, 'submit')->assertRedirect();
        $attempt = PayrollAnchorAttempt::sole();
        $this->assertSame('unavailable', $attempt->status);
        $this->assertNull($attempt->network);
        $this->assertNull($attempt->transaction_reference);
        $this->assertNotNull($attempt->completed_at);
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()->assertSee('Unavailable')->assertSee('No network submission occurred.');
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertDatabaseCount('payroll_commitments', 1);
        $this->assertDatabaseCount('payroll_anchor_attempts', 2);
        $this->assertSame($attempt->getAttributes(), $attempt->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_interface_receives_only_hash_and_stable_key_and_returned_reference_stays_pending(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $commitment = PayrollCommitment::sole();
        $fake = $this->mock(BlockchainSubmitter::class);
        $fake->shouldReceive('submit')->once()->with($commitment->sha256, $commitment->submission_key)
            ->andReturnUsing(function () {
                $this->assertSame('submitting', PayrollAnchorAttempt::sole()->status);

                // Test double only, never a real anchoring acceptance result.
                return new BlockchainSubmission('TEST DOUBLE', 'fictional-test-reference');
            });
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertSame('pending', PayrollAnchorAttempt::sole()->status);
        $this->assertSame('fictional-test-reference', PayrollAnchorAttempt::sole()->transaction_reference);
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertDatabaseCount('payroll_anchor_attempts', 1);
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()->assertSee('Confirmation has not been established.')
            ->assertSee('Resubmission is blocked')->assertDontSee('name="demonstration_confirmed"', false);
    }

    public function test_definite_rejection_can_retry_with_the_same_hash_and_key_without_erasing_failure(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $commitment = PayrollCommitment::sole();
        $fake = $this->mock(BlockchainSubmitter::class);
        $fake->shouldReceive('submit')->once()->with($commitment->sha256, $commitment->submission_key)->andThrow(new BlockchainRejected('PRIVATE PROVIDER DETAIL'));
        $this->act($batch, $final, 'submit')->assertRedirect();
        $first = PayrollAnchorAttempt::sole()->getAttributes();
        $this->assertSame('failed', $first['status']);
        $this->assertNull($first['transaction_reference']);
        $fake->shouldReceive('submit')->once()->with($commitment->sha256, $commitment->submission_key)->andThrow(new BlockchainUnavailable);
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertDatabaseCount('payroll_anchor_attempts', 2);
        $this->assertSame($first, PayrollAnchorAttempt::orderBy('id')->first()->getAttributes());
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()->assertDontSee('PRIVATE PROVIDER DETAIL');
    }

    public function test_ambiguous_exception_retains_unknown_status_and_blocks_blind_retry(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $this->mock(BlockchainSubmitter::class)->shouldReceive('submit')->once()->andThrow(new RuntimeException('PRIVATE CREDENTIAL EXAMPLE'));
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertSame('unknown', PayrollAnchorAttempt::sole()->status);
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertDatabaseCount('payroll_anchor_attempts', 1);
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()->assertSee('Acceptance is uncertain.')->assertDontSee('PRIVATE CREDENTIAL EXAMPLE');
        $this->assertStringNotContainsString('PRIVATE CREDENTIAL EXAMPLE', json_encode(PayrollAnchorAttempt::sole()->getAttributes()));
    }

    public function test_interrupted_attempt_blocks_resubmission_without_calling_transport(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        PayrollAnchorAttempt::forceCreate(['payroll_commitment_id' => PayrollCommitment::sole()->id, 'status' => 'submitting', 'created_by' => $this->approver->id]);
        $this->mock(BlockchainSubmitter::class)->shouldNotReceive('submit');
        $this->act($batch, $final, 'submit')->assertRedirect();
        $this->assertDatabaseCount('payroll_anchor_attempts', 1);
        $this->assertNull(PayrollAnchorAttempt::sole()->completed_at);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_read_and_write_roles_are_enforced(Role $role): void
    {
        [$batch, $final] = $this->fixture();
        $user = User::factory()->create(['role' => $role]);
        $this->actor($user);
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertStatus($role === Role::Administrator ? 403 : 200);
        $response = $this->act($batch, $final);
        if ($role === Role::Approver) {
            $response->assertRedirect();
        } else {
            $response->assertForbidden();
            $this->act($batch, $final, 'submit')->assertForbidden();
            $this->assertDatabaseCount('payroll_commitments', 0);
        }
    }

    public function test_preparer_cannot_anchor_after_changing_to_approver(): void
    {
        [$batch, $final] = $this->fixture();
        $this->officer->forceFill(['role' => Role::Approver])->save();
        $this->actor($this->officer->fresh());
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()->assertDontSee('Prepare local commitment');
        $this->act($batch, $final)->assertForbidden();
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_guest_inactive_and_revoked_sessions_cannot_prepare(): void
    {
        [$batch, $final] = $this->fixture();
        $url = route('payroll-anchoring.show', [$batch, $final]);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($url)->assertRedirect('/login');
        $this->act($batch, $final)->assertRedirect('/login');
        foreach ([['is_active' => false], ['is_active' => true, 'auth_version' => 2]] as $changes) {
            $this->approver->forceFill($changes)->save();
            $this->actingAs($this->approver)->withSession(['auth_version' => 1]);
            $this->act($batch, $final)->assertRedirect('/login');
        }
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_cross_batch_and_unfinalized_or_real_records_fail_closed(): void
    {
        [$batch, $final] = $this->fixture();
        [$other, $otherFinal] = $this->fixture(period: '2026-11');
        $this->get(route('payroll-anchoring.show', [$batch, $otherFinal]))->assertNotFound();
        $this->post(route('payroll-anchoring.act', [$batch, $otherFinal, 'prepare']), [])->assertNotFound();
        DB::table('payroll_batches')->where('id', $batch->id)->update(['status' => 'approved']);
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertNotFound();
        $this->post(route('payroll-anchoring.act', [$batch, $final, 'prepare']), [])->assertForbidden();
        DB::table('payroll_batches')->where('id', $other->id)->update(['is_demonstration' => false]);
        $this->get(route('payroll-anchoring.show', [$other, $otherFinal]))->assertStatus(409);
        $this->post(route('payroll-anchoring.act', [$other, $otherFinal, 'prepare']), ['sha256' => str_repeat('a', 64), 'demonstration_confirmed' => 1])->assertStatus(409);
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_invalid_confirmation_hash_and_submit_before_preparation_do_not_write(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final, overrides: ['demonstration_confirmed' => 0])->assertSessionHasErrors('demonstration_confirmed');
        $this->act($batch, $final, overrides: ['sha256' => 'invalid'])->assertSessionHasErrors('sha256');
        $this->act($batch, $final, overrides: ['sha256' => str_repeat('0', 64)])->assertStatus(409);
        $this->act($batch, $final, 'submit')->assertStatus(409);
        $this->assertDatabaseCount('payroll_commitments', 0);
        $this->assertDatabaseCount('payroll_anchor_attempts', 0);
    }

    public function test_saved_commitment_rejects_changed_snapshot_without_replacing_original(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $commitment = PayrollCommitment::sole()->getAttributes();
        $snapshot = $final->snapshot;
        $snapshot['workflow'][0]['reason'] = 'Changed on isolated test copy';
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $final->refresh();
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertOk()->assertSee('Submission is blocked;')->assertDontSee('name="demonstration_confirmed"', false);
        $this->act($batch, $final, 'submit')->assertStatus(409);
        $this->assertSame($commitment, PayrollCommitment::sole()->getAttributes());
        $this->assertDatabaseCount('payroll_anchor_attempts', 0);
    }

    public function test_live_profile_changes_leave_digest_unchanged_and_envelope_covers_snapshot_metadata(): void
    {
        [$batch, $final] = $this->fixture();
        $service = app(PayrollAnchoringService::class);
        $bytes = $service->bytes($batch, $final);
        $envelope = json_decode($bytes, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($batch->id, $envelope['payroll_batch_id']);
        $this->assertSame($final->id, $envelope['payroll_finalization_id']);
        $this->assertSame($final->version, $envelope['version']);
        $this->assertEquals($final->snapshot, $envelope['snapshot']);
        $employee = Employee::sole();
        $employee->forceFill(['first_name' => 'Changed fictional profile', 'is_active' => false])->save();
        $this->assertSame($bytes, $service->bytes($batch->fresh(), $final->fresh()));
        $snapshot = $final->snapshot;
        $snapshot['submission_id']++;
        DB::table('payroll_finalizations')->where('id', $final->id)->update(['snapshot' => json_encode($snapshot)]);
        $this->get(route('payroll-anchoring.show', [$batch, $final]))->assertStatus(409);
    }

    public function test_csrf_is_required_for_preparation(): void
    {
        [$batch, $final] = $this->fixture();
        $this->app->detectEnvironment(fn () => 'production');
        $this->withMiddleware(PreventRequestForgery::class);
        $this->act($batch, $final)->assertStatus(419);
        $this->assertDatabaseCount('payroll_commitments', 0);
    }

    public function test_commitment_and_attempt_models_reject_mutation_or_deletion(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $this->act($batch, $final, 'submit')->assertRedirect();
        foreach ([PayrollCommitment::sole(), PayrollAnchorAttempt::sole()] as $model) {
            foreach (['update', 'delete'] as $action) {
                try {
                    if ($action === 'update') {
                        $model->forceFill(['created_by' => $this->officer->id])->save();
                    } else {
                        $model->delete();
                    }
                    $this->fail('Retained anchor data was mutable.');
                } catch (LogicException) {
                    $this->assertSame($this->approver->id, $model->fresh()->created_by);
                }
            }
        }
    }

    public function test_attempt_page_offset_boundaries_fail_safely_without_changing_saved_records(): void
    {
        [$batch, $final] = $this->fixture();
        $url = route('payroll-anchoring.show', [$batch, $final]);
        $firstUnsafePage = intdiv(PHP_INT_MAX, 20) + 2;
        $this->get($url)->assertOk()->assertSee('Not submitted');
        $this->act($batch, $final)->assertRedirect();
        $this->act($batch, $final, 'submit')->assertRedirect();
        $before = [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), PayrollCommitment::sole()->getAttributes(), PayrollAnchorAttempt::sole()->getAttributes(), PayrollWorkflowEvent::count()];
        foreach ([PHP_INT_MAX, $firstUnsafePage] as $page) {
            $this->get($url.'?page='.$page)->assertNotFound();
        }
        $this->get($url.'?page='.($firstUnsafePage - 1))->assertOk();
        $this->get($url)->assertOk()->assertSee('Unavailable');
        $this->assertSame($before, [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), PayrollCommitment::sole()->getAttributes(), PayrollAnchorAttempt::sole()->getAttributes(), PayrollWorkflowEvent::count()]);
    }

    public function test_attempt_history_retains_page_boundaries_malformed_fallback_and_read_authorization(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $commitment = PayrollCommitment::sole();
        for ($number = 1; $number <= 21; $number++) {
            PayrollAnchorAttempt::forceCreate([
                'payroll_commitment_id' => $commitment->id, 'status' => $number === 1 ? 'failed' : 'unavailable',
                'created_by' => $this->approver->id, 'completed_at' => now(),
            ]);
        }
        $url = route('payroll-anchoring.show', [$batch, $final]);
        $first = $this->get($url)->assertOk()->assertDontSee('The submission interface reported rejection before acceptance.');
        $this->assertSame(20, substr_count($first->getContent(), 'No network submission occurred.'));
        $second = $this->get($url.'?page=2')->assertOk()->assertSee('The submission interface reported rejection before acceptance.');
        $this->assertSame(1, substr_count($second->getContent(), '<article '));
        $this->get($url.'?page=3')->assertOk()->assertSee('No submission attempts.');
        foreach (['text', '-1', '0', '1.5', '1&page[]=2'] as $page) {
            $pageContent = $this->get($url.'?page='.$page)->assertOk()->getContent();
            $this->assertSame(20, substr_count($pageContent, 'No network submission occurred.'));
        }
        $this->actor(User::factory()->create(['role' => Role::Administrator]));
        $this->get($url.'?page='.PHP_INT_MAX)->assertForbidden();
        $this->assertDatabaseCount('payroll_anchor_attempts', 21);
    }

    public function test_outer_transaction_submission_is_rejected_before_transport_or_attempt_and_can_retry_afterward(): void
    {
        [$batch, $final] = $this->fixture();
        $this->act($batch, $final)->assertRedirect();
        $commitment = PayrollCommitment::sole();
        $before = [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), $commitment->getAttributes()];
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->approver);
        $request->setLaravelSession(app('session.store'));
        $data = ['sha256' => $commitment->sha256, 'demonstration_confirmed' => 1];
        $calls = 0;
        $this->mock(BlockchainSubmitter::class)->shouldReceive('submit')->once()
            ->with($commitment->sha256, $commitment->submission_key)->andReturnUsing(function () use (&$calls) {
                $calls++;
                $this->assertSame(0, DB::transactionLevel());
                $this->assertSame('submitting', PayrollAnchorAttempt::sole()->status);

                return new BlockchainSubmission('TEST DOUBLE', 'fictional-durable-boundary-reference');
            });
        $service = app(PayrollAnchoringService::class);
        foreach (['commit', 'rollback'] as $outerOutcome) {
            DB::beginTransaction();
            try {
                try {
                    $service->act($request, $batch, $final, 'submit', $data);
                    $this->fail('An outer transaction allowed a submission.');
                } catch (HttpException $error) {
                    $this->assertSame(409, $error->getStatusCode());
                }
                $this->assertSame(0, $calls);
                $this->assertDatabaseCount('payroll_anchor_attempts', 0);
                if ($outerOutcome === 'commit') {
                    DB::commit();
                } else {
                    DB::rollBack();
                }
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
            }
        }
        $this->assertSame($before, [$batch->fresh()->getAttributes(), $final->fresh()->getAttributes(), $commitment->fresh()->getAttributes()]);
        $service->act($request, $batch, $final, 'submit', $data);
        $this->assertSame(1, $calls);
        $this->assertSame('pending', PayrollAnchorAttempt::sole()->status);
        $service->act($request, $batch, $final, 'submit', $data);
        $this->assertSame(1, $calls);
        $this->assertDatabaseCount('payroll_anchor_attempts', 1);
    }
}
