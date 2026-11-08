<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRunFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class,
            EmploymentTypeSeeder::class,
            EarningTypeSeeder::class,
            DeductionTypeSeeder::class,
            StatutoryConfigSeeder::class,
        ]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function seedEmployment(): void
    {
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

    private function period(): PayrollPeriod
    {
        return PayrollPeriod::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'pay_date' => '2025-01-31',
        ]);
    }

    public function test_officer_can_create_a_run_and_compute_it(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $this->seedEmployment();
        $period = $this->period();

        $this->actingAs($officer)
            ->post(route('payroll-runs.store'), [
                'payroll_period_id' => $period->payroll_period_id,
                'run_type' => 'REGULAR',
            ])
            ->assertRedirect();

        $run = PayrollRun::firstOrFail();
        $this->assertSame(PayrollRun::STATUS_DRAFT, $run->status);

        $this->actingAs($officer)
            ->post(route('payroll-runs.compute', $run))
            ->assertRedirect(route('payroll-runs.show', $run));

        $this->assertSame(1, $run->details()->count());
        $this->assertDatabaseHas('payroll_details', ['payroll_run_id' => $run->payroll_run_id]);
    }

    public function test_manual_earning_updates_gross_and_net(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $this->seedEmployment();
        $period = $this->period();

        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->payroll_period_id, 'created_by' => $officer->user_id]);
        $this->actingAs($officer)->post(route('payroll-runs.compute', $run));
        $detail = $run->details()->firstOrFail();

        $pera = EarningType::where('earning_name', 'PERA')->firstOrFail();

        $this->actingAs($officer)
            ->put(route('payroll-details.update', [$run, $detail]), [
                'payable_days' => 22, 'input_reference' => 'Approved test summary', 'inputs_confirmed' => 1,
                'earnings' => [$pera->earning_type_id => 2000],
                'deductions' => [],
                'lbp_loan_deduction' => 500,
            ])
            ->assertRedirect(route('payroll-runs.show', $run));

        $detail->refresh();
        $this->assertSame('47000.00', (string) $detail->gross_pay);
        $this->assertSame('8508.33', (string) $detail->total_deductions);
        $this->assertSame('38491.67', (string) $detail->nthp_before_lbp);
        $this->assertSame('37991.67', (string) $detail->net_pay);
    }

    public function test_viewer_cannot_prepare_payroll(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->actingAs($viewer)->get(route('payroll-runs.index'))->assertOk();
        $this->actingAs($viewer)->get(route('payroll-runs.create'))->assertForbidden();
    }
}
