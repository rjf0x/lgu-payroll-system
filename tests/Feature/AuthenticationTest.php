<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_authenticates_by_username(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Username');
        $response->assertDontSee('Email Address');
    }

    public function test_active_user_can_login_and_the_login_is_audited(): void
    {
        $user = User::factory()->create([
            'username' => 'officer',
            'password_hash' => 'secret-password',
        ]);

        $response = $this->post('/login', [
            'username' => 'officer',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->user_id,
            'action' => 'LOGIN',
            'module' => 'Authentication',
        ]);
    }

    public function test_login_fails_with_a_wrong_password_and_is_audited(): void
    {
        $user = User::factory()->create(['username' => 'officer']);

        $response = $this->from('/login')->post('/login', [
            'username' => 'officer',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->user_id,
            'action' => 'LOGIN_FAILED',
        ]);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $user = User::factory()->inactive()->create([
            'username' => 'inactive',
            'password_hash' => 'secret-password',
        ]);

        $response = $this->from('/login')->post('/login', [
            'username' => 'inactive',
            'password' => 'secret-password',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->assertNull($user->fresh()->last_login_at);
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->user_id,
            'action' => 'LOGIN_BLOCKED',
        ]);
    }

    public function test_user_can_logout_and_the_logout_is_audited(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->user_id,
            'action' => 'LOGOUT',
        ]);
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['username' => 'officer']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => 'officer', 'password' => 'wrong-password']);
        }

        $this->post('/login', ['username' => 'officer', 'password' => 'wrong-password'])
            ->assertStatus(429);
    }
}
