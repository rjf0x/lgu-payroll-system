<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UsernameAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_username_login_normalizes_case_and_whitespace_and_email_login_is_removed(): void
    {
        $user = User::factory()->create(['username' => 'payroll.officer']);
        $this->get('/login')->assertOk()->assertSee('Username')->assertSee('name="username"', false)->assertDontSee('name="email"', false);
        $this->post('/login', ['email' => 'payroll.officer@example.test', 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $this->post('/login', ['username' => '  PAYROLL.OFFICER  ', 'password' => 'password'])->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
        $this->assertFalse(Schema::hasColumn('users', 'email'));
        $this->assertFalse(Schema::hasColumn('users', 'email_verified_at'));
        $this->assertTrue(Schema::hasColumn('password_reset_tokens', 'username'));
        $this->assertFalse(Schema::hasColumn('password_reset_tokens', 'email'));
    }

    public function test_account_creation_and_rename_use_unique_usernames_without_email(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $data = ['name' => 'Fictional Payroll Officer', 'username' => '  PAYROLL_ONE  ', 'role' => 'payroll_officer', 'is_active' => 1, 'password' => 'FictionalPass123', 'password_confirmation' => 'FictionalPass123'];
        $this->post('/users', $data)->assertRedirect('/users')->assertSessionHasNoErrors();
        $account = User::where('username', 'payroll_one')->sole();
        $originalHash = $account->password;
        $this->post('/users', [...$data, 'username' => 'PAYROLL_ONE'])->assertSessionHasErrors('username');
        $this->get('/users')->assertOk()->assertSee('Username')->assertSee('payroll_one')->assertDontSee('Email');
        $this->get('/users/'.$account->id.'/edit')->assertOk()->assertSee('name="username"', false)->assertDontSee('name="email"', false);
        $this->put('/users/'.$account->id, [...$data, 'username' => 'payroll.two', 'password' => '', 'password_confirmation' => ''])->assertRedirect('/users')->assertSessionHasNoErrors();
        $this->assertSame('payroll.two', $account->fresh()->username);
        $this->assertSame($originalHash, $account->fresh()->password);
        $this->assertSame(2, $account->fresh()->auth_version);
    }

    public static function invalidUsernames(): array
    {
        return [['ab'], [str_repeat('x', 51)], ['a user'], ['a@example.test'], ['_admin'], ['josé'], [['bad']]];
    }

    public static function validUsernames(): array
    {
        return [['abc'], [str_repeat('a', 50)], ['payroll.officer_01-test']];
    }

    #[DataProvider('validUsernames')]
    public function test_username_length_boundaries_and_allowed_characters_work(string $username): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $this->post('/users', ['name' => 'Fictional Valid User', 'username' => strtoupper($username), 'role' => 'viewer', 'is_active' => 1, 'password' => 'FictionalPass123', 'password_confirmation' => 'FictionalPass123'])->assertRedirect('/users')->assertSessionHasNoErrors();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['username' => $username, 'password' => 'FictionalPass123'])->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $this->assertSame($username, auth()->user()->username);
    }

    #[DataProvider('invalidUsernames')]
    public function test_invalid_usernames_are_rejected_at_login_and_account_writes(mixed $username): void
    {
        $this->post('/login', ['username' => $username, 'password' => 'password'])->assertSessionHasErrors('username');
        $this->assertGuest();
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actingAs($admin)->withSession(['auth_version' => $admin->auth_version]);
        $this->post('/users', ['name' => 'Fictional Invalid User', 'username' => $username, 'role' => 'viewer', 'is_active' => 1, 'password' => 'FictionalPass123', 'password_confirmation' => 'FictionalPass123'])->assertSessionHasErrors('username');
        $this->assertDatabaseCount('users', 1);
    }

    public function test_migration_preserves_legacy_accounts_and_resolves_username_collisions(): void
    {
        $migration = require database_path('migrations/2026_10_26_000001_replace_user_emails_with_usernames.php');
        $migration->down();
        $hash = Hash::make('FictionalLegacy123');
        $emails = ['Admin@example.test', 'admin@another.test', 'ab@example.test', '!!!@example.test', str_repeat('a', 60).'@example.test', str_repeat('a', 60).'@another.test'];
        $ids = [];
        foreach ($emails as $index => $email) {
            $ids[] = DB::table('users')->insertGetId(['name' => 'Fictional Legacy '.$index, 'email' => $email, 'password' => $hash, 'role' => $index === 0 ? 'administrator' : 'viewer', 'is_active' => $index !== 3, 'auth_version' => 4, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('password_reset_tokens')->insert(['email' => $emails[0], 'token' => 'fictional-obsolete-token']);
        $before = DB::table('users')->orderBy('id')->get()->keyBy('id');
        $migration->up();
        $accounts = User::orderBy('id')->get();
        $this->assertSame(['admin', 'admin-'.$ids[1], 'user-'.$ids[2], 'user-'.$ids[3], str_repeat('a', 50), str_repeat('a', 50 - strlen('-'.$ids[5])).'-'.$ids[5]], $accounts->pluck('username')->all());
        foreach ($accounts as $account) {
            foreach (['id', 'name', 'password', 'role', 'is_active', 'auth_version', 'created_at', 'updated_at'] as $field) {
                $this->assertEquals($before[$account->id]->{$field}, $account->getRawOriginal($field));
            }
            $this->assertMatchesRegularExpression('/\A[a-z0-9][a-z0-9._-]{2,49}\z/', $account->username);
        }
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post('/login', ['username' => 'ADMIN', 'password' => 'FictionalLegacy123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($accounts->first());
    }
}
