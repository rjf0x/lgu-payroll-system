<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmployeeEarningAssignment;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollAssignmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->freezeTime();
        $this->seed([RolePermissionSeeder::class, DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($officer);
    }

    private function detail(): PayrollDetail
    {
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'created_by' => auth()->id()]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'manual_adjustments' => false]);
        CompensationProfile::factory()->create(['employment_id' => $detail->employment_id, 'basic_rate' => 45000, 'effective_from' => '2024-01-01']);

        return $detail;
    }

    /** @param array<string, mixed> $attributes */
    private function earningAssignment(PayrollDetail $detail, EarningType $type, array $attributes = []): EmployeeEarningAssignment
    {
        return EmployeeEarningAssignment::create([
            'employment_id' => $detail->employment_id, 'earning_type_id' => $type->getKey(),
            'amount' => 500, 'frequency' => 'MONTHLY', 'effective_from' => '2024-01-01', ...$attributes,
        ]);
    }

    /** @param array<string, mixed> $attributes */
    private function deductionAssignment(PayrollDetail $detail, DeductionType $type, array $attributes = []): EmployeeDeductionAssignment
    {
        return EmployeeDeductionAssignment::create([
            'employment_id' => $detail->employment_id, 'deduction_type_id' => $type->getKey(),
            'amount' => 100, 'status' => 'ACTIVE', 'effective_from' => '2024-01-01', ...$attributes,
        ]);
    }

    private function compute(PayrollDetail $detail): PayrollDetail
    {
        return app(PayrollComputationService::class)->computeDetail($detail->payrollRun, $detail->employmentRecord, $detail->payrollRun->payrollPeriod)->fresh();
    }

    public function test_automatic_sources_honor_active_status_dates_and_percentage_amounts(): void
    {
        $detail = $this->detail();
        $fixed = EarningType::factory()->create(['is_taxable' => false]);
        $percentage = EarningType::factory()->create(['is_taxable' => false]);
        $this->earningAssignment($detail, $fixed, ['effective_from' => '2026-01-31', 'effective_to' => '2026-01-31']);
        $this->earningAssignment($detail, $percentage, ['amount' => null, 'percentage' => 2]);
        $this->earningAssignment($detail, EarningType::factory()->create(['status' => 'INACTIVE']), ['frequency' => 'ONE_TIME']);
        $this->earningAssignment($detail, EarningType::factory()->create(), ['effective_to' => '2026-01-30']);
        $this->earningAssignment($detail, EarningType::factory()->create(), ['effective_from' => '2026-02-01']);
        $active = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LOAN']);
        $this->deductionAssignment($detail, $active, ['amount' => null, 'percentage' => 1, 'effective_from' => '2026-01-31', 'effective_to' => '2026-01-31']);
        $this->deductionAssignment($detail, DeductionType::factory()->create(['is_statutory' => false]), ['status' => 'INACTIVE']);
        $this->deductionAssignment($detail, DeductionType::factory()->create(['status' => 'INACTIVE', 'is_statutory' => false]));
        $this->deductionAssignment($detail, DeductionType::factory()->create(['is_statutory' => false]), ['effective_to' => '2026-01-30']);
        $this->deductionAssignment($detail, DeductionType::factory()->create(['is_statutory' => false]), ['effective_from' => '2026-02-01']);
        $this->deductionAssignment($detail, DeductionType::where('deduction_name', 'GSIS Contribution')->firstOrFail());
        $this->deductionAssignment($detail, DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LBP']));

        $detail = $this->compute($detail);

        $this->assertSame(['500.00', '900.00'], $detail->earnings()->orderBy('amount')->pluck('amount')->all());
        $this->assertSame('1400.00', $detail->total_earnings);
        $this->assertSame('450.00', $detail->deductions()->whereHas('deductionType', fn ($query) => $query->where('is_statutory', false))->sole()->amount);
        $this->assertSame(1, $detail->deductions()->where('deduction_name_snapshot', 'GSIS Contribution')->count());
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    /** @return array<string, array{string, bool}> */
    public static function unsupportedEarnings(): array
    {
        return ['annual' => ['ANNUAL', true], 'one-time' => ['ONE_TIME', true], 'nonrecurring monthly' => ['MONTHLY', false]];
    }

    #[DataProvider('unsupportedEarnings')]
    public function test_unsupported_assignments_are_rejected_at_the_request_boundary(string $frequency, bool $recurring): void
    {
        $detail = $this->detail();
        $type = EarningType::factory()->create(['is_recurring' => $recurring]);
        $this->postJson(route('employee-earning-assignments.store'), [
            'employment_id' => $detail->employment_id, 'earning_type_id' => $type->getKey(),
            'amount' => 500, 'frequency' => $frequency, 'effective_from' => '2026-01-01',
        ])->assertUnprocessable()->assertJsonValidationErrors($recurring ? 'frequency' : 'earning_type_id');

        $this->assertDatabaseCount('employee_earning_assignments', 0);
    }

    #[DataProvider('unsupportedEarnings')]
    public function test_legacy_unsupported_assignments_block_computation_and_submission_even_for_manual_rows(string $frequency, bool $recurring): void
    {
        $detail = $this->compute($this->detail());
        $type = EarningType::factory()->create(['is_recurring' => $recurring]);
        $this->earningAssignment($detail, $type, ['frequency' => $frequency]);
        $detail->update(['manual_adjustments' => true]);
        $before = $detail->fresh()->getAttributes();
        $deductions = $detail->deductions()->get()->toArray();

        $this->postJson(route('payroll-runs.compute', $detail->payrollRun))
            ->assertUnprocessable()->assertJsonValidationErrors('financial.'.$detail->getKey());
        $response = $this->postJson(route('payroll-runs.submit', $detail->payrollRun))
            ->assertUnprocessable()->assertJsonValidationErrors('financial.'.$detail->getKey());

        $this->assertStringContainsString('Unsupported automatic earning assignment', $response->getContent());
        $this->assertStringContainsString($detail->employee_number_snapshot, $response->getContent());
        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame($deductions, $detail->deductions()->get()->toArray());
        $this->assertSame('DRAFT', $detail->payrollRun->fresh()->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_VALIDATION_BLOCKED']);
    }

    public function test_expired_and_future_unsupported_assignments_do_not_block_current_payroll(): void
    {
        $detail = $this->detail();
        $this->earningAssignment($detail, EarningType::factory()->create(), ['frequency' => 'ANNUAL', 'effective_to' => '2026-01-30']);
        $this->earningAssignment($detail, EarningType::factory()->create(['is_recurring' => false]), ['frequency' => 'ONE_TIME', 'effective_from' => '2026-02-01']);

        $detail = $this->compute($detail);

        $this->assertSame(0, $detail->earnings()->count());
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
    }

    public function test_disabling_an_assignment_requires_recomputation_and_removes_the_automatic_deduction(): void
    {
        $detail = $this->detail();
        $type = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LOAN']);
        $assignment = $this->deductionAssignment($detail, $type);
        $detail = $this->compute($detail);
        $this->assertSame('100.00', $detail->deductions()->where('deduction_type_id', $type->getKey())->sole()->amount);
        $assignment->update(['status' => 'INACTIVE']);

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertUnprocessable()->assertSee('calculation is missing or stale');
        $detail = $this->compute($detail);

        $this->assertFalse($detail->deductions()->where('deduction_type_id', $type->getKey())->exists());
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
    }

    public function test_assignment_effectivity_uses_the_employment_end_date_for_midmonth_departures(): void
    {
        $detail = $this->detail();
        $detail->employmentRecord->update(['end_date' => '2026-01-15']);
        $type = EarningType::factory()->create(['is_taxable' => false]);
        $this->earningAssignment($detail, $type, ['effective_to' => '2026-01-15']);
        $this->earningAssignment($detail, EarningType::factory()->create(), ['frequency' => 'ANNUAL', 'effective_from' => '2026-01-16']);

        $detail = $this->compute($detail);

        $this->assertSame($type->getKey(), $detail->earnings()->sole()->earning_type_id);
        $this->assertSame('500.00', $detail->total_earnings);
    }

    public function test_monthly_assignment_can_be_created_and_inactive_type_cannot_be_assigned(): void
    {
        $detail = $this->detail();
        $type = EarningType::factory()->create();
        $input = ['employment_id' => $detail->employment_id, 'earning_type_id' => $type->getKey(), 'amount' => 500, 'frequency' => 'MONTHLY', 'effective_from' => '2026-01-01'];
        $this->postJson(route('employee-earning-assignments.store'), $input)->assertRedirect();
        $assignment = EmployeeEarningAssignment::sole();
        $type->update(['status' => 'INACTIVE']);

        $this->putJson(route('employee-earning-assignments.update', $assignment), $input)
            ->assertUnprocessable()->assertJsonValidationErrors('earning_type_id');
        $this->assertSame('500.00', $assignment->fresh()->amount);
    }

    public function test_legacy_hourly_profile_cannot_be_saved_computed_or_submitted(): void
    {
        $detail = $this->compute($this->detail());
        $profile = CompensationProfile::where('employment_id', $detail->employment_id)->sole();
        $input = ['basis' => 'DIRECT_RATE', 'employment_id' => $detail->employment_id, 'pay_basis' => 'HOURLY', 'basic_rate' => 250, 'service_fraction' => 1, 'effective_from' => '2024-01-01'];
        $this->postJson(route('compensation-profiles.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('pay_basis');
        $this->putJson(route('compensation-profiles.update', $profile), $input)->assertUnprocessable()->assertJsonValidationErrors('pay_basis');
        $this->assertSame('MONTHLY', $profile->fresh()->pay_basis);
        $profile->update(['pay_basis' => 'HOURLY']);
        $before = $detail->refresh()->getAttributes();

        $this->postJson(route('payroll-runs.compute', $detail->payrollRun))->assertUnprocessable()->assertSee('Unsupported pay basis');
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertUnprocessable()->assertSee('invalid salary rate, pay basis');

        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame('DRAFT', $detail->payrollRun->fresh()->status);
    }

    /** @return array<string, array{string, float, float, string}> */
    public static function supportedPayBases(): array
    {
        return ['monthly' => ['MONTHLY', 45000, 1, '22500.00'], 'daily' => ['DAILY', 500, 1, '5500.00'], 'proportional monthly' => ['PROPORTIONAL_MONTHLY', 45000, .5, '11250.00']];
    }

    #[DataProvider('supportedPayBases')]
    public function test_supported_pay_bases_keep_their_day_based_formulas(string $basis, float $rate, float $fraction, string $expected): void
    {
        $detail = $this->detail();
        CompensationProfile::where('employment_id', $detail->employment_id)->update(['pay_basis' => $basis, 'basic_rate' => $rate, 'service_fraction' => $fraction]);
        $detail->update(['payable_days' => 11]);

        $detail = $this->compute($detail);

        $this->assertSame($expected, $detail->basic_pay);
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
    }

    public function test_manual_exceptional_earnings_are_allowed_without_an_automatic_assignment(): void
    {
        $detail = $this->detail();
        $type = EarningType::factory()->create(['is_recurring' => false, 'is_taxable' => false]);

        $this->putJson(route('payroll-details.update', [$detail->payrollRun, $detail]), [
            'payable_days' => 22, 'inputs_confirmed' => 1, 'earnings' => [$type->getKey() => 700],
        ])->assertRedirect();
        $detail->refresh();
        $this->assertSame('700.00', $detail->earnings()->sole()->amount);
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
    }

    public function test_next_period_copy_filters_disabled_expired_and_nonrecurring_sources_without_changing_history(): void
    {
        $detail = $this->detail();
        $detail->payrollRun->update(['status' => 'FINALIZED', 'finalized_at' => '2026-01-31 12:00:00']);
        $retained = EarningType::factory()->create();
        $boundary = EarningType::factory()->create();
        $expired = EarningType::factory()->create();
        $annual = EarningType::factory()->create();
        $this->earningAssignment($detail, $boundary, ['effective_from' => '2026-02-28', 'effective_to' => '2026-02-28']);
        $this->earningAssignment($detail, $expired, ['effective_to' => '2026-01-31']);
        $this->earningAssignment($detail, $annual, ['frequency' => 'ANNUAL']);
        foreach ([$retained, $boundary, $expired, $annual, EarningType::factory()->create(['status' => 'INACTIVE']), EarningType::factory()->create(['is_recurring' => false])] as $type) {
            $detail->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => $type->earning_name, 'amount' => 500, 'is_taxable' => false]);
        }
        $keptDeduction = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LOAN']);
        $disabled = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LOAN']);
        $ended = DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LOAN']);
        $this->deductionAssignment($detail, $disabled, ['status' => 'INACTIVE']);
        $this->deductionAssignment($detail, $ended, ['effective_to' => '2026-01-31']);
        foreach ([$keptDeduction, $disabled, $ended, DeductionType::factory()->create(['status' => 'INACTIVE', 'is_statutory' => false])] as $type) {
            $detail->deductions()->create(['deduction_type_id' => $type->getKey(), 'deduction_name_snapshot' => $type->deduction_name, 'amount' => 100]);
        }
        $before = $detail->refresh()->getAttributes();
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);

        $this->postJson(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $copy = PayrollRun::where('payroll_period_id', $period->getKey())->sole()->details()->sole();
        $this->assertEqualsCanonicalizing([$retained->getKey(), $boundary->getKey()], $copy->earnings()->pluck('earning_type_id')->all());
        $this->assertSame([$keptDeduction->getKey()], $copy->deductions()->pluck('deduction_type_id')->all());
        $this->assertNull($copy->input_confirmed_at);
        $this->assertNull($copy->computation_fingerprint);
        $this->assertSame('INPUT_REQUIRED', $copy->payroll_status);
        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame(6, $detail->earnings()->count());
        $this->assertSame(4, $detail->deductions()->count());
    }

    public function test_forms_hide_new_unsupported_choices_and_label_legacy_values_for_correction(): void
    {
        $detail = $this->detail();
        $type = EarningType::factory()->create(['is_recurring' => false]);
        $assignment = $this->earningAssignment($detail, $type, ['frequency' => 'ANNUAL']);
        $profile = CompensationProfile::where('employment_id', $detail->employment_id)->sole();
        $profile->update(['pay_basis' => 'HOURLY']);

        $this->get(route('employee-earning-assignments.create'))->assertOk()->assertDontSee('value="ANNUAL"', false)->assertDontSee('value="ONE_TIME"', false);
        $this->get(route('employee-earning-assignments.edit', $assignment))->assertOk()->assertSee('unsupported; select Monthly')->assertSee('unsupported for automatic assignment');
        $this->get(route('compensation-profiles.create'))->assertOk()->assertDontSee('value="HOURLY"', false);
        $this->get(route('compensation-profiles.edit', $profile))->assertOk()->assertSee('unsupported; select a supported pay basis');
    }
}
