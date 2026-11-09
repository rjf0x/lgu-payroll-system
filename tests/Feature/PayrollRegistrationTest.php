<?php

namespace Tests\Feature;

use App\Jobs\SubmitPayrollRegistration;
use App\Models\PayrollApproval;
use App\Models\PayrollDetail;
use App\Models\PayrollRegistration;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Blockchain\PayrollCanonicalizer;
use App\Services\Blockchain\PayrollPayloadBuilder;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use App\Services\PayrollRegistrationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['blockchain.registry' => [
            'enabled' => true, 'organization_id' => 'lgu-example', 'bridge_url' => 'http://bridge.test',
            'bridge_token' => str_repeat('t', 40), 'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64),
            'contract_address' => '0x'.str_repeat('b', 40), 'code_hash' => '0x'.str_repeat('c', 64),
            'signer_address' => '0x'.str_repeat('d', 40), 'deployment_block' => '10',
        ]]);
        Http::preventStrayRequests();
    }

    public function test_authorized_finalization_captures_a_versioned_payload_and_outbox_atomically_without_http(): void
    {
        $this->freezeTime();
        [$run, $user] = $this->approvedRun();

        $this->actingAs($user)->post(route('payroll-runs.finalize', $run))->assertRedirect();

        $registration = $run->registration()->firstOrFail();
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->fresh()->status);
        $this->assertSame(PayrollRegistration::QUEUED, $registration->status);
        $this->assertSame(1, $registration->hash_schema_version);
        $this->assertSame(1, $registration->revision);
        $this->assertSame(hash('sha256', $registration->canonical_payload), $registration->payload_hash);
        $this->assertMatchesRegularExpression('/^[a-f0-9-]{14}4[a-f0-9-]{21}$/', $registration->payroll_reference);
        $payload = json_decode($registration->canonical_payload, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Finance', $payload['details'][0]['department_snapshot']);
        $this->assertSame('45000.00', $payload['details'][0]['net_pay']);
        $this->assertSame($user->getKey().'', $payload['run']['approval']['acted_by']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_REGISTRATION_QUEUED']);
        $this->assertDatabaseCount('blockchain_anchors', 0);
        Http::assertNothingSent();
        $this->actingAs($user)->postJson(route('payroll-runs.finalize', $run))->assertStatus(422);
        $this->assertDatabaseCount('payroll_registrations', 1);
    }

    public function test_invalid_covered_data_rolls_back_finalization_and_approval_audit(): void
    {
        [$run, $user] = $this->approvedRun();
        $run->details()->update(['input_recorded_by' => null]);

        $this->actingAs($user)->postJson(route('payroll-runs.finalize', $run))->assertUnprocessable()->assertJsonValidationErrors('blockchain');

        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->fresh()->status);
        $this->assertNull($run->fresh()->finalized_at);
        $this->assertDatabaseCount('payroll_registrations', 0);
        $this->assertDatabaseMissing('payroll_approvals', ['action' => PayrollApproval::ACTION_FINALIZED]);
        Http::assertNothingSent();
    }

    public function test_capture_persistence_failure_rolls_back_both_the_payroll_and_outbox(): void
    {
        [$run, $user] = $this->approvedRun();
        $this->mock(AuditLogger::class)->shouldReceive('log')->once()->andThrow(new RuntimeException('Synthetic audit failure'));

        try {
            app(PayrollApprovalService::class)->finalize($run, $user);
            $this->fail('Expected finalization to roll back.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }

        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->fresh()->status);
        $this->assertDatabaseCount('payroll_registrations', 0);
    }

    public function test_disabled_registry_preserves_existing_finalization_and_viewer_cannot_finalize(): void
    {
        [$run, $user] = $this->approvedRun();
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('role_name', Role::VIEWER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($viewer)->post(route('payroll-runs.finalize', $run))->assertForbidden();
        $this->assertDatabaseCount('payroll_registrations', 0);
        config(['blockchain.registry.enabled' => false]);

        $this->actingAs($user)->post(route('payroll-runs.finalize', $run))->assertRedirect();

        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->fresh()->status);
        $this->assertDatabaseCount('payroll_registrations', 0);
        Http::assertNothingSent();
    }

    public function test_worker_submits_only_the_captured_hash_and_metadata_after_current_data_changes(): void
    {
        [$run, $user] = $this->approvedRun();
        app(PayrollApprovalService::class)->finalize($run, $user);
        $registration = $run->registration()->firstOrFail();
        $capturedHash = $registration->payload_hash;
        DB::table('payroll_details')->where('payroll_run_id', $run->getKey())->update(['net_pay' => '999.99']);
        $this->fakeBridge($registration);

        (new SubmitPayrollRegistration($registration->getKey()))->handle(app(PayrollRegistrationService::class));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST' && $request->data() === $registration->bridgeRequest()
            && $request['payload_hash'] === $capturedHash && $request->hasHeader('Authorization', 'Bearer '.str_repeat('t', 40)));
        $this->assertSame($capturedHash, $registration->fresh()->payload_hash);
        $this->assertSame(1, $registration->fresh()->attempts);
        $this->assertNull($registration->fresh()->lease_token);
        $current = app(PayrollPayloadBuilder::class)->build($run->fresh(), $registration);
        $this->assertNotSame($capturedHash, app(PayrollCanonicalizer::class)->hash($current));
    }

    public function test_bridge_outage_retains_a_recoverable_request_without_reversing_finalization(): void
    {
        $registration = $this->capture();
        Http::fake(['http://bridge.test/health' => Http::failedConnection()]);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $registration->refresh();
        $this->assertSame(PayrollRegistration::QUEUED, $registration->status);
        $this->assertSame('BRIDGE_UNAVAILABLE', $registration->last_error);
        $this->assertTrue($registration->next_attempt_at->isFuture());
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $registration->payrollRun->status);
        Http::assertSentCount(1);
    }

    public function test_wrong_network_prevents_submission_and_legacy_anchoring_is_blocked_for_registry_runs(): void
    {
        $registration = $this->capture();
        Http::fake(['http://bridge.test/health' => Http::response(['status' => 'READY', 'network' => ['chain_id' => '1']])]);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame('BRIDGE_NETWORK_MISMATCH', $registration->fresh()->last_error);
        Http::assertSentCount(1);
        $user = User::findOrFail($registration->payrollRun->finalized_by);
        $this->actingAs($user)->postJson(route('payroll-runs.anchor', $registration->payrollRun))->assertStatus(422);
        $this->assertDatabaseCount('blockchain_anchors', 0);
    }

    public function test_confirmation_persists_matching_chain_evidence_and_audit(): void
    {
        $this->freezeTime();
        $registration = $this->capture();
        $evidence = $this->evidence($registration);
        $this->fakeBridge($registration, $evidence);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame(PayrollRegistration::CONFIRMED, $registration->fresh()->status);
        $this->assertSame($evidence['transaction_hash'], $registration->fresh()->transaction_hash);
        $this->assertSame($evidence, $registration->fresh()->evidence);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_REGISTRATION_CONFIRMED']);
        Http::assertSentCount(2);
    }

    public function test_conflicting_hash_is_terminal_and_does_not_retry(): void
    {
        $registration = $this->capture();
        $evidence = $this->evidence($registration);
        $evidence['payload_hash'] = str_repeat('e', 64);
        $this->fakeBridge($registration, $evidence);
        app(PayrollRegistrationService::class)->synchronize($registration->getKey());
        $this->assertSame(PayrollRegistration::CONFLICT, $registration->fresh()->status);
        $this->assertNull($registration->fresh()->next_attempt_at);
        app(PayrollRegistrationService::class)->synchronize($registration->getKey());
        Http::assertSentCount(2);
    }

    public function test_duplicate_workers_are_excluded_by_the_persisted_lease(): void
    {
        $registration = $this->capture();
        $network = $registration->target_network;
        unset($network['bridge_url']);
        Http::fake([
            'http://bridge.test/health' => function () use ($registration, $network) {
                app(PayrollRegistrationService::class)->synchronize($registration->getKey());

                return Http::response(['status' => 'READY', 'network' => $network]);
            },
            $this->path($registration) => Http::response(['registration' => null, 'submission' => null]),
            'http://bridge.test/registrations' => Http::response([...$registration->bridgeRequest(), 'status' => 'QUEUED', 'transaction_hash' => null], 202),
        ]);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame(1, $registration->fresh()->attempts);
        Http::assertSentCount(3);
    }

    public function test_scheduler_recovers_due_outbox_rows_and_respects_poll_and_lease_delays(): void
    {
        $registration = $this->capture();
        Bus::fake([SubmitPayrollRegistration::class]);

        $this->artisan('blockchain:dispatch-registrations')->assertSuccessful();

        Bus::assertDispatched(SubmitPayrollRegistration::class, fn ($job): bool => $job->registrationId === $registration->getKey());
        $registration->update(['next_attempt_at' => now()->addMinutes(2), 'lease_token' => '123e4567-e89b-42d3-a456-426614174000']);
        Bus::fake([SubmitPayrollRegistration::class]);
        $this->artisan('blockchain:dispatch-registrations')->assertSuccessful();
        Bus::assertNotDispatched(SubmitPayrollRegistration::class);
        $this->travel(3)->minutes();
        $this->artisan('blockchain:dispatch-registrations')->assertSuccessful();
        Bus::assertDispatched(SubmitPayrollRegistration::class);
    }

    public function test_corrupted_capture_is_not_submitted(): void
    {
        $registration = $this->capture();
        $registration->update(['canonical_payload' => '{}']);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame(PayrollRegistration::FAILED, $registration->fresh()->status);
        $this->assertSame('LOCAL_PAYLOAD_INVALID', $registration->fresh()->last_error);
        Http::assertNothingSent();
    }

    public function test_changed_network_configuration_does_not_retarget_an_existing_request(): void
    {
        $registration = $this->capture();
        config(['blockchain.registry.contract_address' => '0x'.str_repeat('f', 40)]);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame('REGISTRY_TARGET_CHANGED', $registration->fresh()->last_error);
        $this->assertSame('0x'.str_repeat('b', 40), $registration->fresh()->target_network['contract_address']);
        Http::assertNothingSent();
    }

    #[DataProvider('invalidEvidence')]
    public function test_invalid_bridge_evidence_cannot_confirm_a_payroll(string $field, mixed $value): void
    {
        $registration = $this->capture();
        $evidence = $this->evidence($registration);
        $evidence[$field] = $value;
        $this->fakeBridge($registration, $evidence);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame(PayrollRegistration::QUEUED, $registration->fresh()->status);
        $this->assertSame('INVALID_BRIDGE_EVIDENCE', $registration->fresh()->last_error);
        $this->assertNull($registration->fresh()->evidence);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidEvidence(): array
    {
        return ['wrong chain' => ['chain_id', '1'], 'wrong reference' => ['payroll_reference', '123e4567-e89b-42d3-a456-426614174000'],
            'wrong contract' => ['contract_address', '0x'.str_repeat('f', 40)], 'missing receipt' => ['transaction_hash', null],
            'invalid block' => ['block_number', 9], 'invalid timestamp' => ['registered_at', 'yesterday']];
    }

    public function test_failed_receipt_evidence_is_retained_without_automatic_resubmission(): void
    {
        $registration = $this->capture();
        $network = $registration->target_network;
        unset($network['bridge_url']);
        $failure = ['successful' => false, 'transaction_hash' => '0x'.str_repeat('e', 64), 'block_number' => 11, 'block_hash' => '0x'.str_repeat('f', 64)];
        Http::fake([
            'http://bridge.test/health' => Http::response(['status' => 'READY', 'network' => $network]),
            $this->path($registration) => Http::response(['registration' => null, 'submission' => [...$registration->bridgeRequest(),
                'status' => 'FAILED', 'transaction_hash' => $failure['transaction_hash'], 'evidence' => $failure]]),
        ]);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());
        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame(PayrollRegistration::FAILED, $registration->fresh()->status);
        $this->assertSame($failure, $registration->fresh()->evidence);
        $this->assertNull($registration->fresh()->next_attempt_at);
        Http::assertSentCount(2);
    }

    public function test_confirmation_without_authoritative_registration_is_rejected(): void
    {
        $registration = $this->capture();
        $network = $registration->target_network;
        unset($network['bridge_url']);
        Http::fake([
            'http://bridge.test/health' => Http::response(['status' => 'READY', 'network' => $network]),
            $this->path($registration) => Http::response(['registration' => null, 'submission' => [...$registration->bridgeRequest(),
                'status' => 'CONFIRMED', 'transaction_hash' => '0x'.str_repeat('e', 64)]]),
        ]);

        app(PayrollRegistrationService::class)->synchronize($registration->getKey());

        $this->assertSame(PayrollRegistration::QUEUED, $registration->fresh()->status);
        $this->assertSame('INVALID_BRIDGE_RESPONSE', $registration->fresh()->last_error);
    }

    public function test_approval_snapshot_timestamps_are_converted_to_utc(): void
    {
        $approval = new PayrollApproval;
        $approval->setRawAttributes(['acted_by' => 7, 'acted_at' => '2026-11-09T11:00:00+08:00', 'remarks' => null]);

        $values = app(PayrollPayloadBuilder::class)->values($approval, PayrollCanonicalizer::APPROVAL);

        $this->assertSame('2026-11-09T03:00:00Z', $values['acted_at']);
        $this->assertSame('7', $values['acted_by']);
    }

    public function test_historical_master_edits_do_not_change_the_captured_payroll_hash(): void
    {
        $registration = $this->capture();
        $run = $registration->payrollRun;
        $run->details->first()->employee->update(['first_name' => 'Changed']);
        DB::table('payroll_periods')->where('payroll_period_id', $run->payroll_period_id)->update(['period_name' => 'Changed outside normal operations']);

        $payload = app(PayrollPayloadBuilder::class)->build($run->fresh(), $registration);

        $this->assertSame($registration->payload_hash, app(PayrollCanonicalizer::class)->hash($payload));
    }

    public function test_finalized_runs_reject_both_public_computation_paths_even_with_stale_models(): void
    {
        [$run, $user] = $this->approvedRun();
        $detail = $run->details()->firstOrFail();
        app(PayrollApprovalService::class)->finalize($run, $user);
        $run->status = PayrollRun::STATUS_DRAFT;
        $service = app(PayrollComputationService::class);
        foreach ([fn () => $service->computeDetail($run, $detail->employmentRecord, $run->payrollPeriod),
            fn () => $service->recomputeTotals($detail, $run->payrollPeriod)] as $operation) {
            try {
                $operation();
                $this->fail('Finalized payroll must not be recomputed.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            }
        }
        $this->assertSame('45000.00', $detail->fresh()->net_pay);
    }

    public function test_retrospective_registration_is_idempotent_and_preserves_finalization(): void
    {
        [$run, $user] = $this->approvedRun();
        config(['blockchain.registry.enabled' => false]);
        app(PayrollApprovalService::class)->finalize($run, $user);
        $original = $run->fresh()->getAttributes();
        $approvalCount = $run->approvals()->count();
        config(['blockchain.registry.enabled' => true]);
        $this->travel(1)->days();

        $this->artisan('blockchain:register-finalized', ['payroll' => $run->getKey()])->assertSuccessful();
        $first = $run->registration()->firstOrFail();
        $this->artisan('blockchain:register-finalized', ['payroll' => $run->getKey()])->assertSuccessful();

        $this->assertDatabaseCount('payroll_registrations', 1);
        $this->assertSame($original, $run->fresh()->getAttributes());
        $this->assertSame($approvalCount, $run->approvals()->count());
        $this->assertSame(PayrollRegistration::QUEUED, $first->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_REGISTRATION_RETROSPECTIVE', 'user_id' => null]);
        Http::assertNothingSent();
    }

    public function test_retrospective_registration_rejects_unfinalized_payroll(): void
    {
        [$run] = $this->approvedRun();
        $this->expectException(HttpException::class);
        try {
            app(PayrollRegistrationService::class)->registerExisting($run->getKey());
        } finally {
            $this->assertDatabaseCount('payroll_registrations', 0);
        }
    }

    public function test_retrospective_registration_requires_an_enabled_registry(): void
    {
        [$run, $user] = $this->approvedRun();
        config(['blockchain.registry.enabled' => false]);
        app(PayrollApprovalService::class)->finalize($run, $user);
        $this->expectException(HttpException::class);
        try {
            app(PayrollRegistrationService::class)->registerExisting($run->getKey());
        } finally {
            $this->assertDatabaseCount('payroll_registrations', 0);
        }
    }

    public function test_retrospective_registration_rejects_missing_approval_evidence(): void
    {
        [$run, $user] = $this->approvedRun();
        config(['blockchain.registry.enabled' => false]);
        app(PayrollApprovalService::class)->finalize($run, $user);
        $run->approvals()->where('action', PayrollApproval::ACTION_APPROVED)->delete();
        config(['blockchain.registry.enabled' => true]);
        $this->expectException(ValidationException::class);
        try {
            app(PayrollRegistrationService::class)->registerExisting($run->getKey());
        } finally {
            $this->assertDatabaseCount('payroll_registrations', 0);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYROLL_REGISTRATION_RETROSPECTIVE']);
        }
    }

    /** @return array{PayrollRun, User} */
    private function approvedRun(): array
    {
        $this->seed(RolePermissionSeeder::class);
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::APPROVER)->value('role_id'), ['assigned_at' => now()]);
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_APPROVED, 'created_by' => $creator->getKey(),
            'submitted_by' => $creator->getKey(), 'submitted_at' => now()->subMinutes(2)]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'department_snapshot' => 'Finance',
            'position_snapshot' => 'Payroll Clerk', 'salary_rate_snapshot' => '45000.00', 'pay_basis_snapshot' => 'MONTHLY', 'input_recorded_by' => $creator->getKey()]);
        PayrollApproval::create(['payroll_run_id' => $run->getKey(), 'approval_step' => PayrollApproval::ACTION_APPROVED,
            'action' => PayrollApproval::ACTION_APPROVED, 'acted_by' => $user->getKey(), 'acted_at' => now()->subMinute(), 'remarks' => null]);

        return [$run, $user];
    }

    private function capture(): PayrollRegistration
    {
        [$run, $user] = $this->approvedRun();
        app(PayrollApprovalService::class)->finalize($run, $user);

        return $run->registration()->firstOrFail();
    }

    private function path(PayrollRegistration $registration): string
    {
        return 'http://bridge.test/registrations/'.$registration->organization_id.'/'.$registration->payroll_reference.'/'.$registration->revision;
    }

    /** @param array<string, mixed>|null $evidence */
    private function fakeBridge(PayrollRegistration $registration, ?array $evidence = null): void
    {
        $network = $registration->target_network;
        unset($network['bridge_url']);
        Http::fake([
            'http://bridge.test/health' => Http::response(['status' => 'READY', 'network' => array_reverse($network, true)]),
            $this->path($registration) => Http::response(['registration' => $evidence, 'submission' => null]),
            'http://bridge.test/registrations' => Http::response([...$registration->bridgeRequest(), 'status' => 'QUEUED', 'transaction_hash' => null], 202),
        ]);
    }

    /** @return array<string, mixed> */
    private function evidence(PayrollRegistration $registration): array
    {
        return ['organization_id' => $registration->organization_id, 'payroll_reference' => $registration->payroll_reference,
            'revision' => $registration->revision, 'payload_hash' => $registration->payload_hash, 'hash_schema_version' => 1,
            'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64), 'contract_address' => '0x'.str_repeat('b', 40),
            'transaction_hash' => '0x'.str_repeat('e', 64), 'block_hash' => '0x'.str_repeat('f', 64),
            'block_number' => 11, 'registered_at' => '1794193200', 'registrar' => '0x'.str_repeat('d', 40)];
    }
}
