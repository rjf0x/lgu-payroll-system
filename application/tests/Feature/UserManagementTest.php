<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function signInAdministrator(): User
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->post('/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/dashboard');

        return $admin;
    }

    private function accountData(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Fictional Payroll User',
            'email' => 'fictional@example.test',
            'role' => Role::PayrollOfficer->value,
            'is_active' => '1',
            'password' => 'FictionalPass123',
            'password_confirmation' => 'FictionalPass123',
        ], $overrides);
    }

    public static function nonAdministratorRoles(): array
    {
        return [[Role::PayrollOfficer], [Role::Approver], [Role::Viewer]];
    }

    #[DataProvider('nonAdministratorRoles')]
    public function test_non_administrators_cannot_manage_or_escalate_accounts(Role $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $target = User::factory()->create(['role' => Role::Viewer]);
        $this->actingAs($user)->withSession(['auth_version' => 1]);
        $this->get('/users')->assertForbidden();
        $this->get('/users/create')->assertForbidden();
        $this->get('/users/'.$target->id.'/edit')->assertForbidden();
        $this->post('/users', $this->accountData(['role' => 'administrator']))->assertForbidden();
        $this->put('/users/'.$user->id, $this->accountData(['role' => 'administrator']))->assertForbidden();
        $this->put('/users/'.$target->id, $this->accountData())->assertForbidden();
        $this->assertSame($role, $user->fresh()->role);
        $this->assertSame(Role::Viewer, $target->fresh()->role);
        $this->assertDatabaseCount('users', 2);
    }

    public function test_administrator_can_create_each_role_and_passwords_are_hashed(): void
    {
        $this->signInAdministrator();
        $this->get('/users')->assertOk()->assertSee('User accounts');
        $this->get('/users/create')->assertOk()->assertSee('Create account');

        foreach (Role::cases() as $role) {
            $email = $role->value.'@example.test';
            $this->post('/users', $this->accountData(['email' => strtoupper($email), 'role' => $role->value, 'auth_version' => 99]))
                ->assertRedirect('/users')->assertSessionHasNoErrors();
            $account = User::where('email', $email)->firstOrFail();
            $this->assertSame($role, $account->role);
            $this->assertTrue($account->is_active);
            $this->assertSame(1, $account->auth_version);
            $this->assertTrue(Hash::check('FictionalPass123', $account->password));
            $this->assertNotSame('FictionalPass123', $account->password);
            $this->assertArrayNotHasKey('password', $account->toArray());
            $this->get('/users/'.$account->id.'/edit')->assertOk()->assertSee($email)->assertDontSee($account->password);
        }
    }

    public function test_validation_prevents_duplicate_invalid_and_weak_accounts(): void
    {
        $admin = $this->signInAdministrator();
        $this->post('/users', $this->accountData([
            'name' => '', 'email' => strtoupper($admin->email), 'role' => 'owner', 'is_active' => 'yes',
            'password' => 'weak', 'password_confirmation' => 'different',
        ]))->assertSessionHasErrors(['name', 'email', 'role', 'is_active', 'password']);
        $this->post('/users', $this->accountData(['email' => ['bad']]))->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 1);
        $this->post('/users', $this->accountData())->assertRedirect('/users');
        $this->post('/users', $this->accountData())->assertSessionHasErrors('email');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_updates_preserve_blank_password_and_revoke_existing_sessions(): void
    {
        $this->signInAdministrator();
        $target = User::factory()->create(['role' => Role::Viewer]);
        $originalHash = $target->password;
        $this->put('/users/'.$target->id, $this->accountData(['password' => '', 'password_confirmation' => '', 'is_active' => '0']))
            ->assertRedirect('/users')->assertSessionHasNoErrors();
        $target->refresh();
        $this->assertSame('Fictional Payroll User', $target->name);
        $this->assertSame(Role::PayrollOfficer, $target->role);
        $this->assertFalse($target->is_active);
        $this->assertSame(2, $target->auth_version);
        $this->assertSame($originalHash, $target->password);
        $this->assertNull($target->remember_token);

        $this->put('/users/'.$target->id, $this->accountData())->assertRedirect('/users');
        $target->refresh();
        $this->assertTrue($target->is_active);
        $this->assertTrue(Hash::check('FictionalPass123', $target->password));
        $this->actingAs($target)->withSession(['auth_version' => 1]);
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
        $this->post('/login', ['email' => $target->email, 'password' => 'password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $target->email, 'password' => 'FictionalPass123'])->assertRedirect('/dashboard');
    }

    public function test_role_change_applies_on_the_next_request(): void
    {
        $admin = $this->signInAdministrator();
        $this->put('/users/'.$admin->id, $this->accountData(['role' => 'administrator']))->assertRedirect('/users');
        Auth::forgetGuards();
        $this->get('/users')->assertOk();

        // A separate administrator demotes this signed-in account.
        $admin->forceFill(['role' => Role::Viewer, 'auth_version' => $admin->fresh()->auth_version + 1])->save();
        Auth::forgetGuards();
        $this->get('/users')->assertRedirect('/login');
        $this->post('/login', ['email' => 'fictional@example.test', 'password' => 'FictionalPass123'])->assertRedirect('/dashboard');
        $this->get('/users')->assertForbidden();
        $this->get('/payroll-records')->assertOk();
    }

    public function test_administrators_cannot_deactivate_or_demote_themselves(): void
    {
        $admin = $this->signInAdministrator();
        $this->put('/users/'.$admin->id, $this->accountData(['is_active' => '0', 'role' => 'administrator']))->assertSessionHasErrors('role');
        $this->put('/users/'.$admin->id, $this->accountData(['role' => 'viewer']))->assertSessionHasErrors('role');
        $this->assertTrue($admin->fresh()->is_active);
        $this->assertSame(Role::Administrator, $admin->fresh()->role);
        $this->delete('/users/'.$admin->id)->assertStatus(405);
    }

    public function test_initial_admin_command_creates_one_hashed_account_and_refuses_reuse(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Administrator name', 'Fictional Administrator')
            ->expectsQuestion('Administrator email', 'ADMIN@EXAMPLE.TEST')
            ->expectsQuestion('Password (12+ characters, upper/lowercase and numbers)', 'FictionalAdmin123')
            ->expectsQuestion('Confirm password', 'FictionalAdmin123')
            ->expectsOutput('Administrator created. Sign in to manage other accounts.')
            ->assertSuccessful();
        $admin = User::sole();
        $this->assertSame(Role::Administrator, $admin->role);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertTrue(Hash::check('FictionalAdmin123', $admin->password));
        $this->artisan('app:create-admin')->expectsOutput('An administrator already exists. Manage accounts through the application.')->assertFailed();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_initial_admin_command_validates_input_and_default_seeder_creates_no_accounts(): void
    {
        $this->artisan('db:seed')->assertSuccessful();
        $this->assertDatabaseCount('users', 0);
        $this->artisan('app:create-admin')
            ->expectsQuestion('Administrator name', '')
            ->expectsQuestion('Administrator email', 'invalid')
            ->expectsQuestion('Password (12+ characters, upper/lowercase and numbers)', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->assertFailed();
        $this->assertDatabaseCount('users', 0);
    }
}
