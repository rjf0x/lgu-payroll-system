<?php

namespace Tests\Feature;

use App\Models\BlockchainAnchor;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\PayrollApproval;
use App\Models\PayrollDetail;
use App\Models\PayrollRegistration;
use App\Models\PayrollRegistrationVerification as Verification;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\Blockchain\PayrollCanonicalizer;
use App\Services\Blockchain\PayrollPayloadBuilder;
use App\Services\BlockchainAnchorService;
use App\Services\PayrollApprovalService;
use App\Services\PayrollVerificationService;
use Closure;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        config(['blockchain.registry' => ['enabled' => true, 'organization_id' => 'lgu-example', 'bridge_url' => 'http://bridge.test',
            'bridge_token' => str_repeat('t', 40), 'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64),
            'contract_address' => '0x'.str_repeat('b', 40), 'code_hash' => '0x'.str_repeat('c', 64),
            'signer_address' => '0x'.str_repeat('d', 40), 'deployment_block' => '10']]);
        Http::preventStrayRequests();
    }

    public function test_authorized_verification_records_a_fresh_match_and_audit_without_submitting(): void
    {
        [$registration, $user] = $this->registration();
        $this->fakeRead($registration, $this->evidence($registration));

        $this->actingAs($user)->post(route('payroll-registrations.verify', $registration), ['stored_hash' => str_repeat('f', 64)])
            ->assertRedirect(route('payroll-registrations.show', $registration));

        $log = $registration->verificationLogs()->firstOrFail();
        $this->assertSame(Verification::MATCH, $log->verification_result);
        $this->assertSame($registration->payload_hash, $log->stored_hash);
        $this->assertSame($log->stored_hash, $log->calculated_hash);
        $this->assertSame($user->getKey(), $log->verified_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_REGISTRY_VERIFIED', 'record_id' => (string) $log->getKey()]);
        $this->assertSame(PayrollRegistration::QUEUED, $registration->fresh()->status);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && $request->url() === $this->path($registration));
    }

    public function test_archiving_employee_preserves_the_finalized_registry_payload_and_proof(): void
    {
        [$registration, $user] = $this->registration();
        $run = $registration->payrollRun;
        $detail = $run->details()->sole();
        $before = $registration->fresh()->getAttributes();
        $detailBefore = $detail->getAttributes();
        $this->fakeRead($registration, $this->evidence($registration));

        $this->actingAs($user)->delete(route('employees.destroy', $detail->employee), ['reason' => 'Employee separated after earned payroll.'])->assertRedirect();

        $this->assertSame($before, $registration->fresh()->getAttributes());
        $this->assertSame($detailBefore, $detail->fresh()->getAttributes());
        $log = app(PayrollVerificationService::class)->verify($registration, $user);
        $this->assertSame(Verification::MATCH, $log->verification_result);
        $this->assertSame($registration->payload_hash, $log->calculated_hash);
        Http::assertSentCount(1);
    }

    #[DataProvider('historicalLabels')]
    public function test_historical_reports_preserve_registered_payload_and_verification_after_configuration_changes(string $earningLabel, string $deductionLabel): void
    {
        $earning = EarningType::factory()->create();
        $deduction = DeductionType::factory()->create();
        $rate = ContributionRate::factory()->create();
        [$registration, $user] = $this->registration(function (PayrollDetail $detail) use ($earning, $deduction, $rate, $earningLabel, $deductionLabel): void {
            $detail->earnings()->create(['earning_type_id' => $earning->getKey(), 'earning_name_snapshot' => $earningLabel, 'amount' => 100, 'is_taxable' => false]);
            $detail->deductions()->create(['deduction_type_id' => $deduction->getKey(), 'deduction_name_snapshot' => $deductionLabel, 'amount' => 50]);
            $detail->employerContributions()->create(['contribution_rate_id' => $rate->getKey(), 'contribution_type_snapshot' => 'Saved employer share', 'amount' => 75]);
            $detail->update(['total_earnings' => 100, 'gross_pay' => 45100, 'total_deductions' => 50, 'nthp_before_lbp' => 45050, 'lbp_loan_deduction' => 25, 'net_pay' => 45025]);
        });
        $this->fakeRead($registration, $this->evidence($registration));
        $originalRegistration = $registration->fresh()->getAttributes();
        $detail = $registration->payrollRun->details()->sole();
        $originalDetail = $detail->getAttributes();
        $originalLines = [$detail->earnings()->get()->toArray(), $detail->deductions()->get()->toArray(), $detail->employerContributions()->get()->toArray()];
        $this->assertSame(Verification::MATCH, app(PayrollVerificationService::class)->verify($registration, $user)->verification_result);

        $earning->update(['earning_name' => 'Renamed and archived', 'status' => 'ARCHIVED']);
        $deduction->update(['deduction_name' => 'Renamed and inactive', 'deduction_category' => 'LBP', 'status' => 'INACTIVE']);
        $rate->update(['contribution_type' => 'Renamed employer rate', 'employer_rate' => 0.5]);
        $this->actingAs($user)->get(route('payroll-runs.register', $registration->payrollRun))->assertOk();
        $this->get(route('payroll-runs.register.export', $registration->payrollRun))->assertOk()->streamedContent();
        $this->get(route('reports.deductions', $registration->payrollRun))->assertOk();

        $log = app(PayrollVerificationService::class)->verify($registration, $user);
        $this->assertSame(Verification::MATCH, $log->verification_result);
        $this->assertSame($registration->payload_hash, $log->calculated_hash);
        $this->assertSame($originalRegistration, $registration->fresh()->getAttributes());
        $this->assertSame($originalDetail, $detail->fresh()->getAttributes());
        $this->assertSame($originalLines, [$detail->earnings()->get()->toArray(), $detail->deductions()->get()->toArray(), $detail->employerContributions()->get()->toArray()]);
        Http::assertSentCount(2);
    }

    /** @return array<string, array{string, string}> */
    public static function historicalLabels(): array
    {
        return ['saved labels' => ['Saved earning', 'Saved deduction'], 'legacy missing labels' => ['', '']];
    }

    public function test_verification_times_use_philippine_time_without_changing_stored_evidence(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(23, 40, 52));
        [$registration, $user] = $this->registration();
        $this->fakeRead($registration, $this->evidence($registration));
        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->actingAs($user)->get(route('payroll-registrations.show', $registration))->assertOk()
            ->assertSee('Nov 17, 2026 7:40 AM Asia/Manila')
            ->assertSee('2026-11-17T07:40:52+08:00', false);
        $this->get(route('blockchain.index'))->assertOk()
            ->assertSee('Nov 17, 2026 7:40 AM Asia/Manila');
        $this->get(route('payroll-runs.show', $registration->payroll_run_id))->assertOk()
            ->assertSee('Nov 17, 2026 7:40 AM Asia/Manila');

        $this->assertDatabaseHas('payroll_registration_verifications', [
            'verification_id' => $log->getKey(), 'verified_at' => '2026-11-16 23:40:52',
            'verification_result' => Verification::MATCH, 'stored_hash' => $registration->payload_hash,
        ]);
        $this->assertDatabaseHas('payroll_registrations', [
            'registration_id' => $registration->getKey(), 'canonical_payload' => $registration->canonical_payload,
            'payload_hash' => $registration->payload_hash,
        ]);
    }

    public function test_tampered_payroll_and_replaced_local_hash_cannot_hide_a_mismatch(): void
    {
        [$registration, $user] = $this->registration();
        $originalEvidence = $this->evidence($registration);
        DB::table('payroll_details')->where('payroll_run_id', $registration->payroll_run_id)->update(['net_pay' => '1.00']);
        $payload = app(PayrollPayloadBuilder::class)->build($registration->payrollRun->fresh(), $registration);
        $bytes = app(PayrollCanonicalizer::class)->serialize($payload);
        $registration->update(['payload_hash' => hash('sha256', $bytes), 'canonical_payload' => $bytes]);
        $this->fakeRead($registration, $originalEvidence);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::MISMATCH, $log->verification_result);
        $this->assertSame($originalEvidence['payload_hash'], $log->stored_hash);
        $this->assertSame($registration->payload_hash, $log->calculated_hash);
        $this->assertNotSame($log->stored_hash, $log->calculated_hash);
        $this->assertSame('1.00', $registration->payrollRun->details()->firstOrFail()->net_pay);
        $this->actingAs($user)->get(route('payroll-registrations.show', $registration))->assertOk()
            ->assertSee('Mismatch')->assertSee('role="alert"', false);
    }

    public function test_archive_tampering_is_reported_even_when_the_current_payroll_hash_matches(): void
    {
        [$registration, $user] = $this->registration();
        $this->fakeRead($registration, $this->evidence($registration));
        $registration->update(['canonical_payload' => '{}']);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::MISMATCH, $log->verification_result);
        $this->assertSame($log->stored_hash, $log->calculated_hash);
        $this->assertStringContainsString('Saved registration data differs', $log->remarks);
    }

    public function test_missing_required_payroll_data_is_a_mismatch_after_valid_blockchain_evidence(): void
    {
        [$registration, $user] = $this->registration();
        $this->fakeRead($registration, $this->evidence($registration));
        DB::table('payroll_details')->where('payroll_run_id', $registration->payroll_run_id)->update(['input_recorded_by' => null]);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::MISMATCH, $log->verification_result);
        $this->assertNull($log->calculated_hash);
        $this->assertNotNull($log->stored_hash);
    }

    public function test_outage_after_a_match_records_unavailable_and_does_not_reuse_previous_evidence(): void
    {
        [$registration, $user] = $this->registration();
        $available = true;
        $body = $this->readBody($registration, $this->evidence($registration));
        Http::fake([$this->path($registration) => function () use (&$available, $body) {
            if (! $available) {
                throw new ConnectionException('Synthetic sensitive upstream details');
            }

            return Http::response($body);
        }]);
        app(PayrollVerificationService::class)->verify($registration, $user);
        $available = false;

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::UNAVAILABLE, $log->verification_result);
        $this->assertNull($log->stored_hash);
        $this->assertNull($log->evidence);
        $this->assertDatabaseCount('payroll_registration_verifications', 2);
        $this->assertSame(Verification::UNAVAILABLE, $registration->fresh()->latestVerification->verification_result);
        $this->actingAs($user)->get(route('payroll-registrations.show', $registration))->assertOk()
            ->assertSeeInOrder(['Payroll integrity', 'Unavailable', 'Registration evidence'])
            ->assertDontSee('Synthetic sensitive upstream details');
    }

    #[DataProvider('absenceStates')]
    public function test_absent_evidence_is_not_reported_as_a_match_or_mismatch(string $localStatus, ?string $bridgeStatus, string $expected): void
    {
        [$registration, $user] = $this->registration();
        $registration->update(['status' => $localStatus]);
        $this->fakeRead($registration, null, $bridgeStatus);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame($expected, $log->verification_result);
        $this->assertNull($log->stored_hash);
        $this->assertNull($log->calculated_hash);
    }

    /** @return array<string, array{string, ?string, string}> */
    public static function absenceStates(): array
    {
        return ['outbox waiting' => ['QUEUED', null, Verification::PENDING], 'known transaction pending' => ['SUBMITTED', 'SUBMITTED', Verification::PENDING],
            'failed transaction' => ['FAILED', 'FAILED', Verification::NOT_REGISTERED], 'missing previous confirmation' => ['CONFIRMED', null, Verification::NOT_REGISTERED],
            'unsubstantiated bridge confirmation' => ['QUEUED', 'CONFIRMED', Verification::INVALID_EVIDENCE]];
    }

    #[DataProvider('invalidEvidence')]
    public function test_untrusted_evidence_is_recorded_as_invalid_without_a_payroll_integrity_conclusion(string $field, mixed $value): void
    {
        [$registration, $user] = $this->registration();
        $evidence = $this->evidence($registration);
        $evidence[$field] = $value;
        $this->fakeRead($registration, $evidence);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::INVALID_EVIDENCE, $log->verification_result);
        $this->assertNull($log->calculated_hash);
    }

    /** @return array<string, array{string, mixed}> */
    public static function invalidEvidence(): array
    {
        return ['different chain' => ['chain_id', '1'], 'different contract' => ['contract_address', '0x'.str_repeat('e', 40)],
            'invalid receipt' => ['transaction_hash', null], 'different revision' => ['revision', 2],
            'unsupported schema' => ['hash_schema_version', 2], 'empty registration hash' => ['payload_hash', str_repeat('0', 64)],
            'empty registrar' => ['registrar', '0x'.str_repeat('0', 40)]];
    }

    public function test_read_verification_survives_signer_rotation_and_disabled_capture_without_calling_health(): void
    {
        [$registration, $user] = $this->registration();
        $body = $this->readBody($registration, $this->evidence($registration));
        $body['network']['signer_address'] = '0x'.str_repeat('f', 40);
        config(['blockchain.registry.enabled' => false, 'blockchain.registry.signer_address' => null]);
        Http::fake([$this->path($registration) => Http::response($body)]);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::MATCH, $log->verification_result);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET' && ! str_ends_with($request->url(), '/health'));
    }

    public function test_wrong_bridge_network_or_provider_invalid_evidence_is_not_a_payroll_mismatch(): void
    {
        [$registration, $user] = $this->registration();
        $body = $this->readBody($registration, $this->evidence($registration));
        $body['network']['genesis_hash'] = '0x'.str_repeat('f', 64);
        Http::fake([$this->path($registration) => Http::response($body)]);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::INVALID_EVIDENCE, $log->verification_result);
        $this->assertNull($log->evidence);
    }

    #[DataProvider('providerFailures')]
    public function test_provider_failures_are_classified_without_trusting_error_details(string $error, string $expected): void
    {
        [$registration, $user] = $this->registration();
        Http::fake([$this->path($registration) => Http::response(['error' => $error, 'details' => 'Synthetic private details'], 503)]);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame($expected, $log->verification_result);
        $this->assertStringNotContainsString('Synthetic private details', $log->remarks);
        $this->assertNull($log->evidence);
    }

    /** @return array<string, array{string, string}> */
    public static function providerFailures(): array
    {
        return ['invalid receipt' => ['INVALID_EVIDENCE', Verification::INVALID_EVIDENCE],
            'wrong network' => ['NETWORK_OR_CONTRACT_MISMATCH', Verification::INVALID_EVIDENCE],
            'unreachable node' => ['UPSTREAM_UNAVAILABLE', Verification::UNAVAILABLE]];
    }

    public function test_network_calls_do_not_hold_payroll_locks_and_concurrent_metadata_changes_invalidate_the_attempt(): void
    {
        [$registration, $user] = $this->registration();
        $level = DB::transactionLevel();
        $body = $this->readBody($registration, $this->evidence($registration));
        Http::fake([$this->path($registration) => function () use ($registration, $body, $level) {
            $this->assertSame($level, DB::transactionLevel());
            $registration->update(['payload_hash' => str_repeat('f', 64)]);

            return Http::response($body);
        }]);

        $log = app(PayrollVerificationService::class)->verify($registration, $user);

        $this->assertSame(Verification::INVALID_EVIDENCE, $log->verification_result);
    }

    public function test_unauthenticated_and_viewer_requests_cannot_view_or_verify_registry_records(): void
    {
        [$registration] = $this->registration();
        $show = route('payroll-registrations.show', $registration);
        $verify = route('payroll-registrations.verify', $registration);
        $this->get($show)->assertRedirect(route('login'));
        $this->post($verify)->assertRedirect(route('login'));
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('role_name', Role::VIEWER)->value('role_id'), ['assigned_at' => now()]);

        $this->actingAs($viewer)->get($show)->assertForbidden();
        $this->actingAs($viewer)->post($verify)->assertForbidden();

        $this->assertDatabaseCount('payroll_registration_verifications', 0);
        Http::assertNothingSent();
    }

    #[DataProvider('ineligibleUsers')]
    public function test_service_authorization_cannot_be_bypassed_without_an_eligible_active_user(bool $inactive): void
    {
        [$registration, $user] = $this->registration();
        if ($inactive) {
            $user->update(['account_status' => User::STATUS_INACTIVE]);
        } else {
            $user = User::factory()->create();
        }

        try {
            app(PayrollVerificationService::class)->verify($registration, $user);
            $this->fail('An ineligible user must not verify payroll.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('payroll_registration_verifications', 0);
        Http::assertNothingSent();
    }

    /** @return array<string, array{bool}> */
    public static function ineligibleUsers(): array
    {
        return ['inactive administrator' => [true], 'no verification permission' => [false]];
    }

    public function test_pages_show_registry_history_and_keep_legacy_and_simulated_proofs_distinct(): void
    {
        [$registration, $user] = $this->registration();
        $this->fakeRead($registration, $this->evidence($registration));
        app(PayrollVerificationService::class)->verify($registration, $user);
        $legacy = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $anchor = $this->historicalSimulatedAnchor($legacy, $user);
        $this->actingAs($user)->get(route('blockchain.index'))->assertOk()
            ->assertSee('Payroll registrations')->assertSee('Legacy anchors')->assertSee('Simulated local proof')
            ->assertSee(route('payroll-registrations.show', $registration))->assertSee(route('blockchain-anchors.show', $anchor));
        $this->get(route('payroll-runs.show', $registration->payrollRun))->assertOk()
            ->assertSee('View registration &amp; verify', false)->assertDontSee('Anchor finalized payroll');
        $this->get(route('payroll-registrations.show', $registration))->assertOk()
            ->assertSeeInOrder(['Payroll integrity', 'Match', 'Registration evidence', 'Verification history'])
            ->assertSee('Verify current payroll')->assertSee('Each result describes that check only.')
            ->assertDontSee('Confidential Employee Alpha')->assertDontSee(str_repeat('t', 40));
    }

    public function test_registry_headings_escape_payroll_snapshot_text(): void
    {
        [$registration, $user] = $this->registration();
        $snapshot = $registration->period_snapshot;
        $snapshot['period_name'] = '<script>alert("unsafe")</script>';
        $registration->update(['period_snapshot' => $snapshot]);

        $this->actingAs($user)->get(route('payroll-registrations.show', $registration))->assertOk()
            ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert("unsafe")</script>', false);
        Http::assertNothingSent();
    }

    public function test_legacy_anchor_heading_escapes_the_payroll_period_name(): void
    {
        [, $user] = $this->registration();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $run->payrollPeriod->update(['period_name' => '<img src=x onerror=alert(1)>']);
        $anchor = $this->historicalSimulatedAnchor($run, $user);

        $this->actingAs($user)->get(route('blockchain-anchors.show', $anchor))->assertOk()
            ->assertSee('&lt;img src=x', false)->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    public function test_verification_history_is_paginated_and_latest_result_is_not_limited_to_the_current_page(): void
    {
        [$registration, $user] = $this->registration();
        for ($index = 0; $index < 16; $index++) {
            Verification::create(['registration_id' => $registration->getKey(), 'verified_by' => $user->getKey(),
                'captured_hash' => $registration->payload_hash, 'verification_result' => $index === 15 ? 'UNAVAILABLE' : 'MATCH',
                'remarks' => 'Synthetic history entry '.$index, 'verified_at' => now()]);
        }

        $this->actingAs($user)->get(route('payroll-registrations.show', [$registration, 'page' => 2]))->assertOk()
            ->assertViewHas('checks', fn ($checks): bool => $checks->count() === 1 && $checks->total() === 16)
            ->assertSeeInOrder(['Payroll integrity', 'Unavailable', 'Verification history']);
        Http::assertNothingSent();
    }

    /** @return array{PayrollRegistration, User} */
    private function registration(?Closure $prepareDetail = null): array
    {
        $this->seed(RolePermissionSeeder::class);
        $creator = User::factory()->create();
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::ADMIN)->value('role_id'), ['assigned_at' => now()]);
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_APPROVED, 'created_by' => $creator->getKey(),
            'submitted_by' => $creator->getKey(), 'submitted_at' => now()->subMinutes(2)]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employee_name_snapshot' => 'Confidential Employee Alpha',
            'department_snapshot' => 'Finance', 'position_snapshot' => 'Payroll Clerk', 'salary_rate_snapshot' => '45000.00',
            'pay_basis_snapshot' => 'MONTHLY', 'input_recorded_by' => $creator->getKey()]);
        $prepareDetail?->__invoke($detail);
        PayrollApproval::create(['payroll_run_id' => $run->getKey(), 'approval_step' => 'APPROVED', 'action' => 'APPROVED',
            'acted_by' => $user->getKey(), 'acted_at' => now()->subMinute(), 'remarks' => null]);
        app(PayrollApprovalService::class)->finalize($run, $user);

        return [$run->registration()->firstOrFail(), $user];
    }

    private function path(PayrollRegistration $registration): string
    {
        return 'http://bridge.test/registrations/'.$registration->organization_id.'/'.$registration->payroll_reference.'/'.$registration->revision;
    }

    /** @param array<string, mixed>|null $evidence */
    private function fakeRead(PayrollRegistration $registration, ?array $evidence, ?string $status = null): void
    {
        Http::fake([$this->path($registration) => Http::response($this->readBody($registration, $evidence, $status))]);
    }

    /** @param array<string, mixed>|null $evidence
     * @return array<string, mixed>
     */
    private function readBody(PayrollRegistration $registration, ?array $evidence, ?string $status = null): array
    {
        $network = $registration->target_network;
        unset($network['bridge_url']);

        return ['network' => $network, 'registration' => $evidence, 'submission' => $status === null ? null : [
            'organization_id' => $registration->organization_id, 'payroll_reference' => $registration->payroll_reference,
            'revision' => $registration->revision, 'status' => $status,
        ]];
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

    private function historicalSimulatedAnchor(PayrollRun $run, User $user): BlockchainAnchor
    {
        return BlockchainAnchor::create([
            'payroll_run_id' => $run->getKey(),
            'payload_hash' => app(BlockchainAnchorService::class)->hashPayrollRun($run),
            'hash_algorithm' => 'SHA-256', 'network_name' => 'simulated:QA',
            'transaction_id' => '0x'.str_repeat('a', 64), 'contract_address' => null,
            'anchor_status' => 'ANCHORED', 'anchored_by' => $user->getKey(), 'anchored_at' => now(),
        ]);
    }
}
