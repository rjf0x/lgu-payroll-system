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
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => $previous->getKey(), 'input_recorded_by' => $officer->getKey()]);
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
        $this->assertNull($copy->input_confirmed_at);
        $this->assertNull($copy->input_reference);
        $this->assertNull($copy->input_recorded_by);
        $this->assertTrue($copy->manual_adjustments);
        $this->assertSame('500.00', $copy->earnings()->sole()->amount);
        $this->assertSame('100.00', $copy->deductions()->sole()->amount);
        $this->assertSame('200.00', $copy->employerContributions()->sole()->amount);
        $this->postJson(route('payroll-runs.submit', $run))->assertUnprocessable();
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
}
