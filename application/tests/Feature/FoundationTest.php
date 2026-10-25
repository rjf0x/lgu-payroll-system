<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PDOException;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_the_foundation_screen(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertViewIs('foundation')
            ->assertSee('LGU Payroll Management System')
            ->assertSee('Project Foundation');
    }

    public function test_application_health_endpoint_responds(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_base_migrations_support_database_persistence(): void
    {
        $user = User::factory()->create(['email' => 'foundation@example.test']);

        $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'foundation@example.test']);
        $this->assertSame('foundation@example.test', $user->fresh()->email);
    }

    public function test_readiness_command_succeeds_with_a_migrated_database(): void
    {
        $this->artisan('app:check-foundation')
            ->expectsOutput('Database connection and base tables are ready.')
            ->assertSuccessful();
    }

    public function test_readiness_command_fails_when_base_tables_are_missing(): void
    {
        Schema::drop('jobs');

        $this->artisan('app:check-foundation')
            ->expectsOutput('Base tables are missing. Run php artisan migrate.')
            ->assertFailed();
    }

    public function test_readiness_command_fails_cleanly_when_database_is_unavailable(): void
    {
        DB::shouldReceive('connection')->once()->andThrow(new PDOException('Connection refused'));

        $this->artisan('app:check-foundation')
            ->expectsOutput('Database unavailable. Check the server and DB_* configuration.')
            ->assertFailed();
    }
}
