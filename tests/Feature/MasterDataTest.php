<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
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

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, EmploymentTypeSeeder::class]);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public function test_admin_can_create_update_and_delete_a_department(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->post(route('departments.store'), [
                'department_code' => 'MTO',
                'department_name' => 'Municipal Treasurer Office',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['department_code' => 'MTO']);

        $department = Department::where('department_code', 'MTO')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('departments.update', $department), [
                'department_code' => 'MTO-1',
                'department_name' => 'Municipal Treasurer Office',
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseHas('departments', ['department_code' => 'MTO-1']);

        $this->actingAs($admin)
            ->delete(route('departments.destroy', $department))
            ->assertRedirect(route('departments.index'));

        $this->assertDatabaseMissing('departments', ['department_code' => 'MTO-1']);
    }

    public function test_department_code_must_be_unique(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        Department::factory()->create(['department_code' => 'DUP']);

        $this->actingAs($admin)
            ->post(route('departments.store'), [
                'department_code' => 'DUP',
                'department_name' => 'Duplicate',
                'status' => 'ACTIVE',
            ])
            ->assertSessionHasErrors('department_code');
    }

    public function test_viewer_can_view_but_not_create_departments(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->actingAs($viewer)->get(route('departments.index'))->assertOk();
        $this->actingAs($viewer)->get(route('departments.create'))->assertForbidden();
    }

    public function test_admin_can_create_an_employee(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'employee_number' => 'EMP-10001',
                'first_name' => 'Maria',
                'middle_name' => 'Santos',
                'last_name' => 'Dela Cruz',
                'birth_date' => '1990-01-01',
                'sex' => 'FEMALE',
                'civil_status' => 'MARRIED',
                'address' => 'Poblacion',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employees', ['employee_number' => 'EMP-10001']);
    }

    public function test_employment_record_can_be_created(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->department_id]);
        $type = EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->firstOrFail();
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->post(route('employment-records.store'), [
                'employee_id' => $employee->employee_id,
                'department_id' => $department->department_id,
                'position_id' => $position->position_id,
                'employment_type_id' => $type->employment_type_id,
                'start_date' => '2024-01-01',
                'employment_status' => 'ACTIVE',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('employment_records', ['employee_id' => $employee->employee_id]);
    }

    public function test_salary_grade_step_composite_uniqueness_is_enforced(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $schedule = SalarySchedule::factory()->create();

        SalaryGradeStep::factory()->create([
            'salary_schedule_id' => $schedule->salary_schedule_id,
            'grade_number' => 15,
            'step_number' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('salary-grade-steps.store'), [
                'salary_schedule_id' => $schedule->salary_schedule_id,
                'grade_number' => 15,
                'step_number' => 1,
                'monthly_salary' => 45000,
            ])
            ->assertSessionHasErrors('grade_number');
    }

    public function test_employee_soft_delete_archives_the_record(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $employee = Employee::factory()->create();

        $this->actingAs($admin)
            ->delete(route('employees.destroy', $employee))
            ->assertRedirect(route('employees.index'));

        $this->assertSoftDeleted('employees', ['employee_id' => $employee->employee_id]);
    }
}
