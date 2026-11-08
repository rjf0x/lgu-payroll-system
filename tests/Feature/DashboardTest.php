<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $role = Role::where('role_name', $roleName)->firstOrFail();
        $user->roles()->attach($role->role_id, ['assigned_at' => now()]);

        return $user;
    }

    public static function dashboardRoles(): array
    {
        return [
            'admin' => [Role::ADMIN, 'Add user', 'Prepare payroll'],
            'officer' => [Role::PAYROLL_OFFICER, 'Prepare payroll', 'Add user'],
            'approver' => [Role::APPROVER, 'Payroll awaiting your review', 'Prepare payroll'],
            'viewer' => [Role::VIEWER, 'Finalized payroll records', 'Pending Approvals'],
        ];
    }

    #[DataProvider('dashboardRoles')]
    public function test_role_dashboard_displays_authorized_actions(string $roleName, string $visible, string $hidden): void
    {
        $user = $this->userWithRole($roleName);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee($roleName.' Dashboard')
            ->assertSee($visible)->assertDontSee($hidden)
            ->assertSee('No payroll runs to display.');
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_officer_action_queues_are_distinct_and_follow_input_completeness(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        PayrollRun::factory()->create();
        $partial = PayrollRun::factory()->create();
        PayrollDetail::factory()->create(['payroll_run_id' => $partial->getKey()]);
        $missing = PayrollDetail::factory()->create(['payroll_run_id' => $partial->getKey(), 'input_confirmed_at' => null]);
        $ready = PayrollRun::factory()->create();
        PayrollDetail::factory()->count(2)->create(['payroll_run_id' => $ready->getKey()]);
        $blank = PayrollRun::factory()->create();
        PayrollDetail::factory()->create(['payroll_run_id' => $blank->getKey(), 'input_reference' => '']);
        $returned = PayrollRun::factory()->create(['status' => 'RETURNED']);
        PayrollRun::factory()->create(['status' => 'REJECTED']);
        foreach (['PENDING_APPROVAL', 'APPROVED', 'FINALIZED', 'CANCELLED', 'PROCESSING'] as $status) {
            PayrollRun::factory()->create(['status' => $status]);
        }

        $this->actingAs($officer)->get(route('dashboard'))->assertOk()
            ->assertViewHas('nextActions', function (array $actions) use ($ready, $returned): bool {
                $byTitle = collect($actions)->keyBy('title');

                return $byTitle->keys()->all() === ['Payroll preparation', 'Payroll corrections', 'Ready to submit']
                    && $byTitle['Payroll preparation']['count'] === 3
                    && $byTitle['Payroll corrections']['count'] === 2
                    && $byTitle['Ready to submit']['count'] === 1
                    && $byTitle['Ready to submit']['href'] === route('payroll-runs.show', $ready)
                    && $byTitle['Payroll corrections']['href'] === route('payroll-runs.show', $returned);
            })
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['Pending payroll tasks'] === 6);

        $missing->update(['input_confirmed_at' => now()]);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('nextActions', function (array $actions): bool {
            $byTitle = collect($actions)->keyBy('title');

            return $byTitle['Payroll preparation']['count'] === 2
                && $byTitle['Payroll corrections']['count'] === 2
                && $byTitle['Ready to submit']['count'] === 2;
        });
    }

    public function test_empty_officer_queues_explain_the_next_step_and_other_roles_keep_their_actions(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $this->actingAs($officer)->get(route('dashboard'))->assertOk()
            ->assertSee('No draft runs need preparation.')->assertSee('No returned or rejected runs need correction.')
            ->assertSee('No draft runs are ready for submission.')
            ->assertViewHas('nextActions', fn (array $actions): bool => count($actions) === 3 && collect($actions)->sum('count') === 0);

        $approver = $this->userWithRole(Role::APPROVER);
        $this->actingAs($approver)->get(route('dashboard'))->assertOk()
            ->assertViewHas('nextActions', fn (array $actions): bool => array_column($actions, 'title') === ['Independent review', 'Ready to finalize']);
        $viewer = $this->userWithRole(Role::VIEWER);
        $this->actingAs($viewer)->get(route('dashboard'))->assertOk()->assertViewHas('nextActions', []);
    }

    public function test_officer_statistics_count_current_employees_and_actionable_runs_and_refresh_after_changes(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 8));
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $department = Department::factory()->create();
        $employee = Employee::factory()->create();
        EmploymentRecord::factory()->recycle($department)->count(2)->create(['employee_id' => $employee->employee_id, 'department_id' => $department->department_id]);
        foreach ([
            ['employment_status' => EmploymentRecord::STATUS_INACTIVE],
            ['start_date' => '2026-11-09'],
            ['end_date' => '2026-11-07'],
        ] as $attributes) {
            EmploymentRecord::factory()->recycle($department)->create(array_merge(['department_id' => $department->department_id], $attributes));
        }
        $deleted = Employee::factory()->create();
        EmploymentRecord::factory()->recycle($department)->create(['employee_id' => $deleted->employee_id, 'department_id' => $department->department_id]);
        $deleted->delete();
        Department::factory()->create();
        $draft = PayrollRun::factory()->create();
        foreach ([PayrollRun::STATUS_RETURNED, PayrollRun::STATUS_REJECTED, PayrollRun::STATUS_APPROVED,
            PayrollRun::STATUS_PENDING_APPROVAL, PayrollRun::STATUS_PROCESSING, PayrollRun::STATUS_FINALIZED,
            PayrollRun::STATUS_CANCELLED] as $status) {
            PayrollRun::factory()->create(['status' => $status]);
        }

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertViewHas('metrics', [
                'Active employees' => 1, 'Departments' => 2, 'Pending payroll tasks' => 3, 'Finalized runs' => 1,
            ]);

        $draft->update(['status' => PayrollRun::STATUS_FINALIZED]);
        EmploymentRecord::factory()->recycle($department)->create(['department_id' => $department->department_id, 'start_date' => '2026-11-08', 'end_date' => '2026-11-08']);

        $this->get(route('dashboard'))->assertOk()->assertViewHas('metrics', [
            'Active employees' => 2, 'Departments' => 2, 'Pending payroll tasks' => 2, 'Finalized runs' => 2,
        ]);
    }

    public function test_unassigned_account_cannot_see_payroll_or_management_data(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))
            ->assertOk()->assertSee('No role is assigned.')
            ->assertDontSee('Recent payroll runs')->assertDontSee('Active accounts');
    }

    public function test_viewer_dashboard_lists_only_finalized_runs(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        $finalized = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);
        PayrollRun::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertViewHas('runs', fn ($runs): bool => $runs->modelKeys() === [$finalized->payroll_run_id]);
    }

    public function test_approver_queue_excludes_runs_they_prepared_or_submitted(): void
    {
        $user = $this->userWithRole(Role::APPROVER);
        $pending = PayrollRun::factory()->create(['status' => PayrollRun::STATUS_PENDING_APPROVAL]);
        PayrollRun::factory()->create(['status' => PayrollRun::STATUS_PENDING_APPROVAL, 'created_by' => $user->user_id]);
        PayrollRun::factory()->create(['status' => PayrollRun::STATUS_PENDING_APPROVAL, 'submitted_by' => $user->user_id]);
        PayrollRun::factory()->create(['status' => PayrollRun::STATUS_FINALIZED]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertViewHas('runs', fn ($runs): bool => $runs->modelKeys() === [$pending->payroll_run_id]);
    }

    public function test_admin_dashboard_counts_active_accounts_and_role_membership(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        User::factory()->create(['account_status' => User::STATUS_INACTIVE]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('System user overview')
            ->assertViewHas('metrics', fn (array $metrics): bool => $metrics['Active accounts'] === 1);
    }

    public function test_recent_payroll_shows_five_runs_in_processing_chronology_with_coverage_and_finalization_dates(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        $role = Role::where('role_name', Role::PAYROLL_OFFICER)->firstOrFail();
        $user->roles()->attach($role->role_id, ['assigned_at' => now()]);
        $recent = PayrollRun::factory()->create([
            'status' => PayrollRun::STATUS_FINALIZED,
            'run_number' => 7,
            'processed_at' => '2026-01-10 09:00:00',
            'finalized_at' => '2026-02-01 09:00:00',
            'created_at' => '2026-01-01 09:00:00',
        ]);
        $recent->payrollPeriod->update(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $processed = PayrollRun::factory()->create([
            'status' => PayrollRun::STATUS_PROCESSING,
            'processed_at' => '2026-01-20 09:00:00',
            'created_at' => '2026-01-01 09:00:00',
        ]);
        $draft = PayrollRun::factory()->create(['created_at' => '2026-01-15 09:00:00']);
        $older = collect();
        foreach ([14, 13, 12, 11] as $day) {
            $older->push(PayrollRun::factory()->create(['created_at' => '2026-01-'.$day.' 09:00:00']));
        }

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('runs', fn ($runs): bool => $runs->modelKeys() === [
                $recent->payroll_run_id, $processed->payroll_run_id, $draft->payroll_run_id,
                $older[0]->payroll_run_id, $older[1]->payroll_run_id,
            ])
            ->assertSee('Jan 01, 2026')->assertSee('Jan 31, 2026')->assertSee('Feb 01, 2026')
            ->assertSee('Not finalized')->assertSee('View records')->assertSee('View All Payroll Runs')
            ->assertSee('Browse older payroll records');
    }

    public function test_multiple_roles_use_admin_dashboard_and_combined_permissions(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        $role = Role::where('role_name', Role::PAYROLL_OFFICER)->firstOrFail();
        $user->roles()->attach($role->role_id, ['assigned_at' => now()]);

        User::factory()->count(2)->create(['account_status' => User::STATUS_INACTIVE]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('Admin Dashboard')->assertSee('Add user')->assertSee('Prepare payroll')
            ->assertSee('Total unique users')
            ->assertSee('Accounts with multiple roles appear in each role.')
            ->assertViewHas('totalUniqueUsers', 3)
            ->assertViewHas('roles', fn ($roles): bool => $roles->sum('users_count') === 2);
    }
}
