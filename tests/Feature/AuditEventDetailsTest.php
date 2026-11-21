<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditEventDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    public function test_only_the_explicit_detail_page_discloses_saved_changes_and_redacts_legacy_secret_values(): void
    {
        $admin = $this->signIn(Role::ADMIN);
        $event = AuditLog::factory()->create([
            'user_id' => $admin->getKey(), 'module' => 'Configuration', 'action' => 'CONTRIBUTION_RATE_UPDATED',
            'old_values' => ['employee_rate' => '0.0250', 'password' => 'old-password-value'],
            'new_values' => ['employee_rate' => '0.0300', 'audit_context' => ['reason' => 'Approved order <script>alert(1)</script>'], 'nested' => ['PRIVATE-KEY' => 'legacy-private-key', 'accessToken' => 'camel-case-secret']],
            'user_agent' => 'private-browser-identifier',
        ]);

        $this->get(route('audit-logs.index'))->assertOk()->assertSee(route('audit-logs.show', $event))->assertDontSee('0.0250')->assertDontSee('legacy-private-key');
        $this->get(route('audit-logs.show', $event))->assertOk()
            ->assertSee('0.0250')->assertSee('0.0300')->assertSee('Approved order')
            ->assertSee('[REDACTED]')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('old-password-value')->assertDontSee('legacy-private-key')->assertDontSee('camel-case-secret')->assertDontSee('private-browser-identifier')
            ->assertHeader('Cache-Control', 'no-store, private');
        $csv = $this->get(route('audit-logs.export'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('old-password-value', $csv);
        $this->assertStringNotContainsString('legacy-private-key', $csv);
    }

    /** @return array<string, array{string}> */
    public static function restrictedRoles(): array
    {
        return ['officer' => [Role::PAYROLL_OFFICER], 'approver' => [Role::APPROVER], 'viewer' => [Role::VIEWER]];
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admin_roles_cannot_read_an_event_payload(string $role): void
    {
        $this->signIn($role);
        $event = AuditLog::factory()->create(['new_values' => ['amount' => 'private-payroll-value']]);

        $this->get(route('audit-logs.show', $event))->assertForbidden()->assertDontSee('private-payroll-value');
    }

    public function test_guests_are_redirected_and_missing_events_are_not_found(): void
    {
        $event = AuditLog::factory()->create();
        $this->get(route('audit-logs.show', $event))->assertRedirect(route('login'));
        $this->signIn(Role::ADMIN);

        $this->get(route('audit-logs.show', 999999))->assertNotFound();
    }

    public function test_metadata_only_events_remain_readable_without_inventing_before_or_after_values(): void
    {
        $this->signIn(Role::ADMIN);
        $event = AuditLog::factory()->create(['old_values' => null, 'new_values' => null]);

        $this->get(route('audit-logs.show', $event))->assertOk()->assertSee('No values recorded.');
    }

    public function test_the_recorded_actor_remains_identifiable_after_the_account_is_deleted(): void
    {
        $actor = User::factory()->create(['username' => 'former-officer']);
        $actorId = $actor->getKey();
        $event = app(AuditLogger::class)->log('SOURCE_CHANGED', 'Employees', $actorId, newValues: ['reason' => 'Approved update.']);
        $actor->delete();

        $event->refresh();

        $this->assertNull($event->user_id);
        $this->assertSame($actorId, $event->new_values['audit_actor']['user_id']);
        $this->assertSame('former-officer', $event->new_values['audit_actor']['username']);
        $this->signIn(Role::ADMIN);
        $this->get(route('audit-logs.show', $event))->assertOk()->assertSee('former-officer');
    }
}
