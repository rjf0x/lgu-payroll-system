<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SystemSettingTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $name): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $name)->firstOrFail(), ['assigned_at' => now()]);

        return $user;
    }

    public function test_guests_cannot_view_or_save_settings(): void
    {
        $this->get(route('system-settings.edit'))->assertRedirect(route('login'));
        $this->put(route('system-settings.update'), [])->assertRedirect(route('login'));
    }

    public static function nonAdministrators(): array
    {
        return [[Role::PAYROLL_OFFICER], [Role::APPROVER], [Role::VIEWER]];
    }

    #[DataProvider('nonAdministrators')]
    public function test_non_administrators_cannot_view_or_save_settings(string $name): void
    {
        $user = $this->userWithRole($name);

        $this->actingAs($user)->get(route('system-settings.edit'))->assertForbidden();
        $this->put(route('system-settings.update'), [])->assertForbidden();
    }

    public function test_administrator_saves_allowed_settings_and_changes_are_audited_and_displayed(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        $values = ['system_name' => 'Municipal Payroll', 'organization_name' => 'Example Municipality', 'support_email' => 'support@example.test'];

        $this->actingAs($user)->put(route('system-settings.update'), ['settings' => $values])->assertRedirect(route('system-settings.edit'));

        $this->assertDatabaseHas('system_settings', ['setting_key' => 'system_name', 'value' => 'Municipal Payroll']);
        $audit = AuditLog::where('action', 'SETTINGS_UPDATED')->firstOrFail();
        $this->assertSame('LGU Payroll System', $audit->old_values['system_name']);
        $this->assertSame('Municipal Payroll', $audit->new_values['system_name']);
        $this->get(route('dashboard'))->assertOk()->assertSee('Municipal Payroll')->assertSee('Example Municipality');
        $this->seed(SystemSettingSeeder::class);
        $this->assertSame('Municipal Payroll', SystemSetting::currentValues()['system_name']);
    }

    public static function invalidValues(): array
    {
        return [
            'missing name' => [['system_name' => '', 'organization_name' => 'Valid'], 'settings.system_name'],
            'long name' => [['system_name' => str_repeat('x', 151), 'organization_name' => 'Valid'], 'settings.system_name'],
            'missing organization' => [['system_name' => 'Valid', 'organization_name' => ''], 'settings.organization_name'],
            'invalid email' => [['system_name' => 'Valid', 'organization_name' => 'Valid', 'support_email' => 'not-email'], 'settings.support_email'],
            'security toggle' => [['system_name' => 'Valid', 'organization_name' => 'Valid', 'audit_logging' => false], 'settings'],
            'non-array' => ['invalid', 'settings'],
        ];
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_or_unrecognized_settings_cannot_change_configuration(mixed $values, string $field): void
    {
        $user = $this->userWithRole(Role::ADMIN);

        $this->actingAs($user)->putJson(route('system-settings.update'), ['settings' => $values])
            ->assertUnprocessable()->assertJsonValidationErrors($field);

        $this->assertSame('LGU Payroll System', SystemSetting::currentValues()['system_name']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'SETTINGS_UPDATED']);
    }

    public function test_validation_keeps_nested_values_and_connects_inline_errors(): void
    {
        $user = $this->userWithRole(Role::ADMIN);

        $this->actingAs($user)->from(route('system-settings.edit'))->followingRedirects()
            ->put(route('system-settings.update'), ['settings' => ['system_name' => '', 'organization_name' => 'Keep this organization']])
            ->assertOk()->assertSee('Keep this organization')->assertSee('id="settings-system_name-error"', false)
            ->assertSee('aria-describedby="settings-system_name-error"', false);
    }

    public function test_display_settings_are_escaped_in_workspace_html(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        SystemSetting::where('setting_key', 'system_name')->update(['value' => '<script>alert(1)</script>']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertSee('<script>alert(1)</script>')
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
