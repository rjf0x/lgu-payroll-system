<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Http\Controllers\UserController;
use App\Http\Requests\SaveUserRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AccessRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function admin(): User
    {
        $account = User::factory()->create(['role' => Role::Administrator]);
        $this->post('/login', ['email' => $account->email, 'password' => 'password'])->assertRedirect('/dashboard');

        return $account;
    }

    private function input(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Fictional Regression User', 'email' => 'regression@example.test',
            'role' => 'viewer', 'is_active' => 1, 'password' => 'FictionalPass123',
            'password_confirmation' => 'FictionalPass123',
        ], $overrides);
    }

    private function followRedirectToForm(string $path): TestResponse
    {
        // Carry the actual session cookie so JSON flash data is reloaded as in a browser.
        return $this->withCookie(config('session.cookie'), session()->getId())->get($path);
    }

    public static function unsupportedPasswords(): array
    {
        return [
            '73 ASCII bytes' => ['Aa1'.str_repeat('x', 70)],
            '73 UTF-8 bytes' => ['Aa1'.str_repeat('é', 35)],
            'null byte' => ["FictionalPass123\0Extra"],
        ];
    }

    #[DataProvider('unsupportedPasswords')]
    public function test_unsupported_passwords_are_rejected_on_create_edit_login_and_bootstrap(string $password): void
    {
        $admin = $this->admin();
        $target = User::factory()->create();
        $original = $target->fresh()->getAttributes();
        $data = $this->input(['password' => $password, 'password_confirmation' => $password]);
        $this->from('/users/create')->post('/users', $data)->assertRedirect('/users/create')->assertSessionHasErrors('password');
        $this->followRedirectToForm('/users/create')->assertOk();
        $this->from('/users/'.$target->id.'/edit')->put('/users/'.$target->id, $data)
            ->assertRedirect('/users/'.$target->id.'/edit')->assertSessionHasErrors('password');
        $this->assertSame($original, $target->fresh()->getAttributes());
        $this->followRedirectToForm('/users/'.$target->id.'/edit')->assertOk();
        $this->assertDatabaseCount('users', 2);

        $this->post('/logout');
        $this->from('/login')->post('/login', ['email' => $admin->email, 'password' => $password])
            ->assertRedirect('/login')->assertSessionHasErrors('password');
        $this->followRedirectToForm('/login')->assertOk();
        $this->assertGuest();

        $admin->forceFill(['role' => Role::Viewer])->save();
        $this->artisan('app:create-admin')
            ->expectsQuestion('Administrator name', 'Fictional Administrator')
            ->expectsQuestion('Administrator email', 'initial@example.test')
            ->expectsQuestion('Password (12+ characters, upper/lowercase and numbers)', $password)
            ->expectsQuestion('Confirm password', $password)
            ->assertFailed();
        $this->assertDatabaseCount('users', 2);
    }

    public static function boundaryPasswords(): array
    {
        return [['Aa1'.str_repeat('x', 69)], ['Aa1'.str_repeat('é', 34).'x']];
    }

    #[DataProvider('boundaryPasswords')]
    public function test_72_byte_passwords_round_trip_without_ignoring_the_last_byte(string $password): void
    {
        $this->admin();
        $this->assertSame(72, strlen($password));
        $this->post('/users', $this->input(['password' => $password, 'password_confirmation' => $password]))
            ->assertRedirect('/users')->assertSessionHasNoErrors();
        $account = User::where('email', 'regression@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $account->password));
        $this->post('/logout');
        $this->post('/login', ['email' => $account->email, 'password' => substr($password, 0, -1).'z'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => $account->email, 'password' => $password])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($account);
    }

    public function test_long_suffix_cannot_authenticate_against_a_legacy_truncated_password(): void
    {
        $prefix = 'Aa1'.str_repeat('x', 69);
        $user = User::factory()->create(['password' => $prefix.'FIRST']);
        $this->post('/login', ['email' => $user->email, 'password' => $prefix.'SECOND'])->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_hash_shaped_password_is_treated_as_a_literal_credential_on_create_and_reset(): void
    {
        $this->admin();
        $password = Hash::make('OriginalPassword123');
        $this->post('/users', $this->input(['password' => $password, 'password_confirmation' => $password]))
            ->assertRedirect('/users')->assertSessionHasNoErrors();
        $account = User::where('email', 'regression@example.test')->firstOrFail();
        $this->assertTrue(Hash::check($password, $account->password));
        $this->assertFalse(Hash::check('OriginalPassword123', $account->password));
        $this->put('/users/'.$account->id, $this->input(['password' => $password, 'password_confirmation' => $password]))
            ->assertRedirect('/users')->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check($password, $account->fresh()->password));
        $this->post('/logout');
        $this->post('/login', ['email' => $account->email, 'password' => $password])->assertRedirect('/dashboard');
    }

    public function test_invalid_login_input_remains_renderable_after_redirect(): void
    {
        $this->from('/login')->post('/login', ['email' => ['bad'], 'password' => ['bad']])
            ->assertRedirect('/login')->assertSessionHasErrors(['email', 'password']);
        $this->followRedirectToForm('/login')->assertOk()->assertSee('Please check the following:');
    }

    public static function formFields(): array
    {
        return [['name'], ['email'], ['role'], ['is_active']];
    }

    #[DataProvider('formFields')]
    public function test_invalid_account_input_remains_renderable_on_create_and_edit(string $field): void
    {
        $this->admin();
        $target = User::factory()->create();
        $original = $target->fresh()->getAttributes();
        $data = $this->input([$field => ['bad']]);
        $this->from('/users/create')->post('/users', $data)->assertRedirect('/users/create')->assertSessionHasErrors($field);
        $this->followRedirectToForm('/users/create')->assertOk()->assertSee('Please check the following:');
        $path = '/users/'.$target->id.'/edit';
        $this->from($path)->put('/users/'.$target->id, $data)->assertRedirect($path)->assertSessionHasErrors($field);
        $this->followRedirectToForm($path)->assertOk()->assertSee('Please check the following:');
        $this->assertSame($original, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
    }

    public function test_unique_create_conflict_is_a_validation_error_and_rolls_back(): void
    {
        $this->admin();
        $competing = false;
        User::creating(function (User $user) use (&$competing) {
            if (! $competing && $user->email === 'regression@example.test') {
                $competing = true;
                User::factory()->create(['email' => $user->email]);
            }
        });
        $this->from('/users/create')->post('/users', $this->input())->assertRedirect('/users/create')->assertSessionHasErrors('email');
        $this->followRedirectToForm('/users/create')->assertOk();
        // The deterministic competing insert shares the transaction and rolls back too.
        $this->assertDatabaseCount('users', 1);
    }

    public function test_unique_update_conflict_rolls_back_password_and_auth_version(): void
    {
        $this->admin();
        $target = User::factory()->create();
        $original = $target->fresh()->getAttributes();
        User::updating(function (User $user) use ($target) {
            if ($user->id === $target->id) {
                User::factory()->create(['email' => $user->email]);
            }
        });
        $path = '/users/'.$target->id.'/edit';
        $this->from($path)->put('/users/'.$target->id, $this->input())->assertRedirect($path)->assertSessionHasErrors('email');
        $this->followRedirectToForm($path)->assertOk();
        $this->assertSame($original, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 2);
    }

    private function validatedRequest(User $actor, array $data, ?User $target = null): SaveUserRequest
    {
        $request = SaveUserRequest::create($target ? '/users/'.$target->id : '/users', $target ? 'PUT' : 'POST', $data);
        $request->setContainer($this->app);
        $request->setRedirector($this->app['redirect']);
        $request->setUserResolver(fn () => $actor);
        $session = new Store('inflight-request', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $actor->auth_version);
        $request->setLaravelSession($session);
        $route = new Route($request->method(), $target ? '/users/{user}' : '/users', fn () => null);
        $route->bind($request);
        if ($target) {
            $route->setParameter('user', $target);
        }
        $request->setRouteResolver(fn () => $route);
        $request->validateResolved();

        return $request;
    }

    public static function revocations(): array
    {
        return [['deactivate'], ['demote'], ['reset']];
    }

    #[DataProvider('revocations')]
    public function test_revoked_inflight_administrator_cannot_complete_reciprocal_edit_or_creation(string $change): void
    {
        $first = $this->admin();
        $second = User::factory()->create(['role' => Role::Administrator]);
        $edit = $this->validatedRequest($first, [
            'name' => $second->name, 'email' => $second->email, 'role' => 'administrator', 'is_active' => 0,
        ], $second);
        $create = $this->validatedRequest($first, $this->input());
        $this->actingAs($second)->withSession(['auth_version' => 1]);
        $this->put('/users/'.$first->id, [
            'name' => $first->name, 'email' => $first->email,
            'role' => $change === 'demote' ? 'viewer' : 'administrator', 'is_active' => $change === 'deactivate' ? 0 : 1,
            ...($change === 'reset' ? ['password' => 'ChangedPassword123', 'password_confirmation' => 'ChangedPassword123'] : []),
        ])->assertRedirect('/users');

        foreach (['update', 'store'] as $action) {
            try {
                $controller = $this->app->make(UserController::class);
                $action === 'update' ? $controller->update($edit, $second) : $controller->store($create);
                $this->fail('The revoked in-flight request was permitted to write.');
            } catch (AuthorizationException $exception) {
                $this->assertSame(403, $exception->status() ?? 403);
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->assertTrue($second->fresh()->is_active);
        $this->assertSame(Role::Administrator, $second->fresh()->role);
        $this->assertDatabaseCount('users', 2);
        $this->assertGreaterThanOrEqual(1, User::where('role', Role::Administrator)->where('is_active', true)->count());
    }

    public function test_initial_admin_setup_refuses_a_concurrent_provisioning_lock(): void
    {
        $lock = Cache::lock('initial-administrator-provisioning', 30);
        $this->assertTrue($lock->get());
        try {
            $this->artisan('app:create-admin')
                ->expectsQuestion('Administrator name', 'Fictional Administrator')
                ->expectsQuestion('Administrator email', 'initial@example.test')
                ->expectsQuestion('Password (12+ characters, upper/lowercase and numbers)', 'FictionalPass123')
                ->expectsQuestion('Confirm password', 'FictionalPass123')
                ->expectsOutput('Another administrator setup is in progress. Try again.')
                ->assertFailed();
            $this->assertDatabaseCount('users', 0);
        } finally {
            $lock->release();
        }
    }

    public function test_initial_admin_setup_rechecks_after_interactive_input(): void
    {
        $created = false;
        DB::listen(function ($query) use (&$created) {
            if (! $created && str_contains($query->sql, 'count(*)') && in_array('initial@example.test', $query->bindings, true)) {
                $created = true;
                User::factory()->create(['role' => Role::Administrator]);
            }
        });
        $this->artisan('app:create-admin')
            ->expectsQuestion('Administrator name', 'Fictional Administrator')
            ->expectsQuestion('Administrator email', 'initial@example.test')
            ->expectsQuestion('Password (12+ characters, upper/lowercase and numbers)', 'FictionalPass123')
            ->expectsQuestion('Confirm password', 'FictionalPass123')
            ->expectsOutput('An administrator already exists. Manage accounts through the application.')
            ->assertFailed();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseMissing('users', ['email' => 'initial@example.test']);
    }
}
