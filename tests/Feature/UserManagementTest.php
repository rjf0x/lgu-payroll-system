<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
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
        $user->roles()->attach(Role::where('role_name', $roleName)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    public function test_admin_can_create_a_user_with_a_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $approverRole = Role::where('role_name', Role::APPROVER)->firstOrFail();

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'newapprover',
                'email' => 'newapprover@lgu.gov.ph',
                'account_status' => User::STATUS_ACTIVE,
                'password' => 'secret-pass',
                'roles' => [$approverRole->role_id],
            ])
            ->assertRedirect();

        $user = User::where('username', 'newapprover')->firstOrFail();

        $this->assertTrue(Hash::check('secret-pass', $user->getAuthPassword()));
        $this->assertTrue($user->hasRole(Role::APPROVER));
    }

    public function test_non_admin_cannot_manage_users(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);

        $this->actingAs($officer)->get(route('users.index'))->assertForbidden();
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->delete(route('users.destroy', $admin))
            ->assertRedirect();

        $this->assertDatabaseHas('users', ['user_id' => $admin->user_id]);
    }

    public function test_creating_a_user_requires_at_least_one_role(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'username' => 'noroles',
                'account_status' => User::STATUS_ACTIVE,
                'password' => 'secret-pass',
                'roles' => [],
            ])
            ->assertSessionHasErrors('roles');
    }
}
