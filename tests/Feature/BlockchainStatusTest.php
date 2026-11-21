<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlockchainStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.besu.rpc_url' => 'http://node.test']);
        Http::preventStrayRequests();
    }

    /** @return array<string, array{string}> */
    public static function roles(): array
    {
        return ['administrator' => [Role::ADMIN], 'officer' => [Role::PAYROLL_OFFICER], 'approver' => [Role::APPROVER], 'viewer' => [Role::VIEWER]];
    }

    #[DataProvider('roles')]
    public function test_active_roles_can_read_uncacheable_node_connectivity_without_a_registry_readiness_claim(string $role): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        config(['blockchain.registry.bridge_url' => 'http://bridge.test']);
        Http::fake([
            'http://node.test' => Http::response(['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x2a']),
            'http://bridge.test/*' => Http::failedConnection(),
        ]);

        $this->actingAs($user)->getJson(route('blockchain.status'))->assertOk()
            ->assertExactJson(['connected' => true, 'status' => 'Node connected', 'block_number' => 42, 'scope' => 'node_connectivity'])
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Pragma', 'no-cache')->assertHeader('X-Content-Type-Options', 'nosniff');

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'http://node.test'
            && $request->data() === ['jsonrpc' => '2.0', 'method' => 'eth_blockNumber', 'params' => [], 'id' => 1]);
        $this->assertDatabaseCount('payroll_registrations', 0);
    }

    /** @return array<string, array{mixed, int}> */
    public static function unavailableResponses(): array
    {
        return [
            'upstream failure' => [['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x2a'], 500],
            'rpc error with result' => [['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x2a', 'error' => ['message' => 'sensitive detail']], 200],
            'invalid envelope' => [['result' => '0x2a'], 200],
            'wrong rpc version' => [['jsonrpc' => '1.0', 'id' => 1, 'result' => '0x2a'], 200],
            'wrong response id' => [['jsonrpc' => '2.0', 'id' => 2, 'result' => '0x2a'], 200],
            'invalid block' => [['jsonrpc' => '2.0', 'id' => 1, 'result' => 'invalid'], 200],
            'non-string block' => [['jsonrpc' => '2.0', 'id' => 1, 'result' => 42], 200],
            'oversized block' => [['jsonrpc' => '2.0', 'id' => 1, 'result' => '0x'.str_repeat('f', 16)], 200],
            'non-json body' => ['sensitive upstream response', 200],
        ];
    }

    #[DataProvider('unavailableResponses')]
    public function test_unavailable_or_invalid_node_evidence_returns_uncacheable_503_without_upstream_details(mixed $body, int $status): void
    {
        $user = User::factory()->create();
        Http::fake(['http://node.test' => Http::response($body, $status)]);

        $this->actingAs($user)->getJson(route('blockchain.status'))->assertServiceUnavailable()
            ->assertExactJson(['connected' => false, 'status' => 'Node unavailable', 'block_number' => null, 'scope' => 'node_connectivity'])
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Pragma', 'no-cache');

        Http::assertSentCount(1);
    }

    public function test_connection_failure_is_a_scoped_uncacheable_node_outage(): void
    {
        Http::fake(['http://node.test' => Http::failedConnection()]);

        $this->actingAs(User::factory()->create())->getJson(route('blockchain.status'))->assertServiceUnavailable()
            ->assertExactJson(['connected' => false, 'status' => 'Node unavailable', 'block_number' => null, 'scope' => 'node_connectivity'])
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_deactivated_existing_session_is_logged_out_before_any_rpc_call(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->withSession(['private_context' => 'payroll'])->get(route('dashboard'))->assertOk();
        $user->update(['account_status' => User::STATUS_INACTIVE]);
        Http::fake();

        $this->getJson(route('blockchain.status'))->assertForbidden()
            ->assertExactJson(['message' => 'Your account is inactive. Contact an administrator.'])
            ->assertSessionMissing('private_context');

        $this->assertGuest();
        Http::assertNothingSent();
        $this->getJson(route('blockchain.status'))->assertUnauthorized();
        $this->assertDatabaseHas('audit_logs', ['user_id' => $user->getKey(), 'action' => 'LOGOUT']);
    }

    public function test_inactive_browser_request_redirects_to_sign_in_without_contacting_the_node(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->inactive()->create())->get(route('blockchain.status'))
            ->assertRedirect(route('login'))->assertSessionHasErrors('username');

        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_guest_cannot_read_node_status_in_browser_or_json_requests(): void
    {
        Http::fake();

        $this->get(route('blockchain.status'))->assertRedirect(route('login'));
        $this->getJson(route('blockchain.status'))->assertUnauthorized();

        Http::assertNothingSent();
    }

    public function test_layout_explains_node_scope_and_keeps_payroll_registration_checks_separate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()
            ->assertSee('Node status unavailable')->assertSee('Node connection only. Check a payroll registration for confirmation and integrity.')
            ->assertSee('data-status-url="'.route('blockchain.status').'"', false);
    }
}
