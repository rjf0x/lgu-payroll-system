<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\BlockchainAnchorService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class ArchivedEmployeePayrollTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = Role::PAYROLL_OFFICER): User
    {
        $this->freezeTime();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    private function detail(string $status = 'DRAFT'): PayrollDetail
    {
        $this->seed([DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        ContributionRate::query()->update(['employee_rate' => 0, 'employer_rate' => 0]);
        TaxBracket::query()->update(['base_tax' => 0, 'marginal_tax_rate' => 0]);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'status' => $status, 'created_by' => auth()->id()]);
        $employment = EmploymentRecord::factory()->create();
        CompensationProfile::factory()->create([
            'employment_id' => $employment->getKey(), 'grade_step_id' => null,
            'basic_rate' => 22000, 'service_fraction' => 1, 'pay_basis' => 'MONTHLY', 'effective_from' => '2024-01-01',
        ]);

        return PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employment_id' => $employment->getKey()]);
    }

    /** @return array<string, mixed> */
    private function inputs(): array
    {
        return ['payable_days' => 11, 'inputs_confirmed' => 1, 'earnings' => [], 'deductions' => [], 'eligibility_reason' => 'Earned January salary supported by approved HR separation summary.'];
    }

    /** @return array<string, array{string}> */
    public static function editableStates(): array
    {
        return ['draft' => ['DRAFT'], 'returned' => ['RETURNED'], 'rejected' => ['REJECTED']];
    }

    #[DataProvider('editableStates')]
    public function test_archived_employee_computation_preserves_payable_detail_and_requires_resolution(string $status): void
    {
        $user = $this->signIn();
        $detail = $this->detail($status);
        $detail->employee->delete();
        $run = $detail->payrollRun;

        $this->post(route('payroll-runs.compute', $run))->assertRedirect();

        $this->assertModelExists($detail);
        $this->assertSame('22000.00', $detail->fresh()->basic_pay);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Eligibility unresolved')->assertViewHas('canSubmit', false);
        $this->get(route('payroll-details.edit', [$run, $detail]))->assertOk()->assertSee('Retain employee: eligibility justification');
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable();
        $this->putJson(route('payroll-details.update', [$run, $detail]), array_replace($this->inputs(), ['eligibility_reason' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors('eligibility_reason');
        $this->put(route('payroll-details.update', [$run, $detail]), $this->inputs())->assertRedirect();
        $this->assertSame('11000.00', $detail->fresh()->net_pay);
        $this->assertSame($user->getKey(), $detail->fresh()->eligibility_resolved_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_ELIGIBILITY_RETAINED', 'user_id' => $user->getKey()]);
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status);
    }

    public function test_new_computation_excludes_archived_employees_and_authorized_inclusion_requires_current_inputs(): void
    {
        $this->signIn();
        $employment = EmploymentRecord::factory()->create();
        $employment->employee->delete();
        $run = PayrollRun::factory()->create(['created_by' => auth()->id()]);

        $this->post(route('payroll-runs.compute', $run))->assertRedirect();

        $this->assertSame(0, $run->details()->count());
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee($employment->employee->full_name);
        $this->postJson(route('payroll-details.store', $run), ['employment_id' => $employment->getKey()])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->post(route('payroll-details.store', $run), ['employment_id' => $employment->getKey(), 'reason' => 'Earned compensation remains payable.'])->assertRedirect();
        $detail = $run->details()->sole();
        $this->assertNull($detail->input_confirmed_at);
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable();
        $this->post(route('payroll-runs.compute', $run))->assertRedirect();
        $this->assertModelExists($detail);
    }

    public function test_carry_forward_skips_archived_inactive_and_out_of_coverage_employees(): void
    {
        $this->signIn();
        $previous = $this->detail('FINALIZED')->payrollRun;
        $previous->update(['finalized_at' => now()]);
        $archived = PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey()]);
        $archived->employee->delete();
        $inactive = PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey()]);
        $inactive->employmentRecord->update(['employment_status' => 'INACTIVE']);
        $ended = PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey()]);
        $ended->employmentRecord->update(['end_date' => '2026-01-31']);
        $future = PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey()]);
        $future->employmentRecord->update(['start_date' => '2026-03-01']);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);

        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $run = $period->runs()->sole();
        $this->assertSame($previous->details()->firstOrFail()->employee_id, $run->details()->sole()->employee_id);
        $this->assertNull($run->details()->sole()->input_confirmed_at);
        $this->assertSame(5, $previous->details()->count());
    }

    /** @return array<string, array{string, string|null}> */
    public static function employmentDates(): array
    {
        return ['ended before coverage' => ['2024-01-01', '2025-12-31'], 'starts after coverage' => ['2026-02-01', null]];
    }

    #[DataProvider('employmentDates')]
    public function test_out_of_coverage_detail_is_kept_for_resolution_but_cannot_be_retained_or_submitted(string $start, ?string $end): void
    {
        $this->signIn();
        $detail = $this->detail();
        $detail->employmentRecord->update(['start_date' => $start, 'end_date' => $end]);
        $detail->employee->delete();

        $this->post(route('payroll-runs.compute', $detail->payrollRun))->assertRedirect();

        $this->assertSame('45000.00', $detail->fresh()->net_pay);
        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertUnprocessable();
        $this->putJson(route('payroll-details.update', [$detail->payrollRun, $detail]), $this->inputs())->assertUnprocessable();
        $this->assertNull($detail->fresh()->eligibility_resolved_at);
    }

    private function financialRows(PayrollDetail $detail): void
    {
        $this->seed(DeductionTypeSeeder::class);
        $detail->earnings()->create(['earning_type_id' => EarningType::factory()->create()->getKey(), 'earning_name_snapshot' => 'Allowance', 'amount' => 100, 'is_taxable' => false]);
        $detail->deductions()->create(['deduction_type_id' => DeductionType::factory()->create()->getKey(), 'deduction_name_snapshot' => 'Loan', 'amount' => 50]);
        $detail->employerContributions()->create(['contribution_rate_id' => ContributionRate::factory()->create()->getKey(), 'contribution_type_snapshot' => 'Employer', 'amount' => 200]);
    }

    #[DataProvider('editableStates')]
    public function test_removal_deletes_financial_rows_recalculates_totals_and_audits_reason(string $status): void
    {
        $user = $this->signIn();
        $detail = $this->detail($status);
        $detail->employee->delete();
        $other = PayrollDetail::factory()->create(['payroll_run_id' => $detail->payroll_run_id, 'gross_pay' => 1000, 'net_pay' => 900]);
        $this->financialRows($detail);

        $this->delete(route('payroll-details.destroy', [$detail->payrollRun, $detail]), ['reason' => 'No compensation payable; confirmed by HR.'])->assertRedirect();

        $this->assertModelMissing($detail);
        foreach (['payroll_earnings', 'payroll_deductions', 'payroll_employer_contributions'] as $table) {
            $this->assertDatabaseMissing($table, ['payroll_detail_id' => $detail->getKey()]);
        }
        $this->assertModelExists($other);
        $audit = AuditLog::where('action', 'PAYROLL_DETAIL_REMOVED')->sole();
        $this->assertSame($user->getKey(), $audit->user_id);
        $this->assertSame('No compensation payable; confirmed by HR.', $audit->new_values['reason']);
        $this->assertSame(900, $audit->new_values['totals']['net_pay']);
        $this->get(route('payroll-runs.show', $detail->payrollRun))->assertOk()->assertViewHas('totalNet', 900.0);
        $this->post(route('payroll-runs.compute', $detail->payrollRun))->assertRedirect();
        $this->assertDatabaseMissing('payroll_details', ['payroll_run_id' => $detail->payroll_run_id, 'employee_id' => $detail->employee_id]);
    }

    public function test_removal_rolls_back_every_financial_row_if_auditing_fails(): void
    {
        $this->signIn();
        $detail = $this->detail();
        $this->financialRows($detail);
        $this->mock(AuditLogger::class)->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->delete(route('payroll-details.destroy', [$detail->payrollRun, $detail]), ['reason' => 'No payable compensation.']);
            $this->fail('Expected the audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertModelExists($detail);
        $this->assertSame(1, $detail->earnings()->count());
        $this->assertSame(1, $detail->deductions()->count());
        $this->assertSame(1, $detail->employerContributions()->count());
    }

    /** @return array<string, array{string}> */
    public static function protectedStates(): array
    {
        return ['pending' => ['PENDING_APPROVAL'], 'approved' => ['APPROVED'], 'finalized' => ['FINALIZED']];
    }

    #[DataProvider('protectedStates')]
    public function test_protected_runs_reject_inclusion_removal_and_input_changes(string $status): void
    {
        $this->signIn();
        $detail = $this->detail($status);
        $detail->employee->delete();
        $employment = EmploymentRecord::factory()->create();
        $before = $detail->fresh()->getAttributes();

        $this->deleteJson(route('payroll-details.destroy', [$detail->payrollRun, $detail]), ['reason' => 'Correction'])->assertForbidden();
        $this->postJson(route('payroll-details.store', $detail->payrollRun), ['reason' => 'Correction', 'employment_id' => $employment->getKey()])->assertForbidden();
        $this->putJson(route('payroll-details.update', [$detail->payrollRun, $detail]), $this->inputs())->assertForbidden();
        $this->post(route('payroll-runs.compute', $detail->payrollRun))->assertRedirect()->assertSessionHas('error');

        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame($status, $detail->payrollRun->fresh()->status);
    }

    public function test_approved_payroll_requires_return_and_new_independent_approval_after_correction(): void
    {
        $officer = $this->signIn();
        $detail = $this->detail('PENDING_APPROVAL');
        $run = $detail->payrollRun;
        $approver = $this->signIn(Role::APPROVER);
        $this->post(route('payroll-runs.approve', $run))->assertRedirect();
        $this->actingAs($officer)->delete(route('employees.destroy', $detail->employee), ['reason' => 'Employment separated.'])->assertRedirect();
        $this->actingAs($approver)->post(route('payroll-runs.return', $run), ['remarks' => 'Resolve archived employee eligibility.'])->assertRedirect();
        $this->actingAs($officer)->put(route('payroll-details.update', [$run, $detail]), $this->inputs())->assertRedirect();
        $this->actingAs($approver)->postJson(route('payroll-runs.finalize', $run))->assertUnprocessable();
        $this->actingAs($officer)->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($approver)->postJson(route('payroll-runs.finalize', $run))->assertUnprocessable();
        $this->post(route('payroll-runs.approve', $run))->assertRedirect();
        $this->post(route('payroll-runs.finalize', $run))->assertRedirect();

        $this->assertSame('FINALIZED', $run->fresh()->status);
        $this->assertSame(2, $run->approvals()->where('action', 'APPROVED')->count());
    }

    public function test_changed_employment_dates_invalidate_an_earlier_eligibility_decision(): void
    {
        $this->signIn();
        $detail = $this->detail();
        $detail->employee->delete();
        $this->put(route('payroll-details.update', [$detail->payrollRun, $detail]), $this->inputs())->assertRedirect();
        $detail->employmentRecord->update(['end_date' => '2026-01-20']);

        $this->postJson(route('payroll-runs.submit', $detail->payrollRun))->assertUnprocessable();

        $this->assertSame('DRAFT', $detail->payrollRun->fresh()->status);
    }

    public function test_archived_inactive_employee_retains_compensation_effective_on_the_last_day_of_employment(): void
    {
        $this->signIn();
        $detail = $this->detail();
        $detail->employee->delete();
        $detail->employmentRecord->update(['employment_status' => 'INACTIVE', 'end_date' => '2026-01-15']);
        $detail->employmentRecord->compensationProfiles()->update(['effective_to' => '2026-01-15']);

        $this->put(route('payroll-details.update', [$detail->payrollRun, $detail]), $this->inputs())->assertRedirect();

        $this->assertSame('11000.00', $detail->fresh()->basic_pay);
        $this->post(route('payroll-runs.compute', $detail->payrollRun))->assertRedirect();
        $this->assertSame('11000.00', $detail->fresh()->basic_pay);
        $this->post(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_eligibility_context_remains_valid_when_database_json_key_order_changes(): void
    {
        $this->signIn();
        $detail = $this->detail();
        $detail->employee->delete();
        $this->put(route('payroll-details.update', [$detail->payrollRun, $detail]), $this->inputs())->assertRedirect();
        $detail->refresh()->update(['eligibility_context' => array_reverse($detail->eligibility_context, true)]);

        $this->post(route('payroll-runs.submit', $detail->payrollRun))->assertRedirect();

        $this->assertSame('PENDING_APPROVAL', $detail->payrollRun->fresh()->status);
    }

    public function test_explicit_inclusion_rejects_employment_outside_coverage_without_creating_a_detail(): void
    {
        $this->signIn();
        $run = $this->detail()->payrollRun;
        $employment = EmploymentRecord::factory()->create(['end_date' => '2025-12-31']);
        $employment->employee->delete();

        $this->postJson(route('payroll-details.store', $run), ['employment_id' => $employment->getKey(), 'reason' => 'Requested earned salary.'])->assertUnprocessable();

        $this->assertDatabaseMissing('payroll_details', ['payroll_run_id' => $run->getKey(), 'employment_id' => $employment->getKey()]);
    }

    public function test_finalized_payroll_snapshots_payslip_and_integrity_proof_survive_archival(): void
    {
        $user = $this->signIn();
        $detail = $this->detail('FINALIZED');
        $this->financialRows($detail);
        $run = $detail->payrollRun;
        $run->update(['finalized_at' => now(), 'finalized_by' => $user->getKey()]);
        $service = app(BlockchainAnchorService::class);
        $anchor = $service->anchor($run, $user);
        $payslip = $detail->payslip()->create(['payslip_number' => 'PS-ARCHIVE-1', 'generated_at' => now(), 'publication_status' => 'PUBLISHED', 'published_at' => now()]);
        $before = $detail->fresh()->load(['earnings', 'deductions', 'employerContributions'])->toArray();
        $proof = $anchor->fresh()->getAttributes();
        $slip = $payslip->fresh()->getAttributes();

        $this->delete(route('employees.destroy', $detail->employee), ['reason' => 'Separated from employment.'])->assertRedirect();

        $this->assertSame($before, $detail->fresh()->load(['earnings', 'deductions', 'employerContributions'])->toArray());
        $this->assertSame($proof, $anchor->fresh()->getAttributes());
        $this->assertSame($slip, $payslip->fresh()->getAttributes());
        $this->assertSame($anchor->payload_hash, $service->hashPayrollRun($run));
        $this->assertDatabaseHas('audit_logs', ['action' => 'EMPLOYEE_ARCHIVED', 'user_id' => $user->getKey()]);
    }

    public function test_resolution_endpoints_enforce_permission_scope_and_required_removal_reason(): void
    {
        $this->signIn();
        $detail = $this->detail();
        $otherRun = PayrollRun::factory()->create();
        $this->deleteJson(route('payroll-details.destroy', [$otherRun, $detail]), ['reason' => 'Correction'])->assertNotFound();
        $this->deleteJson(route('payroll-details.destroy', [$detail->payrollRun, $detail]))->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->signIn(Role::VIEWER);
        $this->deleteJson(route('payroll-details.destroy', [$detail->payrollRun, $detail]), ['reason' => 'Correction'])->assertForbidden();
        $this->postJson(route('payroll-details.store', $detail->payrollRun), ['reason' => 'Correction', 'employment_id' => $detail->employment_id])->assertForbidden();
        auth()->logout();
        $this->delete(route('payroll-details.destroy', [$detail->payrollRun, $detail]), ['reason' => 'Correction'])->assertRedirect(route('login'));

        $this->assertModelExists($detail);
    }
}
