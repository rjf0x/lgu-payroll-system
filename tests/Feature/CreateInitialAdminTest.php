<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateInitialAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_an_active_administrator_with_local_payroll_setup(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Username', 'first.admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', 'Initial-Admin-2026!Safe')
            ->expectsQuestion('Confirm password', 'Initial-Admin-2026!Safe')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $admin = User::firstOrFail();
        $this->assertSame('first.admin', $admin->username);
        $this->assertSame('admin@example.test', $admin->email);
        $this->assertTrue($admin->isActive());
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue(Hash::check('Initial-Admin-2026!Safe', $admin->password_hash));
        $this->assertDatabaseCount('employees', 8);
        $this->assertDatabaseCount('employment_records', 8);
        $this->assertDatabaseCount('compensation_profiles', 8);
        $this->assertDatabaseCount('departments', 4);
        $this->assertDatabaseCount('positions', 6);
        $this->assertDatabaseCount('salary_grade_steps', 6);
        $this->assertDatabaseCount('earning_types', 6);
        $this->assertDatabaseCount('deduction_types', 24);
        $this->assertDatabaseCount('tax_brackets', 6);
        $this->assertDatabaseCount('contribution_rates', 3);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PRODUCTION_BOOTSTRAPPED']);
    }

    public function test_refuses_to_change_existing_accounts(): void
    {
        $user = User::factory()->create();
        $originalPassword = $user->password_hash;

        $this->artisan('app:create-admin')
            ->expectsOutput('An account already exists. Use User Accounts to manage users.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame($originalPassword, $user->fresh()->password_hash);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_rejects_mismatched_passwords_without_writing_data(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Username', 'first.admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', 'Initial-Admin-2026!Safe')
            ->expectsQuestion('Confirm password', 'Different-Admin-2026!Safe')
            ->expectsOutput('Passwords do not match. No account was created.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_rejects_weak_passwords_without_writing_data(): void
    {
        $this->artisan('app:create-admin')
            ->expectsQuestion('Username', 'first.admin')
            ->expectsQuestion('Email', 'admin@example.test')
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 0);
    }

    public function test_requires_interactive_input(): void
    {
        $this->artisan('app:create-admin', ['--no-interaction' => true])
            ->expectsOutput('Run this command interactively to enter administrator credentials.')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }
}
