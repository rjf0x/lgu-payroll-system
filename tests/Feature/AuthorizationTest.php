<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    private function userWithRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::where('role_name', $roleName)->firstOrFail();

        $user->roles()->attach($role->role_id, ['assigned_at' => now()]);

        return $user;
    }

    public function test_admin_has_management_permissions_but_cannot_approve(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->assertTrue($admin->can(Permission::USERS_MANAGE));
        $this->assertTrue($admin->can(Permission::ROLES_MANAGE));
        $this->assertFalse($admin->can(Permission::PAYROLL_APPROVE));
        $this->assertFalse($admin->can(Permission::PAYROLL_REJECT));
    }

    public function test_approver_can_approve_but_cannot_manage_users(): void
    {
        $approver = $this->userWithRole(Role::APPROVER);

        $this->assertTrue($approver->can(Permission::PAYROLL_APPROVE));
        $this->assertTrue($approver->can(Permission::PAYROLL_FINALIZE));
        $this->assertFalse($approver->can(Permission::USERS_MANAGE));
    }

    public function test_payroll_officer_can_prepare_and_submit_but_cannot_approve(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->assertTrue($officer->can(Permission::PAYROLL_PREPARE));
        $this->assertTrue($officer->can(Permission::PAYROLL_SUBMIT));
        $this->assertFalse($officer->can(Permission::PAYROLL_APPROVE));
    }

    public function test_viewer_has_read_only_permissions(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->assertTrue($viewer->can(Permission::PAYROLL_VIEW));
        $this->assertFalse($viewer->can(Permission::PAYROLL_PREPARE));
        $this->assertFalse($viewer->can(Permission::EMPLOYEES_MANAGE));
    }

    public function test_role_middleware_allows_a_matching_role(): void
    {
        Route::middleware(['auth', 'role:Admin'])->get('/_test/admin', fn (): string => 'ok');

        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->get('/_test/admin')
            ->assertOk()
            ->assertSee('ok');
    }

    public function test_role_middleware_blocks_a_non_matching_role(): void
    {
        Route::middleware(['auth', 'role:Admin'])->get('/_test/admin', fn (): string => 'ok');

        $viewer = $this->userWithRole(Role::VIEWER);

        $this->actingAs($viewer)
            ->get('/_test/admin')
            ->assertForbidden();
    }

    public function test_role_middleware_requires_authentication(): void
    {
        Route::middleware(['auth', 'role:Admin'])->get('/_test/admin', fn (): string => 'ok');

        $this->get('/_test/admin')->assertRedirect(route('login'));
    }
}
