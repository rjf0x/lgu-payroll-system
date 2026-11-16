<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PageLoadingTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string}> */
    public static function pageLayouts(): array
    {
        return [
            'welcome' => ['/', 'auth'],
            'sign in' => ['login', 'auth'],
            'dashboard' => ['dashboard', 'dashboard'],
            'list' => ['employees.index', 'table'],
            'create' => ['employees.create', 'form'],
            'settings' => ['system-settings.edit', 'form'],
            'detail' => ['departments.show', 'detail'],
            'report' => ['reports.payroll-summary', 'report'],
        ];
    }

    #[DataProvider('pageLayouts')]
    public function test_pages_include_hidden_accessible_skeletons_without_hiding_their_content(string $routeName, string $kind): void
    {
        $parameters = [];
        if (! in_array($routeName, ['/', 'login'], true)) {
            $this->seed(RolePermissionSeeder::class);
            $user = User::factory()->create();
            $user->roles()->attach(Role::where('role_name', Role::ADMIN)->value('role_id'), ['assigned_at' => now()]);
            $this->actingAs($user);
            if ($routeName === 'departments.show') {
                $parameters = [Department::factory()->create()];
            } elseif ($routeName === 'reports.payroll-summary') {
                $parameters = [PayrollRun::factory()->create()];
            }
        }

        $response = $this->get($routeName === '/' ? '/' : route($routeName, $parameters));

        $response->assertOk()
            ->assertSee('data-page-content', false)
            ->assertSee('data-page-loader data-current-kind="'.$kind.'"', false)
            ->assertSee('hidden class="no-print', false)
            ->assertSee('data-page-loading-status role="status" aria-live="polite"', false)
            ->assertDontSee('data-page-content hidden', false);
        foreach (['dashboard', 'table', 'form', 'detail', 'report', 'auth'] as $template) {
            $response->assertSee('data-loading-template="'.$template.'"', false);
        }
    }
}
