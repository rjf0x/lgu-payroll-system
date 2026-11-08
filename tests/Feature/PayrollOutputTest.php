<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollOutputTest extends TestCase
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

    private function officer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function makeRun(string $status = PayrollRun::STATUS_DRAFT): PayrollRun
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

        $run->update(['status' => $status]);

        return $run->fresh();
    }

    public function test_register_renders_employee_rows(): void
    {
        $run = $this->makeRun();

        $this->actingAs($this->officer())
            ->get(route('payroll-runs.register', $run))
            ->assertOk()
            ->assertSee('Employee Number')
            ->assertSee('Net Take Home Pay');
    }

    public function test_register_csv_export_records_a_generated_report(): void
    {
        $run = $this->makeRun();

        $this->actingAs($this->officer())
            ->get(route('payroll-runs.register.export', $run))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=utf-8');

        $this->assertDatabaseHas('generated_reports', [
            'report_type' => 'PAYROLL_REGISTER',
        ]);
    }

    public function test_payslips_cannot_be_generated_for_a_draft_run(): void
    {
        $run = $this->makeRun(PayrollRun::STATUS_DRAFT);

        $this->actingAs($this->officer())
            ->post(route('payroll-runs.payslips.generate', $run))
            ->assertRedirect();

        $this->assertDatabaseCount('payslips', 0);
    }

    public function test_payslips_are_generated_and_published_for_an_approved_run(): void
    {
        $officer = $this->officer();
        $run = $this->makeRun(PayrollRun::STATUS_APPROVED);

        $this->actingAs($officer)->post(route('payroll-runs.payslips.generate', $run))->assertRedirect();

        $detailCount = $run->details()->count();
        $this->assertDatabaseCount('payslips', $detailCount);

        $payslip = Payslip::firstOrFail();
        $this->assertSame('GENERATED', $payslip->publication_status);

        $this->actingAs($officer)
            ->get(route('payslips.show', $payslip))
            ->assertOk()
            ->assertSee('Net Take-Home Pay');

        $this->actingAs($officer)
            ->post(route('payslips.publish', $payslip))
            ->assertRedirect();

        $this->assertSame('PUBLISHED', $payslip->fresh()->publication_status);
        $this->assertNotNull($payslip->fresh()->published_at);
    }

    public function test_a_user_without_payroll_view_cannot_open_the_register(): void
    {
        $run = $this->makeRun();
        $user = User::factory()->create(); // no roles

        $this->actingAs($user)->get(route('payroll-runs.register', $run))->assertForbidden();
    }
}
