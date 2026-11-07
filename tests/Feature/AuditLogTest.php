<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $role = Role::where('role_name', $roleName)->firstOrFail();
        $user->roles()->attach($role->role_id, ['assigned_at' => now()]);

        return $user;
    }

    public static function auditRoutes(): array
    {
        return [['audit-logs.index'], ['audit-logs.export']];
    }

    #[DataProvider('auditRoutes')]
    public function test_guests_are_redirected_to_login(string $route): void
    {
        $this->get(route($route))->assertRedirect(route('login'));
    }

    public static function restrictedRoles(): array
    {
        return [
            'officer' => [Role::PAYROLL_OFFICER],
            'approver' => [Role::APPROVER],
            'viewer' => [Role::VIEWER],
        ];
    }

    #[DataProvider('restrictedRoles')]
    public function test_other_roles_cannot_view_or_export_audit_events(string $roleName): void
    {
        $user = $this->userWithRole($roleName);

        $this->actingAs($user)->get(route('audit-logs.index'))->assertForbidden();
        $this->get(route('audit-logs.export'))->assertForbidden();
        $this->get(route('dashboard'))->assertDontSee('Audit trail');
    }

    public function test_admin_filters_events_by_actor_module_action_and_inclusive_date(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $event = AuditLog::factory()->create(['user_id' => $admin->user_id, 'module' => 'Payroll', 'action' => 'SUBMIT']);
        $event->forceFill(['created_at' => '2026-11-07 23:59:59'])->save();
        AuditLog::factory()->create(['module' => 'Payroll', 'action' => 'SUBMIT']);
        AuditLog::factory()->create(['user_id' => $admin->user_id, 'module' => 'Authentication', 'action' => 'LOGIN']);
        $older = AuditLog::factory()->create(['user_id' => $admin->user_id, 'module' => 'Payroll', 'action' => 'SUBMIT']);
        $older->forceFill(['created_at' => '2026-11-06 23:59:59'])->save();

        $this->actingAs($admin)->get(route('audit-logs.index', [
            'module' => 'Payroll', 'action' => 'SUBMIT', 'user_id' => $admin->user_id,
            'from' => '2026-11-07', 'to' => '2026-11-07',
        ]))->assertOk()->assertSee('Audit trail')
            ->assertViewHas('logs', fn ($logs): bool => $logs->modelKeys() === [$event->audit_id]);
    }

    public function test_event_metadata_is_escaped_and_payloads_are_not_disclosed(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        AuditLog::factory()->create([
            'module' => '<script>alert(1)</script>', 'action' => 'UPDATE',
            'old_values' => ['private' => 'private-before'], 'new_values' => ['private' => 'private-after'],
            'user_agent' => 'private-browser-identifier',
        ]);

        $this->actingAs($admin)->get(route('audit-logs.index'))->assertOk()
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('System / unavailable user')
            ->assertDontSee('private-before')->assertDontSee('private-after')->assertDontSee('private-browser-identifier');
    }

    public static function invalidFilters(): array
    {
        return [
            'module array' => [['module' => ['Payroll']], 'module'],
            'long module' => [['module' => str_repeat('x', 101)], 'module'],
            'action array' => [['action' => ['LOGIN']], 'action'],
            'long action' => [['action' => str_repeat('x', 101)], 'action'],
            'invalid actor' => [['user_id' => 'abc'], 'user_id'],
            'missing actor' => [['user_id' => 999999], 'user_id'],
            'bad start date' => [['from' => '2026-99-99'], 'from'],
            'bad end date' => [['to' => 'tomorrow'], 'to'],
            'reversed dates' => [['from' => '2026-11-07', 'to' => '2026-11-01'], 'to'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_are_rejected_on_both_endpoints(array $filters, string $field): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)->getJson(route('audit-logs.index', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->getJson(route('audit-logs.export', $filters))
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'EXPORT']);
    }

    public function test_export_uses_filters_neutralizes_formulas_and_records_the_download(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        AuditLog::factory()->create([
            'module' => 'Payroll', 'action' => '=1+1', 'record_table' => 'payroll_runs', 'record_id' => '17',
            'new_values' => ['private' => 'private-payroll-payload'],
        ]);
        AuditLog::factory()->create(['module' => 'Authentication', 'action' => 'LOGIN']);

        $response = $this->actingAs($admin)->get(route('audit-logs.export', ['module' => 'Payroll']));

        $response->assertOk()->assertDownload();
        $csv = $response->streamedContent();
        $this->assertStringContainsString("'=1+1", $csv);
        $this->assertStringContainsString('payroll_runs,17', $csv);
        $this->assertStringNotContainsString('LOGIN', $csv);
        $this->assertStringNotContainsString('EXPORT', $csv);
        $this->assertStringNotContainsString('private-payroll-payload', $csv);
        $this->assertDatabaseHas('audit_logs', ['module' => 'Audit', 'action' => 'EXPORT', 'user_id' => $admin->user_id]);
    }

    public function test_empty_end_date_filter_does_not_require_a_start_date(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $this->actingAs($admin)->get(route('audit-logs.index', ['to' => '2026-11-07']))
            ->assertOk()->assertSee('No activity matches these filters.');
    }

    public function test_pagination_preserves_filters(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        foreach (range(1, 26) as $number) {
            AuditLog::factory()->create(['module' => 'Payroll', 'action' => 'UPDATE', 'record_id' => (string) $number]);
        }

        $this->actingAs($admin)->get(route('audit-logs.index', ['module' => 'Payroll']))
            ->assertOk()->assertSee('module=Payroll&amp;page=2', false)
            ->assertViewHas('logs', fn ($logs): bool => $logs->count() === 25 && $logs->total() === 26);
    }
}
