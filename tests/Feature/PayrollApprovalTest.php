<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
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
use App\Services\PayrollComputationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollApprovalTest extends TestCase
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

        $this->seedEmployment();
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

    private function userWithRoles(string ...$roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);
        }

        return $user;
    }

    private function computedRun(User $creator): PayrollRun
    {
        $period = PayrollPeriod::factory()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'pay_date' => '2025-01-31',
        ]);

        $run = PayrollRun::factory()->create([
            'payroll_period_id' => $period->payroll_period_id,
            'created_by' => $creator->user_id,
        ]);

        app(PayrollComputationService::class)->computeRun($run);

        return $run->fresh();
    }

    public function test_officer_submits_a_computed_run(): void
    {
        $officer = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $run = $this->computedRun($officer);

        $this->actingAs($officer)
            ->post(route('payroll-runs.submit', $run))
            ->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_PENDING_APPROVAL, $run->status);
        $this->assertSame($officer->user_id, $run->submitted_by);
        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $run->payroll_run_id,
            'action' => 'SUBMITTED',
            'acted_by' => $officer->user_id,
        ]);
    }

    public function test_an_independent_approver_can_approve(): void
    {
        $officer = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $approver = $this->userWithRoles(Role::APPROVER);
        $run = $this->computedRun($officer);

        $this->actingAs($officer)->post(route('payroll-runs.submit', $run));

        $this->actingAs($approver)
            ->post(route('payroll-runs.approve', $run), ['remarks' => 'Reviewed and verified.'])
            ->assertRedirect();

        $this->assertSame(PayrollRun::STATUS_APPROVED, $run->fresh()->status);
        $this->assertDatabaseHas('payroll_approvals', [
            'payroll_run_id' => $run->payroll_run_id,
            'action' => 'APPROVED',
            'acted_by' => $approver->user_id,
        ]);
    }

    public function test_the_preparer_cannot_approve_their_own_run_even_with_permission(): void
    {
        // A user holding both roles has payroll.approve, but must be blocked by separation of duties.
        $bothRoles = $this->userWithRoles(Role::PAYROLL_OFFICER, Role::APPROVER);
        $run = $this->computedRun($bothRoles);

        $this->actingAs($bothRoles)->post(route('payroll-runs.submit', $run));

        $this->actingAs($bothRoles)
            ->post(route('payroll-runs.approve', $run))
            ->assertForbidden();

        $this->assertSame(PayrollRun::STATUS_PENDING_APPROVAL, $run->fresh()->status);
    }

    public function test_only_an_approved_run_can_be_finalized(): void
    {
        $officer = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $approver = $this->userWithRoles(Role::APPROVER);
        $run = $this->computedRun($officer);

        $this->actingAs($officer)->post(route('payroll-runs.submit', $run));

        // Not yet approved.
        $this->actingAs($approver)->post(route('payroll-runs.finalize', $run))->assertStatus(422);

        $this->actingAs($approver)->post(route('payroll-runs.approve', $run));
        $this->actingAs($approver)->post(route('payroll-runs.finalize', $run))->assertRedirect();

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_FINALIZED, $run->status);
        $this->assertNotNull($run->finalized_at);
        $this->assertSame($approver->user_id, $run->finalized_by);
    }

    public function test_a_returned_run_can_be_corrected_and_resubmitted(): void
    {
        $officer = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $approver = $this->userWithRoles(Role::APPROVER);
        $run = $this->computedRun($officer);

        $this->actingAs($officer)->post(route('payroll-runs.submit', $run));
        $this->actingAs($approver)->post(route('payroll-runs.return', $run), ['remarks' => 'Please review allowances.']);

        $run->refresh();
        $this->assertSame(PayrollRun::STATUS_RETURNED, $run->status);
        $this->assertTrue($run->isEditable());

        // Prepare again and resubmit.
        $this->actingAs($officer)->post(route('payroll-runs.compute', $run))->assertRedirect();
        $this->actingAs($officer)->post(route('payroll-runs.submit', $run))->assertRedirect();

        $this->assertSame(PayrollRun::STATUS_PENDING_APPROVAL, $run->fresh()->status);
        $this->assertDatabaseCount('payroll_approvals', 3); // SUBMITTED, RETURNED, SUBMITTED
    }

    public function test_a_rejected_run_is_recorded(): void
    {
        $officer = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $approver = $this->userWithRoles(Role::APPROVER);
        $run = $this->computedRun($officer);

        $this->actingAs($officer)->post(route('payroll-runs.submit', $run));
        $this->actingAs($approver)->post(route('payroll-runs.reject', $run), ['remarks' => 'Incorrect figures.']);

        $this->assertSame(PayrollRun::STATUS_REJECTED, $run->fresh()->status);
        $this->assertDatabaseHas('payroll_approvals', ['payroll_run_id' => $run->payroll_run_id, 'action' => 'REJECTED']);
    }

    public function test_admin_cannot_approve_without_the_permission(): void
    {
        $officer = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $admin = $this->userWithRoles(Role::ADMIN);
        $run = $this->computedRun($officer);

        $this->actingAs($officer)->post(route('payroll-runs.submit', $run));

        $this->actingAs($admin)->post(route('payroll-runs.approve', $run))->assertForbidden();
    }
}
