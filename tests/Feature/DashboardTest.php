<?php

namespace Tests\Feature;

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

    public function test_multiple_roles_use_admin_dashboard_and_combined_permissions(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        $role = Role::where('role_name', Role::PAYROLL_OFFICER)->firstOrFail();
        $user->roles()->attach($role->role_id, ['assigned_at' => now()]);

        $this->actingAs($user)->get(route('dashboard'))
            ->assertOk()->assertSee('Admin Dashboard')->assertSee('Add user')->assertSee('Prepare payroll');
    }
}
