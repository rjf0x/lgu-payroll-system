<?php

namespace Tests\Feature;

use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollRunCarryForwardTest extends TestCase
{
    use RefreshDatabase;

    private function signInOfficer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $officer = User::factory()->create();
        $officer->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($officer);

        return $officer;
    }

    public function test_creation_carries_forward_the_latest_matching_finalized_run_as_an_independent_draft(): void
    {
        $officer = $this->signInOfficer();
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);
        $previousPeriod = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $previous = PayrollRun::factory()->create(['payroll_period_id' => $previousPeriod->getKey(), 'status' => 'FINALIZED', 'finalized_at' => '2026-01-31 12:00:00']);
        $inputRecorder = User::factory()->create();
        $detail = PayrollDetail::factory()->create([
            'payroll_run_id' => $previous->getKey(), 'input_recorded_by' => $inputRecorder->getKey(),
            'input_reference' => 'Approved summary HR-123', 'input_confirmed_at' => '2026-01-30 09:00:00',
        ]);
        $detail->earnings()->create(['earning_type_id' => EarningType::factory()->create()->getKey(), 'earning_name_snapshot' => 'Allowance', 'amount' => 500, 'is_taxable' => false]);
        $detail->deductions()->create(['deduction_type_id' => DeductionType::factory()->create()->getKey(), 'deduction_name_snapshot' => 'Loan', 'amount' => 100]);
        $detail->employerContributions()->create(['contribution_rate_id' => ContributionRate::factory()->create()->getKey(), 'contribution_type_snapshot' => 'Employer contribution', 'amount' => 200]);
        $older = PayrollRun::factory()->create(['payroll_period_id' => $previousPeriod->getKey(), 'run_number' => 4, 'status' => 'FINALIZED', 'finalized_at' => '2026-01-30']);
        PayrollDetail::factory()->create(['payroll_run_id' => $older->getKey(), 'net_pay' => 123]);
        $futurePeriod = PayrollPeriod::factory()->create(['start_date' => '2026-03-01', 'end_date' => '2026-03-31']);
        PayrollRun::factory()->create(['payroll_period_id' => $futurePeriod->getKey(), 'status' => 'FINALIZED', 'finalized_at' => '2026-03-31']);
        PayrollRun::factory()->create(['payroll_period_id' => $previousPeriod->getKey(), 'run_number' => 2, 'status' => 'DRAFT']);
        PayrollRun::factory()->create(['payroll_period_id' => $previousPeriod->getKey(), 'run_number' => 3, 'run_type' => 'SUPPLEMENTAL', 'status' => 'FINALIZED', 'finalized_at' => '2026-02-01']);

        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $run = PayrollRun::where('payroll_period_id', $period->getKey())->firstOrFail();
        $copy = $run->details()->sole();
        $this->assertSame('DRAFT', $run->status);
        $this->assertNull($run->finalized_at);
        $this->assertSame($detail->employee_id, $copy->employee_id);
        $this->assertSame('45000.00', $copy->net_pay);
        $this->assertSame('2026-01-30 09:00:00', $copy->input_confirmed_at->format('Y-m-d H:i:s'));
        $this->assertSame('Approved summary HR-123', $copy->input_reference);
        $this->assertSame($inputRecorder->getKey(), $copy->input_recorded_by);
        $this->assertSame('COMPUTED', $copy->payroll_status);
        $this->assertTrue($copy->manual_adjustments);
        $this->assertSame('500.00', $copy->earnings()->sole()->amount);
        $this->assertSame('100.00', $copy->deductions()->sole()->amount);
        $this->assertSame('200.00', $copy->employerContributions()->sole()->amount);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertViewHas('canSubmit', true);
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $run->refresh()->status);
        $this->assertSame($officer->getKey(), $run->submitted_by);
        $this->assertSame('FINALIZED', $previous->fresh()->status);
        $copy->earnings()->delete();
        $this->assertSame(1, $detail->earnings()->count());
        $this->assertNotNull($detail->fresh()->input_confirmed_at);
    }

    public function test_creation_without_a_previous_finalized_run_keeps_the_empty_run_workflow(): void
    {
        $this->signInOfficer();
        $period = PayrollPeriod::factory()->create();

        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $this->assertSame(0, PayrollRun::where('payroll_period_id', $period->getKey())->sole()->details()->count());
    }

    public function test_carried_forward_confirmed_inputs_without_a_reference_are_ready_for_submission(): void
    {
        $this->signInOfficer();
        $previousPeriod = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);
        $previous = PayrollRun::factory()->create([
            'payroll_period_id' => $previousPeriod->getKey(), 'status' => 'FINALIZED', 'finalized_at' => '2026-01-31 12:00:00',
        ]);
        PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey(), 'input_reference' => null]);

        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $run = PayrollRun::where('payroll_period_id', $period->getKey())->sole();
        $this->assertNull($run->details()->sole()->input_reference);
        $this->assertSame('COMPUTED', $run->details()->sole()->payroll_status);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertViewHas('canSubmit', true);
        $this->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function incompleteInputs(): array
    {
        return [
            'missing payable days' => [['payable_days' => null]],
            'missing confirmation' => [['input_confirmed_at' => null]],
        ];
    }

    /** @param array<string, mixed> $inputs */
    #[DataProvider('incompleteInputs')]
    public function test_carrying_forward_incomplete_inputs_does_not_enable_submission(array $inputs): void
    {
        $this->signInOfficer();
        $previousPeriod = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-02-01', 'end_date' => '2026-02-28']);
        $previous = PayrollRun::factory()->create([
            'payroll_period_id' => $previousPeriod->getKey(), 'status' => 'FINALIZED', 'finalized_at' => '2026-01-31 12:00:00',
        ]);
        PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey(), ...$inputs]);

        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $period->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $run = PayrollRun::where('payroll_period_id', $period->getKey())->sole();
        $this->assertSame('INPUT_REQUIRED', $run->details()->sole()->payroll_status);
        $this->get(route('payroll-runs.show', $run))->assertOk()->assertViewHas('canSubmit', false);
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable();
        $this->assertSame('DRAFT', $run->fresh()->status);
    }
}
