<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompensationProfileValidationTest extends TestCase
{
    use RefreshDatabase;

    private EmploymentRecord $record;

    private SalaryGradeStep $step15;

    private SalaryGradeStep $step8;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, EmploymentTypeSeeder::class]);

        $department = Department::factory()->create();
        $position = Position::factory()->create([
            'department_id' => $department->department_id,
            'salary_grade' => 15,
        ]);
        $type = EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->firstOrFail();
        $employee = Employee::factory()->create();

        $this->record = EmploymentRecord::factory()->create([
            'employee_id' => $employee->employee_id,
            'department_id' => $department->department_id,
            'position_id' => $position->position_id,
            'employment_type_id' => $type->employment_type_id,
        ]);

        $schedule = SalarySchedule::factory()->create([
            'effective_from' => '2024-01-01',
            'effective_to' => null,
            'status' => SalarySchedule::STATUS_ACTIVE,
        ]);

        $this->step15 = SalaryGradeStep::factory()->create([
            'salary_schedule_id' => $schedule->salary_schedule_id,
            'grade_number' => 15,
            'step_number' => 1,
            'monthly_salary' => 45000,
        ]);

        $this->step8 = SalaryGradeStep::factory()->create([
            'salary_schedule_id' => $schedule->salary_schedule_id,
            'grade_number' => 8,
            'step_number' => 1,
            'monthly_salary' => 22000,
        ]);
    }

    private function officer(): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public function test_grade_step_matching_the_position_grade_is_accepted(): void
    {
        $this->actingAs($this->officer())
            ->post(route('compensation-profiles.store'), [
                'basis' => 'GRADE_STEP',
                'employment_id' => $this->record->employment_id,
                'grade_step_id' => $this->step15->grade_step_id,
                'pay_basis' => CompensationProfile::BASIS_MONTHLY,
                'service_fraction' => '1.0000',
                'effective_from' => '2024-02-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('compensation_profiles', [
            'employment_id' => $this->record->employment_id,
            'grade_step_id' => $this->step15->grade_step_id,
        ]);
    }

    public function test_grade_step_not_matching_the_position_grade_is_rejected(): void
    {
        $this->actingAs($this->officer())
            ->post(route('compensation-profiles.store'), [
                'basis' => 'GRADE_STEP',
                'employment_id' => $this->record->employment_id,
                'grade_step_id' => $this->step8->grade_step_id,
                'pay_basis' => CompensationProfile::BASIS_MONTHLY,
                'service_fraction' => '1.0000',
                'effective_from' => '2024-02-01',
            ])
            ->assertSessionHasErrors('grade_step_id');
    }

    public function test_direct_rate_requires_a_basic_rate(): void
    {
        $this->actingAs($this->officer())
            ->post(route('compensation-profiles.store'), [
                'basis' => 'DIRECT_RATE',
                'employment_id' => $this->record->employment_id,
                'pay_basis' => CompensationProfile::BASIS_DAILY,
                'service_fraction' => '1.0000',
                'effective_from' => '2024-02-01',
            ])
            ->assertSessionHasErrors('basic_rate');
    }

    public function test_effective_date_before_the_schedule_is_rejected(): void
    {
        $this->actingAs($this->officer())
            ->post(route('compensation-profiles.store'), [
                'basis' => 'GRADE_STEP',
                'employment_id' => $this->record->employment_id,
                'grade_step_id' => $this->step15->grade_step_id,
                'pay_basis' => CompensationProfile::BASIS_MONTHLY,
                'service_fraction' => '1.0000',
                'effective_from' => '2023-12-01',
            ])
            ->assertSessionHasErrors('effective_from');
    }

    public function test_overlapping_compensation_period_is_rejected(): void
    {
        CompensationProfile::factory()->create([
            'employment_id' => $this->record->employment_id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-06-30',
        ]);

        $this->actingAs($this->officer())
            ->post(route('compensation-profiles.store'), [
                'basis' => 'GRADE_STEP',
                'employment_id' => $this->record->employment_id,
                'grade_step_id' => $this->step15->grade_step_id,
                'pay_basis' => CompensationProfile::BASIS_MONTHLY,
                'service_fraction' => '1.0000',
                'effective_from' => '2024-06-01',
            ])
            ->assertSessionHasErrors('effective_from');
    }

    public function test_non_overlapping_later_period_is_accepted(): void
    {
        CompensationProfile::factory()->create([
            'employment_id' => $this->record->employment_id,
            'effective_from' => '2024-01-01',
            'effective_to' => '2024-06-30',
        ]);

        $this->actingAs($this->officer())
            ->post(route('compensation-profiles.store'), [
                'basis' => 'GRADE_STEP',
                'employment_id' => $this->record->employment_id,
                'grade_step_id' => $this->step15->grade_step_id,
                'pay_basis' => CompensationProfile::BASIS_MONTHLY,
                'service_fraction' => '1.0000',
                'effective_from' => '2024-07-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('compensation_profiles', 2);
    }
}
