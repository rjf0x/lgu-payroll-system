<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WorkspaceInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_workspace_navigation_and_shortcuts_preserve_role_boundaries(Role $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
        $page = $this->get('/dashboard')->assertOk()->assertSee('Skip to main content')->assertSee('id="main-content"', false)->assertSee('aria-label="Main navigation"', false)->assertSee('aria-current="page"', false)->assertSee('aria-controls="primary-navigation"', false)->assertSee('Where would you like to start?');
        $page->assertSee(route($role->workspace()));
        if ($role === Role::Administrator) {
            $page->assertSee(route('users.index'))->assertDontSee(route('employees.index'))->assertDontSee(route('payroll-batches.index'));
        } else {
            $page->assertSee(route('employees.index'))->assertSee(route('payroll-batches.index'))->assertDontSee(route('users.index'));
        }
        $this->get('/'.$role->workspace())->assertOk()->assertDontSee('Payroll features will be introduced in later development phases.');
    }

    public function test_login_errors_have_visible_field_feedback_and_keep_passwords_out_of_rejected_input(): void
    {
        $this->from('/login')->post('/login', ['username' => 'unknown_user', 'password' => 'FictionalPrivate123'])->assertRedirect('/login')->assertSessionHasErrors('username');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/login')->assertOk()->assertSee('data-error-summary', false)->assertSee('data-error-field="username"', false)->assertSee('data-field-error="username"', false)->assertSee('aria-invalid="true"', false)->assertSee('unknown_user')->assertDontSee('FictionalPrivate123')->assertSee('autocomplete="current-password"', false);
    }

    public function test_employee_validation_displays_inline_feedback_with_saved_form_values(): void
    {
        $user = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actingAs($user)->withSession(['auth_version' => 1]);
        $this->from('/employees/create')->post('/employees', ['employee_number' => 'DEMO-ERROR'])->assertRedirect('/employees/create')->assertSessionHasErrors('first_name');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/employees/create')->assertOk()->assertSee('DEMO-ERROR')->assertSee('data-error-field="first_name"', false)->assertSee('data-field-error="first_name"', false);
    }
}
