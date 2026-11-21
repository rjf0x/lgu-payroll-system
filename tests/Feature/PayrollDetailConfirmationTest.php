<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PayrollDetailConfirmationTest extends TestCase
{
    use RefreshDatabase;

    private function detail(string $status = 'DRAFT', ?float $days = 20): PayrollDetail
    {
        $this->freezeTime();
        $this->seed(RolePermissionSeeder::class);
        $this->seed([DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        ContributionRate::query()->update(['employee_rate' => 0, 'employer_rate' => 0]);
        TaxBracket::query()->update(['base_tax' => 0, 'marginal_tax_rate' => 0]);
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($officer);
        $detail = PayrollDetail::factory()->create([
            'payroll_run_id' => PayrollRun::factory()->create(['status' => $status, 'created_by' => $officer->getKey()])->getKey(),
            'payable_days' => $days, 'input_confirmed_at' => null, 'input_recorded_by' => null,
            'payroll_status' => 'INPUT_REQUIRED', 'pay_basis_snapshot' => 'MONTHLY',
        ]);
        CompensationProfile::factory()->create([
            'employment_id' => $detail->employment_id, 'grade_step_id' => null,
            'basic_rate' => 22000, 'service_fraction' => 1, 'pay_basis' => 'MONTHLY', 'effective_from' => '2024-01-01',
        ]);

        return $detail;
    }

    public function test_breakdown_identifies_confirmation_missing_even_when_payable_days_are_present(): void
    {
        $detail = $this->detail();

        $this->get(route('payroll-runs.show', $detail->payrollRun))->assertOk()
            ->assertSee('Needs Confirmation')->assertSee('Confirm &amp; Save', false)
            ->assertSee(route('payroll-details.confirm', [$detail->payrollRun, $detail]))
            ->assertViewHas('canSubmit', false);
    }

    public function test_breakdown_routes_missing_payable_days_to_edit_instead_of_quick_confirmation(): void
    {
        $detail = $this->detail(days: null);

        $this->get(route('payroll-runs.show', $detail->payrollRun))->assertOk()->assertSee('Needs Input')
            ->assertDontSee(route('payroll-details.confirm', [$detail->payrollRun, $detail]));
        $this->postJson(route('payroll-details.confirm', [$detail->payrollRun, $detail]), ['inputs_confirmed' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('payable_days');

        $this->assertNull($detail->fresh()->input_confirmed_at);
    }

    /** @return array<string, array{string}> */
    public static function editableStates(): array
    {
        return ['draft' => ['DRAFT'], 'returned' => ['RETURNED'], 'rejected' => ['REJECTED']];
    }

    #[DataProvider('editableStates')]
    public function test_quick_confirmation_saves_current_inputs_preserves_adjustments_and_recomputes_pay(string $status): void
    {
        $detail = $this->detail($status);
        $earning = $detail->earnings()->create(['earning_type_id' => EarningType::factory()->create()->getKey(), 'earning_name_snapshot' => 'Allowance', 'amount' => 500, 'is_taxable' => false]);
        $deduction = $detail->deductions()->create(['deduction_type_id' => DeductionType::factory()->create(['is_statutory' => false])->getKey(), 'deduction_name_snapshot' => 'Loan', 'amount' => 100]);
        $detail->update(['lbp_loan_deduction' => 25]);
        $run = $detail->payrollRun;

        $this->post(route('payroll-details.confirm', [$run, $detail]), ['inputs_confirmed' => 1, 'payable_days' => 1, 'earnings' => [], 'lbp_loan_deduction' => 999])->assertRedirect(route('payroll-runs.show', $run));

        $detail->refresh();
        $this->assertSame('20.00', $detail->payable_days);
        $this->assertSame('20000.00', $detail->basic_pay);
        $this->assertSame('20375.00', $detail->net_pay);
        $this->assertSame('25.00', $detail->lbp_loan_deduction);
        $this->assertSame('Approved test payroll input', $detail->input_reference);
        $this->assertSame('COMPUTED', $detail->payroll_status);
        $this->assertSame(auth()->id(), $detail->input_recorded_by);
        $this->assertNotNull($detail->input_confirmed_at);
        $this->assertModelExists($earning);
        $this->assertModelExists($deduction);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYROLL_INPUTS_CONFIRMED', 'user_id' => auth()->id(), 'record_id' => (string) $detail->getKey()]);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Confirmed')
            ->assertDontSee('Payroll inputs are incomplete')->assertDontSee(route('payroll-details.confirm', [$run, $detail]))->assertViewHas('canSubmit', true);
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status);
    }

    public function test_quick_confirmation_accepts_explicit_zero_days_without_granting_full_pay(): void
    {
        $detail = $this->detail(days: 0);

        $this->post(route('payroll-details.confirm', [$detail->payrollRun, $detail]), ['inputs_confirmed' => 1])->assertRedirect();

        $this->assertSame('0.00', $detail->fresh()->basic_pay);
        $this->assertNotNull($detail->fresh()->input_confirmed_at);
    }

    public function test_quick_confirmation_requires_an_explicit_confirmation_and_valid_stored_days(): void
    {
        $detail = $this->detail(days: 23);
        $route = route('payroll-details.confirm', [$detail->payrollRun, $detail]);

        $this->postJson($route)->assertUnprocessable()->assertJsonValidationErrors('inputs_confirmed');
        $this->postJson($route, ['inputs_confirmed' => 0])->assertUnprocessable()->assertJsonValidationErrors('inputs_confirmed');
        $this->postJson($route, ['inputs_confirmed' => 1])->assertUnprocessable()->assertJsonValidationErrors('payable_days');

        $this->assertNull($detail->fresh()->input_confirmed_at);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYROLL_INPUTS_CONFIRMED']);
    }

    public function test_quick_confirmation_does_not_bypass_unresolved_archived_employee_eligibility(): void
    {
        $detail = $this->detail();
        $detail->employee->delete();

        $this->get(route('payroll-runs.show', $detail->payrollRun))->assertOk()->assertSee('Eligibility unresolved')
            ->assertDontSee(route('payroll-details.confirm', [$detail->payrollRun, $detail]));
        $this->postJson(route('payroll-details.confirm', [$detail->payrollRun, $detail]), ['inputs_confirmed' => 1])->assertUnprocessable()->assertJsonValidationErrors('eligibility_reason');

        $this->assertNull($detail->fresh()->input_confirmed_at);
    }

    /** @return array<string, array{string}> */
    public static function protectedStates(): array
    {
        return ['pending' => ['PENDING_APPROVAL'], 'approved' => ['APPROVED'], 'finalized' => ['FINALIZED']];
    }

    #[DataProvider('protectedStates')]
    public function test_quick_confirmation_cannot_modify_protected_runs(string $status): void
    {
        $detail = $this->detail($status);
        $before = $detail->fresh()->getAttributes();

        $this->postJson(route('payroll-details.confirm', [$detail->payrollRun, $detail]), ['inputs_confirmed' => 1])->assertForbidden();

        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYROLL_INPUTS_CONFIRMED']);
    }

    public function test_quick_confirmation_enforces_route_scope_permissions_and_authentication(): void
    {
        $detail = $this->detail();
        $otherRun = PayrollRun::factory()->create();
        $route = route('payroll-details.confirm', [$detail->payrollRun, $detail]);

        $this->postJson(route('payroll-details.confirm', [$otherRun, $detail]), ['inputs_confirmed' => 1])->assertNotFound();
        $viewer = User::factory()->create();
        $viewer->roles()->attach(Role::where('role_name', Role::VIEWER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($viewer)->postJson($route, ['inputs_confirmed' => 1])->assertForbidden();
        auth()->logout();
        $this->post($route, ['inputs_confirmed' => 1])->assertRedirect(route('login'));

        $this->assertNull($detail->fresh()->input_confirmed_at);
    }

    public function test_quick_confirmation_rolls_back_inputs_and_computation_if_auditing_fails(): void
    {
        $detail = $this->detail();
        $before = $detail->fresh()->getAttributes();
        $this->partialMock(AuditLogger::class)->shouldReceive('log')->once()->andThrow(new RuntimeException('Audit unavailable'));
        $this->withoutExceptionHandling();

        try {
            $this->post(route('payroll-details.confirm', [$detail->payrollRun, $detail]), ['inputs_confirmed' => 1]);
            $this->fail('Expected an audit failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }

        $this->assertSame($before, $detail->fresh()->getAttributes());
    }
}
