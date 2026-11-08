<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\SalaryGradeStep;
use App\Models\TaxBracket;
use Database\Seeders\PayrollMasterDataSeeder;
use Database\Seeders\ProductionDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PayrollMasterDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_payroll_master_data_and_employees_with_compensation(): void
    {
        $this->seed(PayrollMasterDataSeeder::class);

        $this->assertDatabaseCount('departments', 4);
        $this->assertDatabaseCount('positions', 6);
        $this->assertDatabaseCount('salary_schedules', 1);
        $this->assertDatabaseCount('salary_grade_steps', 6);
        $this->assertDatabaseCount('earning_types', 6);
        $this->assertDatabaseCount('deduction_types', 24);
        $this->assertDatabaseHas('salary_grade_steps', ['grade_number' => 15, 'step_number' => 2, 'monthly_salary' => 47000]);
        $this->assertDatabaseHas('earning_types', ['earning_name' => 'Clothing Allowance', 'computation_type' => 'MANUAL']);
        $this->assertDatabaseHas('deduction_types', ['deduction_name' => 'MPL', 'deduction_category' => 'GSIS_LOAN']);
        $this->assertDatabaseHas('deduction_types', ['deduction_name' => 'MPL', 'deduction_category' => 'PAGIBIG_LOAN']);
        $this->assertDatabaseHas('deduction_types', ['deduction_name' => 'MONTHLY DUES', 'deduction_category' => 'PLI_DUES']);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('employees', 8);
        $this->assertDatabaseCount('employment_records', 8);
        $this->assertDatabaseCount('compensation_profiles', 8);
        $maria = Employee::where('employee_number', 'EMP-00001')->firstOrFail();
        $this->assertSame('Maria', $maria->first_name);
        $this->assertDatabaseHas('employment_records', ['employee_id' => $maria->getKey(), 'employment_status' => 'ACTIVE']);
        $this->assertDatabaseHas('compensation_profiles', ['pay_basis' => 'MONTHLY', 'basic_rate' => 30000]);
        $this->assertDatabaseHas('compensation_profiles', ['pay_basis' => 'DAILY', 'basic_rate' => 800]);
        $this->assertDatabaseCount('tax_brackets', 6);
        $this->assertDatabaseCount('contribution_rates', 3);
        $this->assertDatabaseHas('tax_brackets', ['tax_type' => 'ANNUAL', 'minimum_income' => 400000, 'base_tax' => 22500, 'marginal_tax_rate' => 0.2]);
        $this->assertDatabaseHas('contribution_rates', ['contribution_type' => ContributionRate::TYPE_GSIS, 'employee_rate' => 0.09, 'employer_rate' => 0.12]);
        $this->assertDatabaseHas('contribution_rates', ['contribution_type' => ContributionRate::TYPE_PHILHEALTH, 'maximum_compensation' => 100000]);
        $this->assertDatabaseHas('contribution_rates', ['contribution_type' => ContributionRate::TYPE_PAGIBIG, 'maximum_compensation' => 10000]);
    }

    public function test_rerunning_preserves_existing_master_data_and_classifications(): void
    {
        $this->seed(PayrollMasterDataSeeder::class);
        $department = Department::where('department_code', 'MO')->firstOrFail();
        $department->update(['department_name' => 'Locally configured office']);
        $step = SalaryGradeStep::where('grade_number', 15)->where('step_number', 2)->firstOrFail();
        $step->update(['monthly_salary' => 48000]);
        $earning = EarningType::where('earning_name', 'PERA')->firstOrFail();
        $earning->update(['is_taxable' => true]);
        $deduction = DeductionType::where('deduction_name', 'MONTHLY DUES')->firstOrFail();
        $deduction->update(['status' => 'INACTIVE']);
        $employee = Employee::where('employee_number', 'EMP-00007')->firstOrFail();
        $employee->update(['address' => 'Updated local address']);
        $profile = CompensationProfile::where('basic_rate', 30000)->firstOrFail();
        $profile->update(['basic_rate' => 31000]);
        $bracket = TaxBracket::where('minimum_income', 400000)->firstOrFail();
        $bracket->update(['base_tax' => 23000]);
        $rate = ContributionRate::where('contribution_type', ContributionRate::TYPE_GSIS)->firstOrFail();
        $rate->update(['employee_rate' => 0.1]);

        $this->seed(PayrollMasterDataSeeder::class);

        $this->assertDatabaseCount('departments', 4);
        $this->assertDatabaseCount('positions', 6);
        $this->assertDatabaseCount('salary_schedules', 1);
        $this->assertDatabaseCount('salary_grade_steps', 6);
        $this->assertDatabaseCount('earning_types', 6);
        $this->assertDatabaseCount('deduction_types', 24);
        $this->assertSame('Locally configured office', $department->fresh()->department_name);
        $this->assertSame('48000.00', $step->fresh()->monthly_salary);
        $this->assertTrue($earning->fresh()->is_taxable);
        $this->assertSame('INACTIVE', $deduction->fresh()->status);
        $this->assertDatabaseCount('employees', 8);
        $this->assertDatabaseCount('employment_records', 8);
        $this->assertDatabaseCount('compensation_profiles', 8);
        $this->assertSame('Updated local address', $employee->fresh()->address);
        $this->assertSame('31000.00', $profile->fresh()->basic_rate);
        $this->assertDatabaseCount('tax_brackets', 6);
        $this->assertDatabaseCount('contribution_rates', 3);
        $this->assertDatabaseHas('tax_brackets', ['tax_bracket_id' => $bracket->getKey(), 'base_tax' => 23000]);
        $this->assertDatabaseHas('contribution_rates', ['contribution_rate_id' => $rate->getKey(), 'employee_rate' => 0.1]);
    }

    public function test_rejects_illustrative_master_data_in_production_before_writing(): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->app->make(PayrollMasterDataSeeder::class)->run();
            $this->fail('Illustrative master data was permitted in production.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('departments', 0);
            $this->assertDatabaseCount('positions', 0);
            $this->assertDatabaseCount('salary_grade_steps', 0);
            $this->assertDatabaseCount('earning_types', 0);
        }
    }

    public function test_production_admin_setup_includes_types_without_illustrative_salaries(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        config(['deployment.bootstrap_admin' => [
            'username' => 'initial.admin',
            'email' => 'admin@example.test',
            'password' => 'Initial-Admin-2026!Safe',
        ]]);

        $this->app->make(ProductionDatabaseSeeder::class)->run();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('earning_types', 6);
        $this->assertDatabaseCount('deduction_types', 24);
        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseCount('positions', 0);
        $this->assertDatabaseCount('salary_grade_steps', 0);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('tax_brackets', 0);
        $this->assertDatabaseCount('contribution_rates', 0);
    }
}
