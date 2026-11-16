<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\EarningType;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollDuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    private function officer(): User
    {
        $this->freezeTime();
        $this->seed([RolePermissionSeeder::class, DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    private function reviewer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::APPROVER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function createRun(?PayrollPeriod $period = null, string $type = 'REGULAR'): PayrollRun
    {
        $period ??= PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

        return PayrollRun::factory()->create([
            'payroll_period_id' => $period->getKey(), 'run_type' => $type, 'created_by' => auth()->id(),
            'run_number' => (int) $period->runs()->max('run_number') + 1,
            'purpose' => $type === 'REGULAR' ? null : 'Authorized additional compensation test',
        ]);
    }

    private function detail(PayrollRun $run, ?EmploymentRecord $employment = null): PayrollDetail
    {
        $employment ??= EmploymentRecord::factory()->create();
        if (! $employment->compensationProfiles()->exists()) {
            CompensationProfile::factory()->create(['employment_id' => $employment->getKey(), 'basic_rate' => 45000, 'effective_from' => '2024-01-01']);
        }
        $detail = PayrollDetail::factory()->create([
            'payroll_run_id' => $run->getKey(), 'employment_id' => $employment->getKey(), 'manual_adjustments' => true,
            'basic_pay' => $run->run_type === 'REGULAR' ? 45000 : 0,
            'payable_days' => $run->run_type === 'REGULAR' ? 22 : 0,
            'input_recorded_by' => auth()->id(),
            'coverage_start' => $run->payrollPeriod->start_date, 'coverage_end' => $run->payrollPeriod->end_date,
            'compensation_reference' => $run->run_type === 'REGULAR' ? null : 'AUTH-'.$run->getKey(),
            'compensation_reason' => 'Additional entitlement authorized by supporting schedule',
            'accounting_basis' => 'Accounting schedule and confirmed policy',
        ]);

        return app(PayrollComputationService::class)->computeDetail($run, $employment, $run->payrollPeriod)->fresh();
    }

    private function earning(PayrollDetail $detail, ?EarningType $type = null, int $amount = 2500): void
    {
        $type ??= EarningType::factory()->create(['is_taxable' => false]);
        $detail->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => $type->earning_name, 'amount' => $amount, 'is_taxable' => $type->is_taxable]);
    }

    private function confirmedPolicy(string $type): void
    {
        config(['payroll.non_regular.'.$type => ['policy_reference' => 'MAO-TEST-ONLY', 'calculation_method' => 'existing_monthly']]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function duplicateTransitions(): array
    {
        return [
            'pending blocks submission' => ['PENDING_APPROVAL', 'DRAFT', 'submit'],
            'approved blocks submission' => ['APPROVED', 'DRAFT', 'submit'],
            'finalized blocks submission' => ['FINALIZED', 'DRAFT', 'submit'],
            'finalized blocks approval' => ['FINALIZED', 'PENDING_APPROVAL', 'approve'],
            'finalized blocks finalization' => ['FINALIZED', 'APPROVED', 'finalize'],
        ];
    }

    #[DataProvider('duplicateTransitions')]
    public function test_regular_duplicates_cannot_cross_financial_transitions(string $conflictStatus, string $targetStatus, string $action): void
    {
        $this->officer();
        $source = $this->createRun();
        $original = $this->detail($source);
        $target = $this->createRun($source->payrollPeriod);
        $copy = $this->detail($target, $original->employmentRecord);
        $source->update(['status' => $conflictStatus]);
        $target->update(['status' => $targetStatus]);
        $originalAttributes = $original->getAttributes();
        if ($action !== 'submit') {
            $this->actingAs($this->reviewer());
        }

        $response = $this->postJson(route('payroll-runs.'.$action, $target));

        $response->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$copy->getKey());
        $message = implode(' ', $response->json('errors')['duplicates.'.$copy->getKey()]);
        $this->assertStringContainsString('basic salary', $message);
        $this->assertStringContainsString('run #'.$source->getKey(), $message);
        $this->assertStringContainsString($source->payrollPeriod->period_name, $message);
        $this->assertStringContainsString($copy->employee_number_snapshot, $message);
        $this->assertSame($targetStatus, $target->fresh()->status);
        $this->assertSame($originalAttributes, $original->fresh()->getAttributes());
        $this->assertDatabaseMissing('payroll_approvals', ['payroll_run_id' => $target->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_VALIDATION_BLOCKED', 'record_id' => (string) $target->getKey()]);
    }

    public function test_overlapping_periods_and_different_employments_cannot_hide_regular_duplicates(): void
    {
        $this->officer();
        $source = $this->createRun();
        $original = $this->detail($source);
        $employment = EmploymentRecord::factory()->create(['employee_id' => $original->employee_id]);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-15', 'end_date' => '2026-02-14']);
        $target = $this->createRun($period);
        $copy = $this->detail($target, $employment);
        $source->update(['status' => 'FINALIZED']);

        $this->postJson(route('payroll-runs.submit', $target))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$copy->getKey());
        $this->postJson(route('payroll-runs.compute', $target))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$copy->getKey());
        $this->assertSame('DRAFT', $target->fresh()->status);
    }

    public function test_multiple_overlapping_employments_in_one_run_are_blocked(): void
    {
        $this->officer();
        $run = $this->createRun();
        $detail = $this->detail($run);
        $employment = EmploymentRecord::factory()->create(['employee_id' => $detail->employee_id]);
        PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employment_id' => $employment->getKey()]);

        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$detail->getKey());
    }

    public function test_adjacent_periods_and_draft_corrections_remain_possible(): void
    {
        $officer = $this->officer();
        $first = $this->createRun();
        $detail = $this->detail($first);
        $draft = $this->createRun($first->payrollPeriod);
        $this->detail($draft, $detail->employmentRecord);
        app(PayrollApprovalService::class)->submit($first, $officer);
        $reviewer = $this->reviewer();
        app(PayrollApprovalService::class)->returnForCorrection($first, $reviewer, 'Correct source inputs');

        $this->postJson(route('payroll-runs.submit', $draft))->assertRedirect();
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);
        $next = $this->createRun($period);
        $this->detail($next, $detail->employmentRecord);
        $this->postJson(route('payroll-runs.submit', $next))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $next->fresh()->status);
    }

    public function test_supplemental_computation_never_seeds_salary_or_all_employees(): void
    {
        $this->officer();
        $regular = $this->createRun();
        $employee = $this->detail($regular);
        $previous = $this->createRun($regular->payrollPeriod, 'SUPPLEMENTAL');
        $previousDetail = $this->detail($previous, $employee->employmentRecord);
        $this->earning($previousDetail);
        $previous->update(['status' => 'FINALIZED']);
        $this->postJson(route('payroll-runs.store'), [
            'payroll_period_id' => $regular->payroll_period_id, 'run_type' => 'SUPPLEMENTAL', 'purpose' => 'Separate authorized additional earnings',
        ])->assertRedirect();
        $run = PayrollRun::where('payroll_period_id', $regular->payroll_period_id)->orderByDesc('run_number')->firstOrFail();
        $this->assertSame(0, $run->details()->count());

        $this->postJson(route('payroll-runs.compute', $run))->assertRedirect();
        $this->assertSame(0, $run->details()->count());
        $detail = $this->detail($run, $employee->employmentRecord);
        $this->get(route('payroll-details.edit', [$run, $detail]))->assertOk()
            ->assertSee('Supporting entitlement reference')->assertSee('Accounting policy confirmation required');
        $this->assertSame('0.00', $detail->basic_pay);
        $this->assertSame(0, $detail->earnings()->count());
        $this->earning($detail);
        app(PayrollComputationService::class)->computeDetail($run, $detail->employmentRecord, $run->payrollPeriod);

        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable()->assertJsonValidationErrors('financial.policy');
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Municipal Accounting Office policy confirmation');
        $this->assertSame('DRAFT', $run->fresh()->status);
        $this->assertSame('2500.00', $detail->fresh()->gross_pay);
        $this->assertSame('POLICY_REQUIRED', $detail->fresh()->payroll_status);
    }

    public function test_distinct_authorized_supplemental_entitlements_can_finalize_under_confirmed_policy(): void
    {
        $officer = $this->officer();
        $this->confirmedPolicy('SUPPLEMENTAL');
        $regular = $this->createRun();
        $original = $this->detail($regular);
        $type = EarningType::factory()->create(['is_taxable' => false]);
        $this->earning($original, $type);
        $regular->update(['status' => 'FINALIZED']);
        $sourceValues = $original->fresh()->getAttributes();
        $run = $this->createRun($regular->payrollPeriod, 'SUPPLEMENTAL');
        $detail = $this->detail($run, $original->employmentRecord);
        $this->earning($detail, $type, 12000);
        $detail->update(['overlap_justification' => 'Separate allowance entitlement under AUTH-2, verified against regular payment']);
        app(PayrollComputationService::class)->computeDetail($run, $detail->employmentRecord, $run->payrollPeriod);

        $this->postJson(route('payroll-runs.submit', $run))->assertRedirect();
        $reviewer = $this->reviewer();
        $this->actingAs($reviewer)->postJson(route('payroll-runs.approve', $run))->assertRedirect();
        $this->postJson(route('payroll-runs.finalize', $run))->assertRedirect();

        $this->assertSame('FINALIZED', $run->fresh()->status);
        $this->assertSame('0.00', $detail->fresh()->basic_pay);
        $this->assertSame('12000.00', $detail->fresh()->gross_pay);
        $this->assertSame($sourceValues, $original->fresh()->getAttributes());
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_DISTINCT_ENTITLEMENT', 'record_id' => (string) $detail->getKey()]);
    }

    public function test_reusing_supplemental_reference_with_a_changed_amount_is_blocked(): void
    {
        $this->officer();
        $this->confirmedPolicy('SUPPLEMENTAL');
        $source = $this->createRun(type: 'SUPPLEMENTAL');
        $first = $this->detail($source);
        $type = EarningType::factory()->create(['is_taxable' => false]);
        $this->earning($first, $type);
        $target = $this->createRun($source->payrollPeriod, 'SUPPLEMENTAL');
        $copy = $this->detail($target, $first->employmentRecord);
        $this->earning($copy, $type, 5000);
        $copy->update(['compensation_reference' => '  '.strtolower($first->compensation_reference).'  ', 'overlap_justification' => 'Claimed exception']);
        app(PayrollComputationService::class)->computeDetail($target, $copy->employmentRecord, $target->payrollPeriod);
        $source->update(['status' => 'FINALIZED']);

        $this->postJson(route('payroll-runs.submit', $target))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$copy->getKey());
        $this->assertSame('DRAFT', $target->fresh()->status);
    }

    public function test_adjustments_reference_the_original_and_record_only_the_explicit_difference(): void
    {
        $this->officer();
        $this->confirmedPolicy('ADJUSTMENT');
        $source = $this->createRun();
        $original = $this->detail($source);
        $source->update(['status' => 'FINALIZED']);
        $values = $original->getAttributes();
        $run = $this->createRun($source->payrollPeriod, 'ADJUSTMENT');
        $detail = $this->detail($run, $original->employmentRecord);

        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$detail->getKey());
        $this->putJson(route('payroll-details.update', [$run, $detail]), [
            'payable_days' => 0, 'inputs_confirmed' => 1, 'adjustment_basic_pay' => 10000,
            'coverage_start' => '2026-01-01', 'coverage_end' => '2026-01-31',
            'original_payroll_detail_id' => $original->getKey(), 'compensation_reference' => 'CORR-1',
            'compensation_reason' => 'Approved underpayment difference', 'accounting_basis' => 'MAO-TEST-ONLY',
        ])->assertRedirect();
        $this->postJson(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($this->reviewer())->postJson(route('payroll-runs.approve', $run))->assertRedirect();
        $this->postJson(route('payroll-runs.finalize', $run))->assertRedirect();

        $this->assertSame('10000.00', $detail->fresh()->basic_pay);
        $this->assertSame('FINALIZED', $run->fresh()->status);
        $this->assertSame($values, $original->fresh()->getAttributes());
        $this->assertSame($original->getKey(), $detail->fresh()->original_payroll_detail_id);
    }

    /** @return array<string, array{bool, string}> */
    public static function invalidOriginals(): array
    {
        return ['wrong employee' => [false, 'FINALIZED'], 'unfinalized original' => [true, 'DRAFT']];
    }

    #[DataProvider('invalidOriginals')]
    public function test_wrong_employee_or_unfinalized_original_and_negative_adjustments_are_rejected(bool $sameEmployee, string $sourceStatus): void
    {
        $this->officer();
        $source = $this->createRun();
        $original = $this->detail($source);
        $source->update(['status' => $sourceStatus]);
        $run = $this->createRun($source->payrollPeriod, 'ADJUSTMENT');
        $detail = $this->detail($run, $sameEmployee ? $original->employmentRecord : null);
        $detail->update(['original_payroll_detail_id' => $original->getKey(), 'basic_pay' => 100]);
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$detail->getKey());

        $this->putJson(route('payroll-details.update', [$run, $detail]), ['payable_days' => 0, 'inputs_confirmed' => 1, 'adjustment_basic_pay' => -100])->assertUnprocessable()->assertJsonValidationErrors('adjustment_basic_pay');
        $this->assertSame('DRAFT', $run->fresh()->status);
    }

    public function test_repeated_earning_components_are_rejected_before_submission(): void
    {
        $this->officer();
        $run = $this->createRun();
        $detail = $this->detail($run);
        $type = EarningType::factory()->create();
        $this->earning($detail, $type);
        $this->earning($detail, $type);

        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable()->assertJsonValidationErrors('duplicates.'.$detail->getKey());
    }

    public function test_only_an_independently_returned_regular_run_can_be_replaced(): void
    {
        $officer = $this->officer();
        $run = $this->createRun();
        $this->detail($run);
        $input = ['payroll_period_id' => $run->payroll_period_id, 'run_type' => 'REGULAR', 'replaces_payroll_run_id' => $run->getKey(), 'replacement_reason' => 'Correct approved source inputs'];
        $this->postJson(route('payroll-runs.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('replaces_payroll_run_id');
        app(PayrollApprovalService::class)->submit($run, $officer);
        app(PayrollApprovalService::class)->returnForCorrection($run, $this->reviewer(), 'Replace invalid preparation');

        $this->postJson(route('payroll-runs.store'), $input)->assertRedirect();

        $this->assertSame('CANCELLED', $run->fresh()->status);
        $replacement = PayrollRun::where('replaces_payroll_run_id', $run->getKey())->sole();
        $this->assertSame('DRAFT', $replacement->status);
        $this->assertNull($replacement->details()->sole()->input_confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_REPLACED', 'record_id' => (string) $run->getKey()]);
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable();
        $this->postJson(route('payroll-runs.submit', $replacement))->assertUnprocessable();
        $this->postJson(route('payroll-details.confirm', [$replacement, $replacement->details()->sole()]), ['inputs_confirmed' => 1])->assertRedirect();
        $this->postJson(route('payroll-runs.submit', $replacement))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $replacement->fresh()->status);
    }

    public function test_non_regular_runs_cannot_be_approved_or_finalized_without_confirmed_policy(): void
    {
        $this->officer();
        $run = $this->createRun(type: 'SUPPLEMENTAL');
        $detail = $this->detail($run);
        $this->earning($detail);
        $run->update(['status' => 'PENDING_APPROVAL']);
        $this->actingAs($this->reviewer())->postJson(route('payroll-runs.approve', $run))->assertUnprocessable()->assertJsonValidationErrors('financial.policy');
        $run->update(['status' => 'APPROVED']);
        $this->postJson(route('payroll-runs.finalize', $run))->assertUnprocessable()->assertJsonValidationErrors('financial.policy');

        $this->assertSame('APPROVED', $run->fresh()->status);
    }

    /** @return array<string, array{string, string}> */
    public static function financialFailures(): array
    {
        return [
            'missing tax' => ['tax', 'No effective annual tax configuration'],
            'missing contribution' => ['contribution', 'No effective GSIS contribution rate'],
            'negative net' => ['negative', 'Net pay is negative'],
            'changed policy' => ['policy', 'calculation is missing or stale'],
        ];
    }

    #[DataProvider('financialFailures')]
    public function test_confirmed_non_regular_policy_does_not_bypass_f03(string $failure, string $message): void
    {
        $this->officer();
        $this->confirmedPolicy('SUPPLEMENTAL');
        $run = $this->createRun(type: 'SUPPLEMENTAL');
        $detail = $this->detail($run);
        $this->earning($detail, amount: 12000);
        if ($failure === 'tax') {
            TaxBracket::query()->delete();
        } elseif ($failure === 'contribution') {
            ContributionRate::where('contribution_type', 'GSIS')->delete();
        } elseif ($failure === 'negative') {
            $detail->update(['lbp_loan_deduction' => 20000]);
        }
        app(PayrollComputationService::class)->computeDetail($run, $detail->employmentRecord, $run->payrollPeriod);
        if ($failure === 'policy') {
            config(['payroll.non_regular.SUPPLEMENTAL.policy_reference' => 'MAO-REVISED']);
        }

        $response = $this->postJson(route('payroll-runs.submit', $run));

        $response->assertUnprocessable()->assertJsonValidationErrors('financial.'.$detail->getKey());
        $this->assertStringContainsString($message, implode(' ', $response->json('errors')['financial.'.$detail->getKey()]));
        $this->assertSame('DRAFT', $run->fresh()->status);
    }

    public function test_policy_revocation_after_approval_blocks_non_regular_finalization(): void
    {
        $this->officer();
        $this->confirmedPolicy('SUPPLEMENTAL');
        $run = $this->createRun(type: 'SUPPLEMENTAL');
        $detail = $this->detail($run);
        $this->earning($detail, amount: 12000);
        app(PayrollComputationService::class)->computeDetail($run, $detail->employmentRecord, $run->payrollPeriod);
        $this->postJson(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($this->reviewer())->postJson(route('payroll-runs.approve', $run))->assertRedirect();
        config(['payroll.non_regular.SUPPLEMENTAL.policy_reference' => null]);

        $this->postJson(route('payroll-runs.finalize', $run))->assertUnprocessable()->assertJsonValidationErrors('financial.policy');

        $this->assertNull($run->fresh()->finalized_at);
        $this->assertDatabaseMissing('payroll_approvals', ['payroll_run_id' => $run->getKey(), 'action' => 'FINALIZED']);
    }

    public function test_adjustment_finalization_preserves_original_registry_proof_and_creates_its_own(): void
    {
        $this->officer();
        $this->confirmedPolicy('ADJUSTMENT');
        Http::preventStrayRequests();
        config(['blockchain.registry' => [
            'enabled' => true, 'organization_id' => 'lgu-example', 'bridge_url' => 'http://bridge.test',
            'bridge_token' => str_repeat('t', 40), 'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64),
            'contract_address' => '0x'.str_repeat('b', 40), 'code_hash' => '0x'.str_repeat('c', 64),
            'signer_address' => '0x'.str_repeat('d', 40), 'deployment_block' => '10',
        ]]);
        $source = $this->createRun();
        $original = $this->detail($source);
        $this->postJson(route('payroll-runs.submit', $source))->assertRedirect();
        $reviewer = $this->reviewer();
        $this->actingAs($reviewer)->postJson(route('payroll-runs.approve', $source))->assertRedirect();
        $this->postJson(route('payroll-runs.finalize', $source))->assertRedirect();
        $proof = $source->registration()->sole();
        $proofValues = $proof->getAttributes();
        $sourceValues = $source->fresh()->getAttributes();
        $detailValues = $original->fresh()->getAttributes();
        $this->actingAs(User::findOrFail($source->created_by));
        $run = $this->createRun($source->payrollPeriod, 'ADJUSTMENT');
        $detail = $this->detail($run, $original->employmentRecord);
        $detail->update(['original_payroll_detail_id' => $original->getKey(), 'basic_pay' => 10000]);
        app(PayrollComputationService::class)->computeDetail($run, $detail->employmentRecord, $run->payrollPeriod);
        $this->postJson(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($reviewer)->postJson(route('payroll-runs.approve', $run))->assertRedirect();
        $this->postJson(route('payroll-runs.finalize', $run))->assertRedirect();

        $this->assertSame($proofValues, $proof->fresh()->getAttributes());
        $this->assertSame($sourceValues, $source->fresh()->getAttributes());
        $this->assertSame($detailValues, $original->fresh()->getAttributes());
        $this->assertNotSame($proof->payload_hash, $run->registration()->sole()->payload_hash);
        $this->assertDatabaseCount('payroll_registrations', 2);
        Http::assertNothingSent();
    }
}
