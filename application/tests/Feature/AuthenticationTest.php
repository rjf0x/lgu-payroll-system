<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public static function roles(): array
    {
        return array_map(fn (Role $role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_each_role_can_sign_in_and_only_access_its_workspace(Role $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $this->get('/login')->assertOk()->assertSee('Sign in');
        $oldSession = session()->getId();

        $this->post('/login', ['username' => strtoupper($user->username), 'password' => 'password', 'role' => 'administrator'])
            ->assertRedirect('/dashboard')->assertSessionHas('auth_version', 1);
        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($oldSession, session()->getId());

        $dashboard = $this->get('/dashboard')->assertOk()->assertSee($role->label());
        foreach (Role::cases() as $candidate) {
            if ($candidate === $role) {
                $dashboard->assertSee(route($candidate->workspace()));
                $this->get('/'.$candidate->workspace())->assertOk()->assertSee($candidate->label().' workspace');
            } else {
                $dashboard->assertDontSee(route($candidate->workspace()));
                $this->get('/'.$candidate->workspace())->assertForbidden();
            }
        }
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/login')->assertRedirect('/dashboard');
    }

    public function test_guests_cannot_access_protected_routes_or_create_accounts(): void
    {
        $user = User::factory()->create();
        foreach (['/dashboard', '/users', '/users/create', '/users/'.$user->id.'/edit', ...array_map(fn ($role) => '/'.$role->workspace(), Role::cases())] as $path) {
            $this->get($path)->assertRedirect('/login');
        }
        $this->post('/users', [])->assertRedirect('/login');
        $this->put('/users/'.$user->id, [])->assertRedirect('/login');
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_invalid_and_inactive_credentials_are_rejected_without_account_disclosure(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        foreach ([[$user->username, 'password'], [$user->username, 'wrong'], ['unknown', 'password']] as [$username, $password]) {
            $this->post('/login', compact('username', 'password'))
                ->assertSessionHasErrors(['username' => 'The provided credentials could not be verified.']);
            $this->assertGuest();
        }
        $this->post('/login', ['username' => ['invalid'], 'password' => []])->assertSessionHasErrors(['username', 'password']);
    }

    public function test_login_is_throttled_and_recovers_after_the_timeout(): void
    {
        $this->freezeTime();
        $user = User::factory()->create();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'incorrect'])->assertSessionHasErrors('username');
        }
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])
            ->assertSessionHasErrors(['username' => 'Too many sign-in attempts. Try again in 60 seconds.']);
        $this->assertGuest();
        $this->travel(61)->seconds();
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts('login:'.hash('sha256', $user->username.'|127.0.0.1')));
    }

    public function test_logout_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $oldSession = session()->getId();
        $oldToken = session()->token();
        $this->withSession(['private_marker' => 'secret']);
        $this->post('/logout')->assertRedirect('/login')->assertSessionMissing('private_marker')->assertSessionMissing('auth_version');
        $this->assertGuest();
        $this->assertNotSame($oldSession, session()->getId());
        $this->assertNotSame($oldToken, session()->token());
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_inactive_accounts_and_outdated_sessions_are_rejected_on_the_next_request(): void
    {
        $user = User::factory()->create();
        $this->post('/login', ['username' => $user->username, 'password' => 'password']);
        $user->forceFill(['auth_version' => 2])->save();
        Auth::forgetGuards(); // Simulate the fresh guard in the next real HTTP request.
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();

        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertRedirect('/dashboard');
        $user->forceFill(['is_active' => false])->save();
        Auth::forgetGuards();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_mutating_routes_enforce_csrf_and_logout_is_post_only(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $user = User::factory()->create(['role' => Role::Administrator]);
        $this->get('/login')->assertOk();
        $this->post('/login', ['username' => $user->username, 'password' => 'password'])->assertStatus(419);
        $this->post('/login', ['username' => $user->username, 'password' => 'password', '_token' => session()->token()])->assertRedirect('/dashboard');
        $this->post('/users', [])->assertStatus(419);
        $this->put('/users/'.$user->id, [])->assertStatus(419);
        $this->post('/logout')->assertStatus(419);
        $this->get('/logout')->assertStatus(405);
        $this->post('/logout', ['_token' => session()->token()])->assertRedirect('/login');
    }
}
