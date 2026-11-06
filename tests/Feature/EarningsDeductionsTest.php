<?php

namespace Tests\Feature;

use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EarningsDeductionsTest extends TestCase
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

    private function employmentRecord(): EmploymentRecord
    {
        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->department_id]);
        $type = EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->firstOrFail();

        return EmploymentRecord::factory()->create([
            'employee_id' => Employee::factory()->create()->employee_id,
            'department_id' => $department->department_id,
            'position_id' => $position->position_id,
            'employment_type_id' => $type->employment_type_id,
        ]);
    }

    public function test_officer_can_create_an_earning_type(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)
            ->post(route('earning-types.store'), [
                'earning_name' => 'Longevity Pay',
                'earning_category' => 'ALLOWANCE',
                'computation_type' => 'MANUAL',
                'is_taxable' => 1,
                'is_recurring' => 1,
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('earning-types.index'));

        $this->assertDatabaseHas('earning_types', ['earning_name' => 'Longevity Pay']);
    }

    public function test_earning_assignment_requires_an_amount_or_percentage(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $record = $this->employmentRecord();
        $earningType = EarningType::factory()->create();

        $this->actingAs($officer)
            ->post(route('employee-earning-assignments.store'), [
                'employment_id' => $record->employment_id,
                'earning_type_id' => $earningType->earning_type_id,
                'frequency' => 'MONTHLY',
                'effective_from' => '2025-01-01',
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_earning_assignment_with_an_amount_is_created(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $record = $this->employmentRecord();
        $earningType = EarningType::factory()->create();

        $this->actingAs($officer)
            ->post(route('employee-earning-assignments.store'), [
                'employment_id' => $record->employment_id,
                'earning_type_id' => $earningType->earning_type_id,
                'amount' => 2000,
                'frequency' => 'MONTHLY',
                'effective_from' => '2025-01-01',
            ])
            ->assertRedirect(route('employee-earning-assignments.index'));

        $this->assertDatabaseHas('employee_earning_assignments', [
            'employment_id' => $record->employment_id,
            'earning_type_id' => $earningType->earning_type_id,
            'amount' => 2000,
        ]);
    }

    public function test_contribution_rate_validates_rates_and_range(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)
            ->post(route('contribution-rates.store'), [
                'contribution_type' => 'GSIS',
                'minimum_compensation' => 5000,
                'maximum_compensation' => 1000,
                'employee_rate' => 1.5,
                'employer_rate' => 0.12,
                'effective_from' => '2025-01-01',
            ])
            ->assertSessionHasErrors(['maximum_compensation', 'employee_rate']);
    }

    public function test_tax_bracket_is_created_and_effective_dated(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)
            ->post(route('tax-brackets.store'), [
                'tax_type' => 'ANNUAL',
                'pay_frequency' => 'ANNUAL',
                'minimum_income' => 400000,
                'maximum_income' => 800000,
                'base_tax' => 22500,
                'marginal_tax_rate' => 0.20,
                'effective_from' => '2025-01-01',
            ])
            ->assertRedirect(route('tax-brackets.index'));

        $this->assertDatabaseHas('tax_brackets', [
            'minimum_income' => 400000,
            'base_tax' => 22500,
        ]);
    }

    public function test_viewer_can_list_but_not_create_configuration(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->actingAs($viewer)->get(route('earning-types.index'))->assertOk();
        $this->actingAs($viewer)->get(route('earning-types.create'))->assertForbidden();
        $this->actingAs($viewer)->get(route('contribution-rates.create'))->assertForbidden();
    }

    public function test_deduction_type_can_be_created(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)
            ->post(route('deduction-types.store'), [
                'deduction_name' => 'Provident Fund',
                'deduction_category' => 'PLI_DUES',
                'computation_type' => 'FIXED',
                'is_statutory' => 0,
                'is_pre_tax' => 0,
                'status' => 'ACTIVE',
            ])
            ->assertRedirect(route('deduction-types.index'));

        $this->assertDatabaseHas('deduction_types', ['deduction_name' => 'Provident Fund']);
    }

    public function test_deduction_assignment_requires_amount_or_percentage(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $record = $this->employmentRecord();
        $deductionType = DeductionType::factory()->create();

        $this->actingAs($officer)
            ->post(route('employee-deduction-assignments.store'), [
                'employment_id' => $record->employment_id,
                'deduction_type_id' => $deductionType->deduction_type_id,
                'status' => 'ACTIVE',
                'effective_from' => '2025-01-01',
            ])
            ->assertSessionHasErrors('amount');
    }
}
