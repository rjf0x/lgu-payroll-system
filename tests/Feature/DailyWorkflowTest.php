<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\DeductionType;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmploymentRecord;
use App\Models\PayrollRun;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DailyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRoles(string ...$roleNames): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::whereIn('role_name', $roleNames)->pluck('role_id')->all(), ['assigned_at' => now()]);

        return $user;
    }

    private function document(string $html): DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($document);
    }

    public static function payrollStages(): array
    {
        return [
            ['DRAFT', 'Compute payroll to create'], ['RETURNED', 'Compute payroll to create'],
            ['REJECTED', 'Compute payroll to create'], ['PENDING_APPROVAL', 'awaiting an independent reviewer'],
            ['APPROVED', 'awaits a user with finalization permission'], ['FINALIZED', 'Payroll details are locked'],
            ['PROCESSING', 'computation is in progress'], ['CANCELLED', 'cannot proceed'],
        ];
    }

    #[DataProvider('payrollStages')]
    public function test_payroll_guidance_matches_the_run_state(string $status, string $guidance): void
    {
        $user = $this->userWithRoles(Role::PAYROLL_OFFICER);
        $run = PayrollRun::factory()->create(['status' => $status]);

        $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk()->assertSee($guidance);
    }

    public function test_preparer_is_told_why_they_cannot_review_their_own_run(): void
    {
        $user = $this->userWithRoles(Role::PAYROLL_OFFICER, Role::APPROVER);
        $run = PayrollRun::factory()->create(['created_by' => $user->user_id, 'status' => 'PENDING_APPROVAL']);

        $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk()->assertSee('Another user must review it');
    }

    public static function invalidDecisions(): array
    {
        return [
            ['return', [], 'remarks'], ['reject', ['remarks' => '   '], 'remarks'],
            ['approve', ['remarks' => ['unsafe']], 'remarks'], ['approve', ['remarks' => str_repeat('x', 2001)], 'remarks'],
        ];
    }

    #[DataProvider('invalidDecisions')]
    public function test_invalid_decisions_do_not_change_payroll_or_create_approval_history(string $action, array $payload, string $field): void
    {
        $user = $this->userWithRoles(Role::APPROVER);
        $run = PayrollRun::factory()->create(['status' => 'PENDING_APPROVAL']);

        $this->actingAs($user)->postJson(route('payroll-runs.'.$action, $run), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame('PENDING_APPROVAL', $run->fresh()->status);
        $this->assertDatabaseCount('payroll_approvals', 0);
    }

    public function test_return_only_reviewer_sees_only_their_permitted_decision_and_can_record_a_reason(): void
    {
        $user = $this->userWithRoles();
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::whereIn('permission_name', ['payroll.view', 'payroll.review'])->pluck('permission_id')->all(), ['created_at' => now()]);
        $user->roles()->attach($role, ['assigned_at' => now()]);
        $run = PayrollRun::factory()->create(['status' => 'PENDING_APPROVAL']);

        $response = $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk();
        $document = $this->document($response->getContent());
        $this->assertSame(1, $document->query('//button[@formaction="'.route('payroll-runs.return', $run).'"]')->length);
        $this->assertSame(0, $document->query('//button[@formaction="'.route('payroll-runs.approve', $run).'"]')->length);
        $this->assertSame(0, $document->query('//button[@formaction="'.route('payroll-runs.reject', $run).'"]')->length);

        $this->post(route('payroll-runs.return', $run), ['remarks' => 'Correct the approved payroll inputs.'])->assertRedirect();
        $this->assertDatabaseHas('payroll_approvals', ['payroll_run_id' => $run->payroll_run_id, 'action' => 'RETURNED', 'remarks' => 'Correct the approved payroll inputs.']);
        $this->assertSame('RETURNED', $run->fresh()->status);
    }

    public function test_confirmation_identifies_the_payroll_period_and_locking_consequence(): void
    {
        $user = $this->userWithRoles(Role::APPROVER);
        $run = PayrollRun::factory()->create(['status' => 'APPROVED']);

        $response = $this->actingAs($user)->get(route('payroll-runs.show', $run))->assertOk();

        $document = $this->document($response->getContent());
        $forms = $document->query('//form[@action="'.route('payroll-runs.finalize', $run).'"][@data-confirm]');
        $this->assertSame(1, $forms->length);
        $this->assertStringContainsString($run->payrollPeriod->period_name, $forms->item(0)->getAttribute('data-confirm-context'));
        $this->assertStringContainsString('Net pay ₱', $forms->item(0)->getAttribute('data-confirm-context'));
        $this->assertStringContainsString('locks this run', $forms->item(0)->getAttribute('data-confirm-description'));
        $this->assertSame(1, $document->query('//dialog[@id="confirmation-dialog" and @aria-labelledby="confirmation-title"]')->length);
    }

    public function test_dashboard_prioritizes_corrections_and_combines_roles_without_self_review(): void
    {
        $user = $this->userWithRoles(Role::ADMIN, Role::PAYROLL_OFFICER, Role::APPROVER);
        $returned = PayrollRun::factory()->create(['status' => 'RETURNED']);
        $draft = PayrollRun::factory()->create(['status' => 'DRAFT']);
        $pending = PayrollRun::factory()->create(['status' => 'PENDING_APPROVAL']);
        PayrollRun::factory()->create(['status' => 'PENDING_APPROVAL', 'created_by' => $user->user_id]);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('Manage user access')
            ->assertViewHas('nextActions', function (array $actions) use ($draft, $returned, $pending): bool {
                $byTitle = collect($actions)->keyBy('title');

                return $byTitle['Payroll preparation']['count'] === 1
                    && $byTitle['Payroll preparation']['href'] === route('payroll-runs.show', $draft)
                    && $byTitle['Payroll corrections']['count'] === 1
                    && $byTitle['Payroll corrections']['href'] === route('payroll-runs.show', $returned)
                    && $byTitle['Independent review']['count'] === 1
                    && $byTitle['Independent review']['href'] === route('payroll-runs.show', $pending);
            });
    }

    public function test_employee_workspace_shows_only_the_selected_employees_compensation_and_hides_restricted_modules(): void
    {
        $user = $this->userWithRoles(Role::VIEWER);
        $record = EmploymentRecord::factory()->create();
        $profile = CompensationProfile::factory()->create(['employment_id' => $record->employment_id]);
        CompensationProfile::factory()->create();

        $this->actingAs($user)->get(route('employees.show', $record->employee))->assertOk()
            ->assertViewHas('compensation', fn ($profiles): bool => $profiles->modelKeys() === [$profile->compensation_id])
            ->assertDontSee('id="attendance"', false)->assertDontSee('id="leave"', false)
            ->assertDontSee('Add compensation')->assertDontSee('Add employment');
    }

    public static function relatedForms(): array
    {
        return [
            ['employment-records', 'employmentRecord', 'employee_id'],
            ['compensation-profiles', 'profile', 'employment_id'],
            ['employee-earning-assignments', 'assignment', 'employment_id'],
            ['employee-deduction-assignments', 'assignment', 'employment_id'],
        ];
    }

    #[DataProvider('relatedForms')]
    public function test_related_creation_prefills_the_employee_and_retains_a_safe_return_path(string $resource, string $variable, string $field): void
    {
        $user = $this->userWithRoles(Role::ADMIN);
        $record = EmploymentRecord::factory()->create();
        $id = $field === 'employee_id' ? $record->employee_id : $record->employment_id;

        $this->actingAs($user)->get(route($resource.'.create', [$field => $id, 'employee_workspace' => 1]))
            ->assertOk()->assertViewHas($variable, fn ($model): bool => (int) $model->{$field} === (int) $id)
            ->assertViewHas('workspaceEmployee', fn ($employee): bool => $employee->employee_id === $record->employee_id)
            ->assertSee('name="employee_workspace" value="1"', false)->assertSee('Back to employee workspace');
    }

    public function test_earning_save_and_filtered_list_keep_employee_context(): void
    {
        $user = $this->userWithRoles(Role::ADMIN);
        $record = EmploymentRecord::factory()->create();
        $earning = EarningType::factory()->create();

        $this->actingAs($user)->post(route('employee-earning-assignments.store'), [
            'employment_id' => $record->employment_id, 'earning_type_id' => $earning->earning_type_id,
            'amount' => 250, 'frequency' => 'MONTHLY', 'effective_from' => '2026-11-01', 'employee_workspace' => 1,
        ])->assertRedirect(route('employees.show', $record->employee_id).'#earnings');

        $this->assertDatabaseHas('employee_earning_assignments', ['employment_id' => $record->employment_id, 'amount' => 250]);
        $this->get(route('employee-earning-assignments.index', ['employee_id' => $record->employee_id]))
            ->assertOk()->assertViewHas('assignments', fn ($items): bool => $items->count() === 1);
        $other = Employee::factory()->create();
        $this->get(route('employee-earning-assignments.index', ['employee_id' => $other->employee_id]))
            ->assertOk()->assertViewHas('assignments', fn ($items): bool => $items->isEmpty());
    }

    public function test_invalid_employee_prefill_is_rejected_without_rendering_another_employee(): void
    {
        $user = $this->userWithRoles(Role::ADMIN);

        $this->actingAs($user)->getJson(route('employment-records.create', ['employee_id' => 999999, 'employee_workspace' => 1]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');
    }

    public function test_compensation_save_and_update_return_to_the_employee_workspace(): void
    {
        $user = $this->userWithRoles(Role::ADMIN);
        $record = EmploymentRecord::factory()->create();
        $payload = [
            'employment_id' => $record->employment_id, 'basis' => 'DIRECT_RATE', 'pay_basis' => 'MONTHLY',
            'basic_rate' => 45000, 'service_fraction' => 1, 'effective_from' => '2026-11-01', 'employee_workspace' => 1,
        ];

        $this->actingAs($user)->post(route('compensation-profiles.store'), $payload)
            ->assertRedirect(route('employees.show', $record->employee_id).'#compensation');
        $profile = CompensationProfile::firstOrFail();
        $this->put(route('compensation-profiles.update', $profile), array_replace($payload, ['basic_rate' => 46000]))
            ->assertRedirect(route('employees.show', $record->employee_id).'#compensation');

        $this->assertSame('46000.00', $profile->fresh()->basic_rate);
        $this->get(route('compensation-profiles.index', ['employee_id' => $record->employee_id]))
            ->assertOk()->assertSee('Back to employee workspace')->assertViewHas('profiles', fn ($items): bool => $items->count() === 1);
    }

    public function test_deduction_save_and_update_return_to_the_employee_workspace(): void
    {
        $user = $this->userWithRoles(Role::ADMIN);
        $record = EmploymentRecord::factory()->create();
        $type = DeductionType::factory()->create();
        $payload = ['employment_id' => $record->employment_id, 'deduction_type_id' => $type->deduction_type_id, 'amount' => 100, 'status' => 'ACTIVE', 'effective_from' => '2026-11-01', 'employee_workspace' => 1];

        $this->actingAs($user)->post(route('employee-deduction-assignments.store'), $payload)
            ->assertRedirect(route('employees.show', $record->employee_id).'#deductions');
        $assignment = EmployeeDeductionAssignment::firstOrFail();
        $this->put(route('employee-deduction-assignments.update', $assignment), array_replace($payload, ['amount' => 150]))
            ->assertRedirect(route('employees.show', $record->employee_id).'#deductions');

        $this->assertSame('150.00', $assignment->fresh()->amount);
    }

    public function test_employment_creation_returns_to_the_selected_employee(): void
    {
        $user = $this->userWithRoles(Role::ADMIN);
        $reference = EmploymentRecord::factory()->create();
        $employee = Employee::factory()->create();

        $this->actingAs($user)->post(route('employment-records.store'), [
            'employee_id' => $employee->employee_id, 'department_id' => $reference->department_id,
            'position_id' => $reference->position_id, 'employment_type_id' => $reference->employment_type_id,
            'start_date' => '2026-11-01', 'employment_status' => 'ACTIVE', 'employee_workspace' => 1,
        ])->assertRedirect(route('employees.show', $employee).'#employment');

        $this->assertDatabaseHas('employment_records', ['employee_id' => $employee->employee_id, 'employment_status' => 'ACTIVE']);
    }

    public function test_failed_related_submission_keeps_the_selected_employment_and_return_path(): void
    {
        $user = $this->userWithRoles(Role::ADMIN);
        $record = EmploymentRecord::factory()->create();
        $url = route('employee-earning-assignments.create', ['employment_id' => $record->employment_id, 'employee_workspace' => 1]);

        $this->actingAs($user)->from($url)->followingRedirects()->post(route('employee-earning-assignments.store'), [
            'employment_id' => $record->employment_id, 'frequency' => 'MONTHLY', 'amount' => 250,
            'effective_from' => '2026-11-01', 'employee_workspace' => 1,
        ])->assertOk()->assertSee('Back to employee workspace')
            ->assertSee('name="employee_workspace" value="1"', false)
            ->assertViewHas('workspaceEmployee', fn ($employee): bool => $employee->employee_id === $record->employee_id);

        $this->assertDatabaseCount('employee_earning_assignments', 0);
    }

    public function test_directory_search_and_page_are_preserved_on_the_employee_return_link(): void
    {
        $user = $this->userWithRoles(Role::VIEWER);
        $employee = Employee::factory()->create();

        $response = $this->actingAs($user)->get(route('employees.show', [$employee, 'q' => 'finance', 'page' => 2]))->assertOk();
        $document = $this->document($response->getContent());

        $this->assertSame(1, $document->query('//a[@href="'.route('employees.index', ['q' => 'finance', 'page' => 2]).'"]')->length);
    }
}
