<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\ProductionDatabaseSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Database\Seeders\UserAccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class DeploymentReadinessTest extends TestCase
{
    use RefreshDatabase;

    public static function demonstrationSeeders(): array
    {
        return [[DatabaseSeeder::class], [DemoDataSeeder::class], [UserAccountSeeder::class], [StatutoryConfigSeeder::class]];
    }

    #[DataProvider('demonstrationSeeders')]
    public function test_demo_seeding_is_rejected_before_production_data_is_written(string $seeder): void
    {
        $this->app->detectEnvironment(fn () => 'production');

        try {
            $this->app->make($seeder)->run();
            $this->fail('Production demo seeding was permitted.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('employees', 0);
            $this->assertDatabaseCount('tax_brackets', 0);
        }
    }

    private function configureInitialAccount(): void
    {
        config(['deployment.bootstrap_admin' => [
            'username' => 'release.manager', 'email' => 'release@example.test', 'password' => 'Release-Example-2026!Safe',
        ]]);
    }

    public function test_production_setup_creates_one_admin_without_demo_payroll_or_rates(): void
    {
        $this->configureInitialAccount();

        $this->seed(ProductionDatabaseSeeder::class);

        $this->assertDatabaseCount('users', 1);
        $admin = User::firstOrFail();
        $this->assertTrue($admin->isActive());
        $this->assertTrue($admin->hasRole(Role::ADMIN));
        $this->assertTrue(Hash::check('Release-Example-2026!Safe', $admin->password_hash));
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('tax_brackets', 0);
        $this->assertDatabaseCount('contribution_rates', 0);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PRODUCTION_BOOTSTRAPPED']);
    }

    public function test_existing_accounts_are_not_overwritten_by_bootstrap(): void
    {
        $this->configureInitialAccount();
        $user = User::factory()->create();
        $originalPassword = $user->password_hash;

        try {
            $this->seed(ProductionDatabaseSeeder::class);
            $this->fail('Bootstrap was permitted on existing accounts.');
        } catch (RuntimeException) {
            $this->assertDatabaseCount('users', 1);
            $this->assertSame($originalPassword, $user->fresh()->password_hash);
            $this->assertDatabaseCount('roles', 0);
        }
    }

    public function test_weak_bootstrap_credentials_are_rejected_without_partial_setup(): void
    {
        $this->configureInitialAccount();
        config(['deployment.bootstrap_admin.password' => 'password']);

        try {
            $this->seed(ProductionDatabaseSeeder::class);
            $this->fail('Weak bootstrap credentials were permitted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('password', $exception->errors());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('roles', 0);
        }
    }

    public function test_production_preflight_rejects_local_debug_and_insecure_cookie_configuration(): void
    {
        config(['app.debug' => true, 'session.secure' => false, 'app.url' => 'http://localhost']);

        $this->artisan('deployment:check', ['--production' => true])
            ->expectsOutput('FAIL Production environment selected')
            ->expectsOutput('FAIL Debug output disabled')
            ->expectsOutput('FAIL Secure session cookies')
            ->assertFailed();

        $this->assertDatabaseCount('users', 0);
    }

    public function test_production_preflight_rejects_known_demonstration_credentials(): void
    {
        User::factory()->create(['username' => 'admin', 'password_hash' => 'password']);

        $this->artisan('deployment:check', ['--production' => true])
            ->expectsOutput('FAIL Known demo passwords absent')->assertFailed();
    }
}
