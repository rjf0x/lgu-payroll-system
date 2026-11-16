<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
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

    public function test_login_uses_saved_organization_identity_and_support_contact(): void
    {
        SystemSetting::query()->where('setting_key', 'system_name')->update(['value' => 'Municipal Payroll']);
        SystemSetting::query()->where('setting_key', 'organization_name')->update(['value' => 'Municipality of Sample']);
        SystemSetting::query()->where('setting_key', 'support_email')->update(['value' => 'payroll-help@example.test']);

        $this->get(route('login'))->assertOk()
            ->assertSee('Municipal Payroll')->assertSee('Municipality of Sample')
            ->assertSee('mailto:payroll-help@example.test')
            ->assertDontSee('Contact your system administrator for account access or a password reset.');
    }

    public function test_failed_sign_in_keeps_username_without_rendering_the_password(): void
    {
        User::factory()->create(['username' => 'officer']);

        $this->from(route('login'))->post(route('login.store'), [
            'username' => 'officer', 'password' => 'never-render-this-password',
        ])->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk()->assertSee('Unable to sign in')
            ->assertSee('value="officer"', false)->assertSee('aria-invalid="true"', false)
            ->assertSee('data-error-summary', false)->assertDontSee('never-render-this-password');
    }

    public function test_empty_login_shows_linked_summary_and_inline_errors_for_both_fields(): void
    {
        $this->followingRedirects()->from(route('login'))->post(route('login.store'), [])
            ->assertOk()
            ->assertSee('Unable to sign in')
            ->assertSee('href="#username"', false)
            ->assertSee('href="#password"', false)
            ->assertSee('aria-describedby="username-error"', false)
            ->assertSee('aria-describedby="password-error"', false)
            ->assertSee('id="username-error"', false)
            ->assertSee('id="password-error"', false)
            ->assertSee('The username field is required.')
            ->assertSee('The password field is required.');
        $this->assertGuest();
    }

    public function test_remember_me_restores_login_without_an_existing_session(): void
    {
        $user = User::factory()->create(['password_hash' => 'secret-password']);
        $cookieName = $this->app['auth']->guard()->getRecallerName();

        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'secret-password',
            'remember' => '1',
        ]);

        $response->assertRedirect(route('dashboard'))->assertCookie($cookieName);
        $rememberCookie = $response->getCookie($cookieName);
        $this->app['session']->invalidate();
        $this->app['session']->save();
        $this->app['auth']->forgetGuards();

        $this->withCookie($cookieName, $rememberCookie->getValue())
            ->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_without_remember_me_does_not_restore_an_ended_session(): void
    {
        $user = User::factory()->create(['password_hash' => 'secret-password']);
        $cookieName = $this->app['auth']->guard()->getRecallerName();

        $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'secret-password',
        ])->assertRedirect(route('dashboard'))->assertCookieMissing($cookieName);

        $this->app['session']->invalidate();
        $this->app['session']->save();
        $this->app['auth']->forgetGuards();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_logout_invalidates_the_previous_remember_me_cookie(): void
    {
        $user = User::factory()->create(['password_hash' => 'secret-password']);
        $cookieName = $this->app['auth']->guard()->getRecallerName();
        $response = $this->post(route('login.store'), [
            'username' => $user->username,
            'password' => 'secret-password',
            'remember' => '1',
        ]);
        $response->assertRedirect(route('dashboard'))->assertCookie($cookieName);
        $rememberCookie = $response->getCookie($cookieName);

        $this->withCookie($cookieName, $rememberCookie->getValue())
            ->post(route('logout'))->assertRedirect(route('login'));
        $this->app['auth']->forgetGuards();

        $this->withCookie($cookieName, $rememberCookie->getValue())
            ->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
