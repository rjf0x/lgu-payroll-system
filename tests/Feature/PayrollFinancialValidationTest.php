<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\EmployeeEarningAssignment;
use App\Models\PayrollApproval;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PayrollFinancialValidationTest extends TestCase
{
    use RefreshDatabase;

    private function computedDetail(string $status = 'DRAFT'): PayrollDetail
    {
        $this->freezeTime();
        $this->seed([RolePermissionSeeder::class, DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($officer);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'created_by' => $officer->getKey(), 'status' => $status]);
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'manual_adjustments' => true]);
        CompensationProfile::factory()->create(['employment_id' => $detail->employment_id, 'basic_rate' => 45000, 'effective_from' => '2024-01-01']);
        $detail->earnings()->create(['earning_type_id' => EarningType::factory()->create(['is_taxable' => false])->getKey(), 'earning_name_snapshot' => 'Allowance', 'amount' => 500, 'is_taxable' => false]);
        $detail->deductions()->create(['deduction_type_id' => DeductionType::factory()->create(['is_statutory' => false, 'deduction_category' => 'LOAN'])->getKey(), 'deduction_name_snapshot' => 'Loan', 'amount' => 100]);
        $detail->update(['lbp_loan_deduction' => 25]);

        return $this->recompute($detail);
    }

    private function recompute(PayrollDetail $detail): PayrollDetail
    {
        return app(PayrollComputationService::class)->computeDetail($detail->payrollRun, $detail->employmentRecord, $detail->payrollRun->payrollPeriod)->fresh();
    }

    private function assertSubmissionBlocked(PayrollDetail $detail, string $message): void
    {
        $before = $detail->refresh()->getAttributes();
        $status = $detail->payrollRun->fresh()->status;
        $response = $this->postJson(route('payroll-runs.submit', $detail->payrollRun));

        $response->assertUnprocessable()->assertJsonValidationErrors('financial.'.$detail->getKey());
        $messages = implode(' ', $response->json('errors')['financial.'.$detail->getKey()]);
        $this->assertStringContainsString($detail->employee_number_snapshot, $messages);
        $this->assertStringContainsString($message, $messages);
        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame($status, $detail->payrollRun->fresh()->status);
        $this->assertNull($detail->payrollRun->fresh()->submitted_at);
        $this->assertDatabaseMissing('payroll_approvals', ['payroll_run_id' => $detail->payroll_run_id, 'action' => 'SUBMITTED']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYROLL_SUBMITTED', 'record_id' => (string) $detail->payroll_run_id]);
    }

    /** @return array<string, array{string, string|null}> */
    public static function missingCompensation(): array
    {
        return ['deleted' => ['delete', null], 'future' => ['effective_from', '2026-02-01'], 'expired' => ['effective_to', '2025-12-31']];
    }

    #[DataProvider('missingCompensation')]
    public function test_missing_effective_compensation_blocks_even_a_recomputed_zero_salary(string $field, ?string $date): void
    {
        $detail = $this->computedDetail();
        $profile = CompensationProfile::where('employment_id', $detail->employment_id)->firstOrFail();
        if ($field === 'delete') {
            $profile->delete();
        } else {
            $profile->update([$field => $date]);
        }
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, 'No effective compensation profile');
    }

    /** @return array<string, array{string}> */
    public static function contributionTypes(): array
    {
        return ['gsis' => ['GSIS'], 'philhealth' => ['PhilHealth'], 'pagibig' => ['Pag-IBIG']];
    }

    #[DataProvider('contributionTypes')]
    public function test_missing_statutory_rates_block_submission(string $type): void
    {
        $detail = $this->computedDetail();
        ContributionRate::where('contribution_type', $type)->update(['effective_to' => '2025-12-31']);
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, "No effective {$type} contribution rate");
    }

    public function test_missing_statutory_deduction_type_blocks_with_an_actionable_message(): void
    {
        $detail = $this->computedDetail();
        DeductionType::where('deduction_name', 'GSIS Contribution')->update(['status' => 'INACTIVE']);

        $this->assertSubmissionBlocked($detail, 'Required statutory deduction type [GSIS Contribution]');
    }

    public function test_recomputation_rolls_back_when_a_required_deduction_type_is_unavailable(): void
    {
        $detail = $this->computedDetail();
        DeductionType::where('deduction_name', 'GSIS Contribution')->update(['status' => 'INACTIVE']);
        $before = $detail->getAttributes();
        $deductions = $detail->deductions()->get()->toArray();

        $this->postJson(route('payroll-runs.compute', $detail->payrollRun))->assertUnprocessable()
            ->assertJsonValidationErrors('financial.'.$detail->getKey())
            ->assertJsonFragment(['message' => "{$detail->employee_number_snapshot} ({$detail->employee_name_snapshot}): Required deduction type [GSIS Contribution] is missing, inactive, or not statutory. Correct the configuration and recompute."]);

        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame($deductions, $detail->deductions()->get()->toArray());
        $this->assertSame('DRAFT', $detail->payrollRun->fresh()->status);
    }

    public function test_missing_tax_configuration_blocks_even_when_the_saved_tax_is_zero(): void
    {
        $detail = $this->computedDetail();
        TaxBracket::query()->delete();
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, 'No effective annual tax configuration');
    }

    public function test_expired_tax_configuration_does_not_satisfy_period_coverage(): void
    {
        $detail = $this->computedDetail();
        TaxBracket::query()->update(['effective_to' => '2025-12-31']);

        $this->assertSubmissionBlocked($detail, 'No effective annual tax configuration');
    }

    public function test_invalid_contribution_limits_block_submission(): void
    {
        $detail = $this->computedDetail();
        ContributionRate::where('contribution_type', 'GSIS')->update(['minimum_compensation' => 1000, 'maximum_compensation' => 500]);

        $this->assertSubmissionBlocked($detail, 'GSIS contribution configuration contains invalid rates');
    }

    public function test_invalid_tax_rate_blocks_submission(): void
    {
        $detail = $this->computedDetail();
        TaxBracket::where('minimum_income', 400000)->update(['marginal_tax_rate' => -0.1]);

        $this->assertSubmissionBlocked($detail, 'tax configuration contains an invalid income range or tax rate');
    }

    public function test_tax_bracket_gap_for_the_employee_income_blocks_submission(): void
    {
        $detail = $this->computedDetail();
        TaxBracket::where('minimum_income', 400000)->delete();
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, 'do not cover the calculated taxable income');
    }

    public function test_overlapping_tax_brackets_block_ambiguous_tax_coverage(): void
    {
        $detail = $this->computedDetail();
        TaxBracket::factory()->create(['minimum_income' => 450000, 'maximum_income' => 500000, 'marginal_tax_rate' => 0.1]);
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, 'tax brackets overlap');
    }

    /** @return array<string, array{string}> */
    public static function financialTotals(): array
    {
        return array_combine($fields = ['salary_rate_snapshot', 'basic_pay', 'total_earnings', 'gross_pay', 'total_deductions', 'nthp_before_lbp', 'net_pay'], array_map(fn (string $field): array => [$field], $fields));
    }

    #[DataProvider('financialTotals')]
    public function test_inconsistent_saved_totals_block_submission(string $field): void
    {
        $detail = $this->computedDetail();
        $detail->update([$field => 123]);

        $this->assertSubmissionBlocked($detail, "Saved {$field} does not reconcile");
    }

    public function test_employer_contribution_tampering_is_rejected(): void
    {
        $detail = $this->computedDetail();
        $detail->employerContributions()->firstOrFail()->update(['amount' => 1]);

        $this->assertSubmissionBlocked($detail, 'Saved employer contributions do not match');
    }

    public function test_missing_statutory_line_is_rejected_even_with_reconciled_totals(): void
    {
        $detail = $this->computedDetail();
        $row = $detail->deductions()->whereHas('deductionType', fn ($query) => $query->where('deduction_name', 'GSIS Contribution'))->firstOrFail();
        $amount = (float) $row->amount;
        $row->delete();
        $detail->update(['total_deductions' => (float) $detail->total_deductions - $amount, 'nthp_before_lbp' => (float) $detail->nthp_before_lbp + $amount, 'net_pay' => (float) $detail->net_pay + $amount]);

        $this->assertSubmissionBlocked($detail, 'Saved statutory deductions or withholding tax do not match');
    }

    /** @return array<string, array{string, int}> */
    public static function changedInputs(): array
    {
        return ['days' => ['payable_days', 21], 'lbp' => ['lbp_loan_deduction', 125]];
    }

    #[DataProvider('changedInputs')]
    public function test_changed_inputs_require_full_recomputation(string $field, int $amount): void
    {
        $detail = $this->computedDetail();
        $detail->update([$field => $amount]);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');

        $detail = $this->recompute($detail);
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_configuration_change_requires_recomputation_even_when_the_rounded_amount_is_unchanged(): void
    {
        $detail = $this->computedDetail();
        ContributionRate::where('contribution_type', 'GSIS')->update(['effective_from' => '2024-02-01']);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_compensation_change_requires_recomputation(): void
    {
        $detail = $this->computedDetail();
        CompensationProfile::where('employment_id', $detail->employment_id)->update(['basic_rate' => 46000]);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_tax_configuration_change_requires_recomputation(): void
    {
        $detail = $this->computedDetail();
        TaxBracket::where('minimum_income', 400000)->update(['base_tax' => 22501]);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_changed_earning_tax_treatment_requires_and_is_applied_by_recomputation(): void
    {
        $detail = $this->computedDetail();
        $earning = $detail->earnings()->firstOrFail();
        $earning->earningType->update(['is_taxable' => true]);

        $this->assertSubmissionBlocked($detail, 'has changed tax treatment');

        $detail = $this->recompute($detail);
        $this->assertTrue($earning->fresh()->is_taxable);
        $this->assertSame('500.00', $earning->fresh()->amount);
        $this->assertSame('36791.67', $detail->nthp_before_lbp);
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_salary_grade_changes_require_recomputation_and_an_effective_schedule(): void
    {
        $detail = $this->computedDetail();
        $schedule = SalarySchedule::factory()->create(['effective_from' => '2024-01-01']);
        $step = SalaryGradeStep::factory()->create(['salary_schedule_id' => $schedule->getKey(), 'monthly_salary' => 45000]);
        CompensationProfile::where('employment_id', $detail->employment_id)->update(['grade_step_id' => $step->getKey(), 'basic_rate' => null]);
        $detail = $this->recompute($detail);
        $step->update(['monthly_salary' => 46000]);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');

        $detail = $this->recompute($detail);
        $schedule->update(['effective_to' => '2025-12-31']);
        $this->assertSubmissionBlocked($detail, 'salary schedule is not active and effective');
    }

    public function test_earning_and_deduction_changes_require_recomputation_even_when_totals_still_balance(): void
    {
        $detail = $this->computedDetail();
        $detail->earnings()->firstOrFail()->update(['amount' => 600]);
        $detail->deductions()->whereHas('deductionType', fn ($query) => $query->where('is_statutory', false))->firstOrFail()->update(['amount' => 200]);
        $detail->update(['total_earnings' => 600, 'gross_pay' => 45600, 'total_deductions' => (float) $detail->total_deductions + 100]);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_automatic_assignment_changes_require_recomputation(): void
    {
        $detail = $this->computedDetail();
        EmployeeEarningAssignment::create(['employment_id' => $detail->employment_id, 'earning_type_id' => EarningType::factory()->create()->getKey(), 'effective_from' => '2024-01-01', 'amount' => 100, 'frequency' => 'MONTHLY']);

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_partial_total_recomputation_cannot_certify_stale_salary(): void
    {
        $detail = $this->computedDetail();
        CompensationProfile::where('employment_id', $detail->employment_id)->update(['basic_rate' => 46000]);
        app(PayrollComputationService::class)->recomputeTotals($detail, $detail->payrollRun->payrollPeriod);
        $detail->refresh();

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_legacy_calculations_without_a_fingerprint_require_recomputation(): void
    {
        $detail = $this->computedDetail();
        $detail->forceFill(['computation_fingerprint' => null])->save();

        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
    }

    public function test_negative_net_pay_blocks_submission_until_deductions_are_corrected(): void
    {
        $detail = $this->computedDetail();
        $detail->update(['lbp_loan_deduction' => 100000]);
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, 'Net pay is negative');

        $detail->update(['lbp_loan_deduction' => 25]);
        $detail = $this->recompute($detail);
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_manual_deductions_exceeding_gross_pay_block_submission(): void
    {
        $detail = $this->computedDetail();
        $detail->deductions()->whereHas('deductionType', fn ($query) => $query->where('is_statutory', false))->firstOrFail()->update(['amount' => 100000]);
        $detail = $this->recompute($detail);

        $this->assertSubmissionBlocked($detail, 'Net pay is negative');
    }

    public function test_zero_tax_is_valid_when_covered_by_an_effective_zero_tax_bracket(): void
    {
        $detail = $this->computedDetail();
        CompensationProfile::where('employment_id', $detail->employment_id)->update(['basic_rate' => 15000]);
        $detail = $this->recompute($detail);

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();

        $this->assertDatabaseMissing('payroll_deductions', ['payroll_detail_id' => $detail->getKey(), 'deduction_name_snapshot' => 'Withholding Tax']);
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_legitimate_zero_net_pay_is_permitted_with_valid_calculations(): void
    {
        $detail = $this->computedDetail();
        $detail->update(['lbp_loan_deduction' => (float) $detail->nthp_before_lbp]);
        $detail = $this->recompute($detail);

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();

        $this->assertSame('0.00', $detail->net_pay);
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_confirmed_zero_days_can_submit_when_the_valid_configuration_produces_zero_pay(): void
    {
        $detail = $this->computedDetail();
        ContributionRate::query()->update(['minimum_compensation' => 0]);
        $detail->earnings()->delete();
        $detail->deductions()->whereHas('deductionType', fn ($query) => $query->where('is_statutory', false))->delete();
        $detail->update(['payable_days' => 0, 'lbp_loan_deduction' => 0]);
        $detail = $this->recompute($detail);

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();

        $this->assertSame('0.00', $detail->basic_pay);
        $this->assertSame('0.00', $detail->net_pay);
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_failure_to_record_submission_rolls_back_the_status_change(): void
    {
        $detail = $this->computedDetail();
        PayrollApproval::creating(function (): void {
            throw new RuntimeException('Approval history unavailable');
        });

        try {
            app(PayrollApprovalService::class)->submit($detail->payrollRun, auth()->user());
            $this->fail('Expected the approval history failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Approval history unavailable', $exception->getMessage());
        }

        $this->assertSame('DRAFT', $detail->payrollRun->fresh()->status);
        $this->assertNull($detail->payrollRun->fresh()->submitted_at);
        $this->assertDatabaseMissing('payroll_approvals', ['payroll_run_id' => $detail->payroll_run_id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYROLL_SUBMITTED', 'record_id' => (string) $detail->payroll_run_id]);
    }

    public function test_valid_financials_submit_with_known_amounts_and_an_audit_record(): void
    {
        $detail = $this->computedDetail();

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();

        $this->assertSame('45500.00', $detail->gross_pay);
        $this->assertSame('8608.33', $detail->total_deductions);
        $this->assertSame('36866.67', $detail->net_pay);
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
        $this->assertDatabaseHas('payroll_approvals', ['payroll_run_id' => $detail->payroll_run_id, 'action' => 'SUBMITTED', 'acted_by' => auth()->id()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_SUBMITTED', 'record_id' => (string) $detail->payroll_run_id]);
    }

    public function test_financial_errors_are_visible_on_the_existing_payroll_page(): void
    {
        $detail = $this->computedDetail();
        $detail->update(['net_pay' => -1]);
        $url = route('payroll-runs.show', $detail->payrollRun);

        $this->followingRedirects()->from($url)->post(route('payroll-runs.submit', $detail->payrollRun))
            ->assertOk()->assertSee($detail->employee_number_snapshot)->assertSee('Net pay is negative');
    }

    /** @return array<string, array{string}> */
    public static function editableStates(): array
    {
        return ['draft' => ['DRAFT'], 'returned' => ['RETURNED'], 'rejected' => ['REJECTED']];
    }

    #[DataProvider('editableStates')]
    public function test_a_later_employee_failure_preserves_the_whole_editable_run(string $status): void
    {
        $detail = $this->computedDetail($status);
        $other = PayrollDetail::factory()->create(['payroll_run_id' => $detail->payroll_run_id]);
        $this->assertNotNull($detail->computation_fingerprint);

        $this->assertSubmissionBlocked($other, 'No effective compensation profile');

        $this->assertSame($detail->getAttributes(), $detail->fresh()->getAttributes());
        $this->assertTrue($detail->payrollRun->fresh()->isEditable());
    }

    public function test_corrected_configuration_requires_recomputation_before_submission_succeeds(): void
    {
        $detail = $this->computedDetail();
        ContributionRate::where('contribution_type', 'GSIS')->update(['effective_to' => '2025-12-31']);
        $detail = $this->recompute($detail);
        $this->assertSubmissionBlocked($detail, 'No effective GSIS contribution rate');
        ContributionRate::where('contribution_type', 'GSIS')->update(['effective_to' => null]);
        $this->assertSubmissionBlocked($detail, 'calculation is missing or stale');
        $detail = $this->recompute($detail);

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();

        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }
}
