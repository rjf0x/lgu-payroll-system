<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveAccountTest extends TestCase
{
    use RefreshDatabase;

    public function test_deactivated_account_is_logged_out_and_cannot_reuse_its_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_context' => 'payroll'])->get(route('dashboard'))->assertOk();
        $user->update(['account_status' => User::STATUS_INACTIVE]);

        $this->get(route('dashboard'))->assertRedirect(route('login'))
            ->assertSessionHasErrors(['username' => 'Your account is inactive. Contact an administrator.'])
            ->assertSessionMissing('private_context');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->user_id, 'action' => 'LOGOUT']);
        $this->get(route('employees.index'))->assertRedirect(route('login'));
    }

    public function test_inactive_account_cannot_write_even_with_an_existing_session(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->post(route('employees.store'), [
            'employee_number' => 'BLOCKED-001', 'first_name' => 'Blocked', 'last_name' => 'Account',
            'birth_date' => '1990-01-01', 'sex' => 'MALE', 'address' => 'Test address',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, Employee::count());
        $this->assertGuest();
    }

    public function test_inactive_json_request_is_denied_and_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)->getJson(route('dashboard'))->assertForbidden()
            ->assertJsonPath('message', 'Your account is inactive. Contact an administrator.');

        $this->assertGuest();
    }
}
