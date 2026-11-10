<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeductionReportTest extends TestCase
{
    use RefreshDatabase;

    private function viewer(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::VIEWER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public function test_report_totals_saved_deductions_and_excludes_other_payroll_runs(): void
    {
        $user = $this->viewer();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        $loan = DeductionType::factory()->create(['deduction_name' => 'Renamed loan', 'status' => 'INACTIVE']);
        $tax = DeductionType::factory()->create();
        $first = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employee_name_snapshot' => 'Alex Santos']);
        $second = PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey(), 'employee_name_snapshot' => 'Jamie Reyes']);
        $first->deductions()->create(['deduction_type_id' => $loan->getKey(), 'deduction_name_snapshot' => 'Saved loan', 'amount' => '100.10']);
        $second->deductions()->create(['deduction_type_id' => $loan->getKey(), 'deduction_name_snapshot' => 'Saved loan', 'amount' => '200.20']);
        $first->deductions()->create(['deduction_type_id' => $tax->getKey(), 'deduction_name_snapshot' => 'Saved tax', 'amount' => '50.05']);
        PayrollDetail::factory()->create()->deductions()->create(['deduction_type_id' => $loan->getKey(), 'deduction_name_snapshot' => 'Other run deduction', 'amount' => 9000]);

        $this->actingAs($user)->get(route('reports.deductions', $run))->assertOk()
            ->assertSee('Saved loan')->assertDontSee('Renamed loan')->assertDontSee('Other run deduction')
            ->assertSee('Alex Santos')->assertSee('Jamie Reyes')->assertSee('300.30')->assertSee('350.35')
            ->assertSee('Print deductions report')->assertDontSee('Working copy.');
        $this->get(route('reports.deductions', [$run, 'deduction_type_id' => $tax->getKey()]))->assertOk()
            ->assertSee('50.05')->assertDontSee('Jamie Reyes')->assertDontSee('300.30');
        $this->get(route('reports.index'))->assertOk()->assertSee(route('reports.deductions', $run), false);
    }

    public function test_empty_draft_report_shows_working_copy_and_no_deductions(): void
    {
        $user = $this->viewer();
        $run = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_DRAFT]);

        $this->actingAs($user)->get(route('reports.deductions', $run))->assertOk()
            ->assertSee('No deductions have been recorded')->assertSee('Working copy.');
    }

    public function test_report_requires_authentication_and_payroll_view_permission(): void
    {
        $run = PayrollRun::factory()->create();
        $this->get(route('reports.deductions', $run))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->get(route('reports.deductions', $run))->assertForbidden();
    }

    public function test_filter_rejects_deductions_not_recorded_in_the_selected_run(): void
    {
        $user = $this->viewer();
        $run = PayrollRun::factory()->create();
        $type = DeductionType::factory()->create();

        $this->actingAs($user)->getJson(route('reports.deductions', [$run, 'deduction_type_id' => $type->getKey()]))
            ->assertUnprocessable()->assertJsonValidationErrors('deduction_type_id');
        $this->getJson(route('reports.deductions', [$run, 'deduction_type_id' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('deduction_type_id');
    }

    public function test_same_named_deduction_types_stay_separate_and_employee_rows_are_combined(): void
    {
        $user = $this->viewer();
        $detail = PayrollDetail::factory()->create(['employee_name_snapshot' => '<script>alert(1)</script>']);
        $firstType = DeductionType::factory()->create();
        $secondType = DeductionType::factory()->create();
        foreach (['10.10', '20.20'] as $amount) {
            $detail->deductions()->create(['deduction_type_id' => $firstType->getKey(), 'deduction_name_snapshot' => 'Loan', 'amount' => $amount]);
        }
        $detail->deductions()->create(['deduction_type_id' => $secondType->getKey(), 'deduction_name_snapshot' => 'Loan', 'amount' => '5.50']);

        $response = $this->actingAs($user)->get(route('reports.deductions', $detail->payrollRun))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('35.80');
        $groups = $response->viewData('groups');
        $this->assertCount(2, $groups);
        $this->assertCount(1, $groups[$firstType->getKey()]['employees']);
        $this->assertSame(30.3, $groups[$firstType->getKey()]['total']);
        $this->assertSame(5.5, $groups[$secondType->getKey()]['total']);
    }
}
