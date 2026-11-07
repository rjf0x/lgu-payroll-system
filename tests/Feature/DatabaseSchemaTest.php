<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, string>
     */
    private function documentedTables(): array
    {
        return [
            'users', 'roles', 'permissions', 'user_roles', 'role_permissions',
            'employees', 'departments', 'positions', 'employment_types',
            'employment_records', 'employment_history',
            'salary_schedules', 'salary_grade_steps', 'compensation_profiles',
            'earning_types', 'employee_earning_assignments',
            'attendance_records', 'attendance_adjustments',
            'leave_types', 'leave_balances', 'leave_applications', 'leave_transactions',
            'deduction_types', 'employee_deduction_assignments',
            'tax_brackets', 'contribution_rates',
            'payroll_periods', 'payroll_runs', 'payroll_approvals', 'payroll_details',
            'payroll_earnings', 'payroll_deductions', 'payroll_employer_contributions',
            'payroll_adjustments', 'payslips', 'generated_reports', 'audit_logs',
            'blockchain_anchors', 'blockchain_verification_logs',
        ];
    }

    public function test_all_documented_tables_exist(): void
    {
        foreach ($this->documentedTables() as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing table: {$table}");
        }
    }

    public function test_every_model_resolves_to_an_existing_table(): void
    {
        $models = collect(glob(app_path('Models/*.php')))
            ->map(fn (string $file): string => 'App\\Models\\'.basename($file, '.php'));

        $this->assertNotEmpty($models);

        foreach ($models as $class) {
            $table = (new $class)->getTable();

            $this->assertTrue(Schema::hasTable($table), "{$class} resolves to missing table `{$table}`.");
        }
    }

    public function test_employee_compensation_relationship_chain_resolves(): void
    {
        $department = Department::factory()->create();
        $position = Position::factory()->create([
            'department_id' => $department->department_id,
            'salary_grade' => 15,
        ]);
        $employmentType = EmploymentType::factory()->create([
            'employment_type_name' => EmploymentType::PERMANENT,
        ]);

        $employee = Employee::factory()->create([
            'first_name' => 'Maria',
            'middle_name' => 'Santos',
            'last_name' => 'Dela Cruz',
            'suffix' => null,
        ]);

        $record = EmploymentRecord::factory()->create([
            'employee_id' => $employee->employee_id,
            'department_id' => $department->department_id,
            'position_id' => $position->position_id,
            'employment_type_id' => $employmentType->employment_type_id,
        ]);

        $schedule = SalarySchedule::factory()->create();
        $step = SalaryGradeStep::factory()->create([
            'salary_schedule_id' => $schedule->salary_schedule_id,
            'grade_number' => 15,
            'step_number' => 1,
            'monthly_salary' => 45000.00,
        ]);

        $profile = CompensationProfile::factory()->create([
            'employment_id' => $record->employment_id,
            'grade_step_id' => $step->grade_step_id,
            'basic_rate' => null,
        ]);

        $this->assertSame('Maria Santos Dela Cruz', $employee->full_name);
        $this->assertTrue($record->position->is($position));
        $this->assertTrue($record->employmentType->is($employmentType));
        $this->assertSame('45000.00', $profile->gradeStep->monthly_salary);
        $this->assertFalse((float) $profile->service_fraction !== 1.0);
    }

    public function test_employment_history_uses_documented_table(): void
    {
        $record = EmploymentRecord::factory()->create();

        $this->assertSame('employment_history', (new EmploymentHistory)->getTable());
        $this->assertCount(0, $record->history()->get());
    }

    public function test_user_authenticates_with_username_and_password_hash(): void
    {
        $user = User::factory()->create([
            'username' => 'jdoe',
            'password_hash' => 'secret-password',
        ]);

        $this->assertSame('password_hash', $user->getAuthPasswordName());
        $this->assertTrue(Hash::check('secret-password', $user->getAuthPassword()));

        $this->assertTrue(Auth::attempt([
            'username' => 'jdoe',
            'password' => 'secret-password',
        ]));
    }

    public function test_employee_number_is_unique(): void
    {
        Employee::factory()->create(['employee_number' => 'EMP-00001']);

        $this->expectException(QueryException::class);

        Employee::factory()->create(['employee_number' => 'EMP-00001']);
    }
}
