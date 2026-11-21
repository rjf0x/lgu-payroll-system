<?php

namespace Tests\Feature;

use App\Models\BlockchainAnchor;
use App\Models\BlockchainVerificationLog;
use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\Blockchain\BlockchainDriver;
use App\Services\Blockchain\OnChainBlockchainDriver;
use App\Services\BlockchainAnchorService;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BlockchainAnchorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['blockchain.driver' => 'simulated']);

        $this->seed([
            RolePermissionSeeder::class,
            EmploymentTypeSeeder::class,
            EarningTypeSeeder::class,
            DeductionTypeSeeder::class,
            StatutoryConfigSeeder::class,
        ]);

        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->department_id, 'salary_grade' => 15]);
        $type = EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->firstOrFail();
        $record = EmploymentRecord::factory()->create([
            'employee_id' => Employee::factory()->create()->employee_id,
            'department_id' => $department->department_id,
            'position_id' => $position->position_id,
            'employment_type_id' => $type->employment_type_id,
        ]);

        $schedule = SalarySchedule::factory()->create(['effective_from' => '2024-01-01', 'status' => 'ACTIVE']);
        $step = SalaryGradeStep::factory()->create([
            'salary_schedule_id' => $schedule->salary_schedule_id,
            'grade_number' => 15,
            'step_number' => 1,
            'monthly_salary' => 45000,
        ]);

        CompensationProfile::factory()->create([
            'employment_id' => $record->employment_id,
            'grade_step_id' => $step->grade_step_id,
            'basic_rate' => null,
            'effective_from' => '2024-01-01',
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function finalizedRun(): PayrollRun
    {
        $period = PayrollPeriod::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'pay_date' => '2025-01-31',
        ]);

        $run = PayrollRun::factory()->create([
            'payroll_period_id' => $period->payroll_period_id,
            'created_by' => User::factory()->create()->user_id,
        ]);

        app(PayrollComputationService::class)->computeRun($run);
        foreach ($run->details as $detail) {
            $detail->update(['payable_days' => 22, 'input_reference' => 'Approved test summary', 'input_confirmed_at' => now()]);
        }
        app(PayrollComputationService::class)->computeRun($run);
        $run->update(['status' => PayrollRun::STATUS_FINALIZED]);

        return $run->fresh();
    }

    public function test_the_payroll_hash_is_deterministic(): void
    {
        $run = $this->finalizedRun();
        $service = app(BlockchainAnchorService::class);

        $this->assertSame($service->hashPayrollRun($run), $service->hashPayrollRun($run));
    }

    public function test_only_a_finalized_run_can_be_anchored(): void
    {
        $run = $this->finalizedRun();
        $run->update(['status' => PayrollRun::STATUS_APPROVED]);
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)
            ->post(route('payroll-runs.anchor', $run))
            ->assertStatus(422);

        $this->assertDatabaseCount('blockchain_anchors', 0);
    }

    public function test_anchoring_stores_a_hash_and_transaction_metadata(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)
            ->post(route('payroll-runs.anchor', $run))
            ->assertRedirect();

        $anchor = BlockchainAnchor::firstOrFail();
        $this->assertSame('ANCHORED', $anchor->anchor_status);
        $this->assertSame('SHA-256', $anchor->hash_algorithm);
        $this->assertSame(64, strlen($anchor->payload_hash));
        $this->assertNotNull($anchor->transaction_id);
        $this->assertSame($officer->user_id, $anchor->anchored_by);
    }

    public function test_verification_matches_an_untampered_run(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)->post(route('payroll-runs.anchor', $run));
        $anchor = BlockchainAnchor::firstOrFail();

        $this->actingAs($officer)
            ->post(route('blockchain-anchors.verify', $anchor))
            ->assertRedirect();

        $this->assertDatabaseHas('blockchain_verification_logs', [
            'anchor_id' => $anchor->anchor_id,
            'verification_result' => BlockchainVerificationLog::RESULT_MATCH,
        ]);
    }

    public function test_verification_detects_tampering(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)->post(route('payroll-runs.anchor', $run));
        $anchor = BlockchainAnchor::firstOrFail();

        // Tamper with a stored payroll detail after anchoring.
        PayrollDetail::firstOrFail()->update(['net_pay' => 99999.99]);

        $this->actingAs($officer)
            ->post(route('blockchain-anchors.verify', $anchor))
            ->assertRedirect();

        $this->assertDatabaseHas('blockchain_verification_logs', [
            'anchor_id' => $anchor->anchor_id,
            'verification_result' => BlockchainVerificationLog::RESULT_MISMATCH,
        ]);
    }

    public function test_viewer_cannot_access_blockchain_verification(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->actingAs($viewer)->get(route('blockchain.index'))->assertForbidden();
    }

    public function test_legacy_network_submission_is_rejected_even_when_a_network_driver_is_injected(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $driver = $this->mock(OnChainBlockchainDriver::class);
        $driver->shouldNotReceive('anchor');
        $this->app->instance(BlockchainDriver::class, $driver);

        $this->actingAs($officer)->postJson(route('payroll-runs.anchor', $run))->assertUnprocessable()->assertSee('Legacy network submission is disabled');
        $this->actingAs($officer)->postJson(route('payroll-runs.anchor', $run))->assertUnprocessable();

        $this->assertDatabaseCount('blockchain_anchors', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYROLL_ANCHOR_SUBMITTED']);
    }

    private function pendingBesuAnchor(PayrollRun $run, User $user): BlockchainAnchor
    {
        config(['blockchain.besu.chain_id' => 1337]);

        return BlockchainAnchor::create([
            'payroll_run_id' => $run->getKey(),
            'payload_hash' => app(BlockchainAnchorService::class)->hashPayrollRun($run),
            'hash_algorithm' => 'SHA-256', 'network_name' => 'besu:1337',
            'transaction_id' => '0x'.str_repeat('a', 64),
            'contract_address' => '0x'.str_repeat('1', 40),
            'anchor_status' => 'PENDING', 'anchored_by' => $user->getKey(),
        ]);
    }

    public function test_confirming_besu_transaction_updates_anchor_and_records_match(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $anchor = $this->pendingBesuAnchor($run, $officer);
        $driver = $this->mock(OnChainBlockchainDriver::class);
        $driver->shouldReceive('receipt')->once()->with($anchor->transaction_id, $anchor->contract_address)->andReturn([
            'payload_hash' => $anchor->payload_hash, 'block_number' => 42, 'successful' => true,
        ]);
        $this->app->instance(BlockchainDriver::class, $driver);

        $this->actingAs($officer)->post(route('blockchain-anchors.verify', $anchor))->assertRedirect();

        $this->assertDatabaseHas('blockchain_anchors', ['anchor_id' => $anchor->getKey(), 'anchor_status' => 'ANCHORED', 'block_number' => 42]);
        $this->assertDatabaseHas('blockchain_verification_logs', ['anchor_id' => $anchor->getKey(), 'verification_result' => 'MATCH']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_ANCHORED']);
    }

    public function test_pending_besu_transaction_returns_409_without_a_verification_log(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $anchor = $this->pendingBesuAnchor($run, $officer);
        $driver = $this->mock(OnChainBlockchainDriver::class);
        $driver->shouldReceive('receipt')->once()->andReturnNull();
        $this->app->instance(BlockchainDriver::class, $driver);

        $this->actingAs($officer)->post(route('blockchain-anchors.verify', $anchor))->assertStatus(409);

        $this->assertDatabaseCount('blockchain_verification_logs', 0);
        $this->assertDatabaseHas('blockchain_anchors', ['anchor_id' => $anchor->getKey(), 'anchor_status' => 'PENDING']);
    }

    public function test_failed_besu_transaction_returns_422_and_records_failed_status(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $anchor = $this->pendingBesuAnchor($run, $officer);
        $driver = $this->mock(OnChainBlockchainDriver::class);
        $driver->shouldReceive('receipt')->once()->andReturn([
            'payload_hash' => $anchor->payload_hash, 'block_number' => 42, 'successful' => false,
        ]);
        $this->app->instance(BlockchainDriver::class, $driver);

        $this->actingAs($officer)->post(route('blockchain-anchors.verify', $anchor))->assertUnprocessable();

        $this->assertDatabaseHas('blockchain_anchors', ['anchor_id' => $anchor->getKey(), 'anchor_status' => 'FAILED']);
        $this->assertDatabaseCount('blockchain_verification_logs', 0);
    }

    public function test_besu_verification_detects_tampering_even_when_local_hash_is_also_changed(): void
    {
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $anchor = $this->pendingBesuAnchor($run, $officer);
        $originalHash = $anchor->payload_hash;
        PayrollDetail::firstOrFail()->update(['net_pay' => 99999.99]);
        $anchor->update(['payload_hash' => app(BlockchainAnchorService::class)->hashPayrollRun($run)]);
        $driver = $this->mock(OnChainBlockchainDriver::class);
        $driver->shouldReceive('receipt')->once()->andReturn([
            'payload_hash' => $originalHash, 'block_number' => 42, 'successful' => true,
        ]);
        $this->app->instance(BlockchainDriver::class, $driver);

        $this->actingAs($officer)->post(route('blockchain-anchors.verify', $anchor))->assertRedirect();

        $this->assertDatabaseHas('blockchain_verification_logs', [
            'anchor_id' => $anchor->getKey(), 'stored_hash' => $originalHash, 'verification_result' => 'MISMATCH',
        ]);
    }

    public function test_live_legacy_settings_cannot_reenable_network_submission(): void
    {
        config(['blockchain.driver' => 'besu', 'blockchain.besu.live_tests' => true]);
        Http::preventStrayRequests();
        Http::fake();
        $run = $this->finalizedRun();
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)->postJson(route('payroll-runs.anchor', $run))->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('blockchain_anchors', 0);
        $this->assertSame('FINALIZED', $run->fresh()->status);
    }
}
