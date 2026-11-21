<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class SharedDemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    private function configureDemo(): void
    {
        Storage::fake('demo-public');
        Storage::disk('demo-public')->put('build/manifest.json', '{}');
        $this->app->usePublicPath(Storage::disk('demo-public')->path(''));
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.debug' => false, 'app.url' => 'http://localhost:8000',
            'session.secure' => null, 'session.http_only' => true, 'session.same_site' => 'lax',
        ]);
        Http::preventStrayRequests();
    }

    /** @return array<string, array{string, ?bool}> */
    public static function validDemoTransports(): array
    {
        return [
            'local HTTP automatic cookie' => ['http://localhost:8000', null],
            'local HTTP explicit cookie' => ['http://localhost:8000', false],
            'shared HTTPS secure cookie' => ['https://demo.example.test', true],
        ];
    }

    #[DataProvider('validDemoTransports')]
    public function test_demo_preflight_accepts_matching_transport_without_writing_data_or_contacting_the_chain(string $url, ?bool $secure): void
    {
        $this->configureDemo();
        config(['app.url' => $url, 'session.secure' => $secure]);
        Http::fake();

        $this->artisan('deployment:check', ['--demo' => true])
            ->expectsOutput('PASS Demo debug output disabled')
            ->expectsOutput('PASS Demo session cookies HTTP-only')
            ->expectsOutput('PASS Demo session SameSite policy')
            ->expectsOutput('PASS Demo session cookies match application URL')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('payroll_runs', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function unsafeDemoSettings(): array
    {
        return [
            'debug' => [['app.debug' => true], 'Demo debug output disabled'],
            'script-readable session' => [['session.http_only' => false], 'Demo session cookies HTTP-only'],
            'cross-site cookie' => [['session.same_site' => 'none'], 'Demo session SameSite policy'],
            'secure cookie over HTTP' => [['session.secure' => true], 'Demo session cookies match application URL'],
            'insecure cookie over HTTPS' => [['app.url' => 'https://demo.example.test', 'session.secure' => false], 'Demo session cookies match application URL'],
            'implicit cookie over HTTPS' => [['app.url' => 'https://demo.example.test'], 'Demo session cookies match application URL'],
            'unsupported transport' => [['app.url' => 'ftp://demo.example.test'], 'Demo session cookies match application URL'],
            'invalid application URL' => [['app.url' => 'https:invalid', 'session.secure' => true], 'Demo session cookies match application URL'],
        ];
    }

    /** @param array<string, mixed> $settings */
    #[DataProvider('unsafeDemoSettings')]
    public function test_demo_preflight_rejects_debug_or_incompatible_session_settings(array $settings, string $check): void
    {
        $this->configureDemo();
        config($settings);

        $this->artisan('deployment:check', ['--demo' => true])->expectsOutput('FAIL '.$check)->assertFailed();
    }

    public function test_normal_local_preflight_does_not_silently_claim_demo_acceptance(): void
    {
        $this->configureDemo();
        config(['app.debug' => true]);

        $this->artisan('deployment:check')->doesntExpectOutput('PASS Demo debug output disabled')->assertSuccessful();
    }

    public function test_demo_option_cannot_bypass_production_https_or_cookie_requirements(): void
    {
        $this->configureDemo();
        $this->app->detectEnvironment(fn () => 'production');

        $this->artisan('deployment:check', ['--demo' => true])
            ->expectsOutput('FAIL HTTPS application URL')->expectsOutput('FAIL Secure session cookies')->assertFailed();
    }

    public function test_production_preflight_does_not_accept_the_retired_legacy_submission_transport(): void
    {
        $this->configureDemo();
        config([
            'app.url' => 'https://demo.example.test', 'session.secure' => true,
            'blockchain.registry.enabled' => false, 'blockchain.driver' => 'besu',
            'blockchain.contract_address' => '0x'.str_repeat('1', 40),
            'blockchain.besu.from_address' => '0x'.str_repeat('2', 40),
        ]);

        $this->artisan('deployment:check', ['--production' => true])
            ->expectsOutput('FAIL Payroll integrity transport configured')->assertFailed();
    }

    public function test_production_transport_check_accepts_the_supported_registry_configuration_without_network_io(): void
    {
        $this->configureDemo();
        config(['blockchain.registry' => [
            'enabled' => true, 'organization_id' => 'lgu-example', 'bridge_url' => 'http://bridge.test',
            'bridge_token' => str_repeat('t', 40), 'chain_id' => '1337', 'genesis_hash' => '0x'.str_repeat('a', 64),
            'contract_address' => '0x'.str_repeat('b', 40), 'code_hash' => '0x'.str_repeat('c', 64),
            'signer_address' => '0x'.str_repeat('d', 40), 'deployment_block' => '10',
        ]]);
        Http::fake();

        $this->artisan('deployment:check', ['--production' => true])
            ->expectsOutput('PASS Payroll integrity transport configured')->assertFailed();

        Http::assertNothingSent();
    }

    private function signIn(): void
    {
        config(['app.debug' => false]);
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', Role::ADMIN)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);
    }

    /** @return array<string, array{bool, bool}> */
    public static function serverFailures(): array
    {
        return ['database HTML' => [true, false], 'database JSON' => [true, true], 'runtime HTML' => [false, false], 'runtime JSON' => [false, true]];
    }

    #[DataProvider('serverFailures')]
    public function test_shared_demo_server_errors_hide_sensitive_details_and_preserve_rollback(bool $databaseFailure, bool $json): void
    {
        $this->signIn();
        if ($databaseFailure) {
            Department::creating(function (): void {
                DB::table('demo_failure_marker')->first();
            });
        } else {
            AuditLog::creating(function (): void {
                throw new RuntimeException('private-demo-marker');
            });
        }
        $input = ['department_code' => 'DEMO-FAIL', 'department_name' => 'private-demo-marker', 'status' => 'ACTIVE'];

        try {
            $response = $json ? $this->postJson(route('departments.store'), $input) : $this->post(route('departments.store'), $input);

            $response->assertServerError()->assertDontSee('private-demo-marker')->assertDontSee('demo_failure_marker')
                ->assertDontSee('SQLSTATE')->assertDontSee('RuntimeException')->assertDontSee('QueryException');
            if ($json) {
                $response->assertExactJson(['message' => 'Server Error']);
            } else {
                $response->assertSee('Server Error');
            }
        } finally {
            Department::flushEventListeners();
            AuditLog::flushEventListeners();
        }

        $this->assertDatabaseCount('departments', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_debug_disabled_validation_remains_actionable_and_keeps_form_input(): void
    {
        $this->signIn();
        Department::factory()->create(['department_code' => 'DUPLICATE']);
        $input = ['department_code' => 'DUPLICATE', 'department_name' => 'Retained demo input', 'status' => 'ACTIVE'];

        $this->postJson(route('departments.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('department_code');
        $this->followingRedirects()->from(route('departments.create'))->post(route('departments.store'), $input)->assertOk()
            ->assertSee('The department code has already been taken.')->assertSee('value="Retained demo input"', false);

        $this->assertDatabaseCount('departments', 1);
    }

    public function test_debug_disabled_missing_records_and_permission_denial_are_readable(): void
    {
        $this->signIn();

        $this->get(route('departments.show', 999999))->assertNotFound()->assertSee('Not Found')->assertDontSee('ModelNotFoundException');
        $this->actingAs(User::factory()->create())->get(route('departments.create'))
            ->assertForbidden()->assertSee('Forbidden')->assertDontSee('AuthorizationException');
    }
}
