<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SalaryGradeStepNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);
    }

    public function test_officer_can_open_every_grade_step_get_route_and_its_schedule_link(): void
    {
        $this->signIn(Role::PAYROLL_OFFICER);
        $step = SalaryGradeStep::factory()->create(['grade_number' => 15, 'step_number' => 2, 'monthly_salary' => '45000.50']);
        $before = $step->fresh()->getAttributes();

        $this->get(route('salary-grade-steps.index', ['salary_schedule_id' => $step->salary_schedule_id]))->assertOk()->assertSee('45,000.50');
        $this->get(route('salary-grade-steps.create', ['salary_schedule_id' => $step->salary_schedule_id]))->assertOk();
        $this->get(route('salary-grade-steps.edit', $step))->assertOk();
        $this->get(route('salary-grade-steps.show', $step))->assertOk()->assertViewIs('salary-grade-steps.show')
            ->assertViewHas('gradeStep', fn (SalaryGradeStep $record): bool => $record->is($step) && $record->relationLoaded('salarySchedule'))
            ->assertSee('SG 15 Step 2')->assertSee('45,000.50')->assertSee($step->salarySchedule->schedule_name)
            ->assertSee(route('salary-schedules.show', $step->salarySchedule));
        $this->get(route('salary-schedules.show', $step->salarySchedule))->assertOk();

        $this->assertSame($before, $step->fresh()->getAttributes());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{string}> */
    public static function readOnlyRoles(): array
    {
        return ['viewer' => [Role::VIEWER], 'approver' => [Role::APPROVER]];
    }

    #[DataProvider('readOnlyRoles')]
    public function test_read_only_roles_can_open_the_detail_without_write_controls(string $role): void
    {
        $this->signIn($role);
        $step = SalaryGradeStep::factory()->create();

        $this->get(route('salary-grade-steps.show', $step))->assertOk()->assertSee($step->salarySchedule->schedule_name)
            ->assertDontSee('href="'.route('salary-grade-steps.edit', $step).'"', false)
            ->assertDontSee('action="'.route('salary-grade-steps.destroy', $step).'"', false);
        $this->get(route('salary-grade-steps.edit', $step))->assertForbidden();
        $this->get(route('salary-grade-steps.create'))->assertForbidden();
    }

    public function test_missing_grade_step_returns_404(): void
    {
        $this->signIn(Role::PAYROLL_OFFICER);

        $this->get(route('salary-grade-steps.show', 999999))->assertNotFound();
    }

    public function test_guest_is_redirected_to_sign_in(): void
    {
        $step = SalaryGradeStep::factory()->create();

        $this->get(route('salary-grade-steps.show', $step))->assertRedirect(route('login'));
    }

    public function test_account_without_employee_view_permission_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create());
        $step = SalaryGradeStep::factory()->create();

        $this->get(route('salary-grade-steps.show', $step))->assertForbidden();
    }

    public function test_schedule_name_is_escaped_on_the_detail_page(): void
    {
        $this->signIn(Role::PAYROLL_OFFICER);
        $schedule = SalarySchedule::factory()->create(['schedule_name' => '<img src=x onerror=alert(1)>']);
        $step = SalaryGradeStep::factory()->create(['salary_schedule_id' => $schedule->getKey()]);

        $this->get(route('salary-grade-steps.show', $step))->assertOk()
            ->assertSee('&lt;img src=x onerror=alert(1)&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false);
    }
}
