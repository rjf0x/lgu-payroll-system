<?php

namespace Tests\Feature;

use App\Jobs\GeneratePayslips;
use App\Models\AuditLog;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\PayslipGenerationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ViewerReportAccessTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }

    private function detail(string $status = PayrollRun::STATUS_FINALIZED): PayrollDetail
    {
        $run = PayrollRun::factory()->create(['status' => $status]);

        return PayrollDetail::factory()->create(['payroll_run_id' => $run->getKey()]);
    }

    private function payslip(PayrollDetail $detail): Payslip
    {
        return $detail->payslip()->create([
            'payslip_number' => 'PS-ACCESS-'.$detail->getKey(), 'generated_at' => now(), 'publication_status' => Payslip::STATUS_GENERATED,
        ]);
    }

    /** @return array<string, array{string, bool}> */
    public static function rolePermissions(): array
    {
        return [
            'administrator' => [Role::ADMIN, true], 'officer' => [Role::PAYROLL_OFFICER, true],
            'approver' => [Role::APPROVER, true], 'viewer' => [Role::VIEWER, false],
        ];
    }

    #[DataProvider('rolePermissions')]
    public function test_system_roles_have_export_access_and_only_writers_have_generation_access(string $role, bool $canGenerate): void
    {
        $user = $this->userWithRole($role);

        $this->assertTrue($user->can(Permission::REPORTS_EXPORT));
        $this->assertSame($canGenerate, $user->can(Permission::REPORTS_GENERATE));
    }

    public function test_viewer_reads_existing_outputs_and_exports_without_changing_payroll_or_payslips(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);
        $detail = $this->detail();
        $run = $detail->payrollRun;
        $payslip = $this->payslip($detail);
        $before = $detail->fresh()->getAttributes();
        $slipBefore = $payslip->fresh()->getAttributes();

        $this->actingAs($viewer)->get(route('reports.index'))->assertOk()->assertSee(route('payroll-runs.register.export', $run));
        $this->get(route('payroll-runs.register', $run))->assertOk()->assertSee(route('payroll-runs.register.export', $run));
        $this->get(route('reports.payroll-summary', $run))->assertOk()->assertSee(route('payroll-runs.register.export', $run));
        $this->get(route('reports.deductions', $run))->assertOk();
        $this->get(route('payroll-runs.payslips', $run))->assertOk()
            ->assertSee(route('payslips.show', $payslip))->assertDontSee(route('payroll-runs.payslips.generate', $run))->assertDontSee(route('payslips.publish', $payslip));
        $this->get(route('payslips.show', $payslip))->assertOk()->assertSee('45,000.00');
        $this->get(route('payroll-runs.register.export', $run))->assertOk()->assertDownload();

        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame($slipBefore, $payslip->fresh()->getAttributes());
        $this->assertDatabaseHas('generated_reports', ['report_type' => 'PAYROLL_REGISTER', 'generated_by' => $viewer->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'REPORT_GENERATED', 'user_id' => $viewer->getKey()]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_GENERATED']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_PUBLISHED']);
    }

    /** @return array<string, array{bool}> */
    public static function queueModes(): array
    {
        return ['synchronous' => [false], 'queued' => [true]];
    }

    #[DataProvider('queueModes')]
    public function test_viewer_cannot_generate_payslips_or_enqueue_generation(bool $queued): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);
        $detail = $this->detail();
        config(['deployment.queue_payslips' => $queued]);
        Queue::fake([GeneratePayslips::class]);

        $this->actingAs($viewer)->postJson(route('payroll-runs.payslips.generate', $detail->payrollRun))->assertForbidden();

        Queue::assertNotPushed(GeneratePayslips::class);
        $this->assertDatabaseCount('payslips', 0);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_GENERATED']);
    }

    public function test_viewer_cannot_publish_a_generated_finalized_payslip(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);
        $payslip = $this->payslip($this->detail());
        $before = $payslip->fresh()->getAttributes();

        $this->actingAs($viewer)->postJson(route('payslips.publish', $payslip))->assertForbidden();

        $this->assertSame($before, $payslip->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_PUBLISHED']);
    }

    /** @return array<string, array{string}> */
    public static function writerRoles(): array
    {
        return ['administrator' => [Role::ADMIN], 'officer' => [Role::PAYROLL_OFFICER], 'approver' => [Role::APPROVER]];
    }

    #[DataProvider('writerRoles')]
    public function test_authorized_writers_can_still_generate_and_publish_finalized_payslips(string $role): void
    {
        $user = $this->userWithRole($role);
        $detail = $this->detail();
        config(['deployment.queue_payslips' => false]);

        $this->actingAs($user)->post(route('payroll-runs.payslips.generate', $detail->payrollRun))->assertRedirect()->assertSessionHas('status', 'Generated 1 payslip(s).');
        $payslip = $detail->fresh()->payslip;
        $this->post(route('payslips.publish', $payslip))->assertRedirect();

        $this->assertSame('PUBLISHED', $payslip->fresh()->publication_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYSLIP_GENERATED', 'user_id' => $user->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'PAYSLIP_PUBLISHED', 'user_id' => $user->getKey()]);
    }

    public function test_export_permission_does_not_imply_payslip_generation_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::whereIn('permission_name', [Permission::PAYROLL_VIEW, Permission::REPORTS_EXPORT])->pluck('permission_id'));
        $reader = User::factory()->create();
        $reader->roles()->attach($role->getKey(), ['assigned_at' => now()]);
        $detail = $this->detail();

        $this->actingAs($reader)->get(route('payroll-runs.register.export', $detail->payrollRun))->assertOk();
        $this->postJson(route('payroll-runs.payslips.generate', $detail->payrollRun))->assertForbidden();

        $this->assertDatabaseCount('payslips', 0);
    }

    public function test_generation_permission_does_not_imply_report_export_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::factory()->create();
        $role->permissions()->attach(Permission::whereIn('permission_name', [Permission::PAYROLL_VIEW, Permission::REPORTS_GENERATE])->pluck('permission_id'));
        $writer = User::factory()->create();
        $writer->roles()->attach($role->getKey(), ['assigned_at' => now()]);
        $detail = $this->detail();

        $this->actingAs($writer)->get(route('payroll-runs.register', $detail->payrollRun))->assertOk()->assertDontSee(route('payroll-runs.register.export', $detail->payrollRun));
        $this->getJson(route('payroll-runs.register.export', $detail->payrollRun))->assertForbidden();

        $this->assertDatabaseCount('generated_reports', 0);
    }

    public function test_a_queued_generation_request_is_refused_after_its_requester_becomes_a_viewer(): void
    {
        $officer = $this->userWithRole(Role::PAYROLL_OFFICER);
        $this->assertTrue($officer->can(Permission::REPORTS_GENERATE));
        $detail = $this->detail();
        $job = new GeneratePayslips($detail->payroll_run_id, $officer->getKey());
        $officer->roles()->sync([Role::where('role_name', Role::VIEWER)->value('role_id') => ['assigned_at' => now()]]);

        try {
            $job->handle(app(PayslipGenerationService::class));
            $this->fail('A revoked requester generated payslips.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertDatabaseCount('payslips', 0);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'PAYSLIP_GENERATED']);
        }
    }

    public function test_a_queued_viewer_request_cannot_bypass_the_http_permission_check(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);
        $detail = $this->detail();

        try {
            (new GeneratePayslips($detail->payroll_run_id, $viewer->getKey()))->handle(app(PayslipGenerationService::class));
            $this->fail('A Viewer bypassed the HTTP guard using a queued request.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertDatabaseCount('payslips', 0);
        }
    }

    public function test_multiple_roles_preserve_the_additional_permissions_of_an_authorized_officer(): void
    {
        $user = $this->userWithRole(Role::VIEWER);
        $user->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);

        $this->assertTrue($user->can(Permission::REPORTS_GENERATE));
        $this->assertTrue($user->can(Permission::REPORTS_EXPORT));
    }

    private function permissionMigration(): Migration
    {
        return require database_path('migrations/2026_11_21_080314_separate_report_export_from_payslip_generation.php');
    }

    public function test_existing_legacy_grants_are_migrated_idempotently_without_reseeding_users_or_changing_payroll(): void
    {
        $this->freezeTime();
        $viewer = $this->userWithRole(Role::VIEWER);
        $viewerRole = Role::where('role_name', Role::VIEWER)->firstOrFail();
        $legacy = Permission::where('permission_name', Permission::REPORTS_GENERATE)->firstOrFail();
        $viewerRole->permissions()->attach($legacy->getKey());
        Permission::where('permission_name', Permission::REPORTS_EXPORT)->delete();
        $custom = Role::factory()->create();
        $custom->permissions()->attach($legacy->getKey());
        $detail = $this->detail();
        $before = $detail->fresh()->getAttributes();
        $userRoles = DB::table('user_roles')->orderBy('user_role_id')->get()->map(fn ($row): array => (array) $row)->all();
        $this->assertTrue($viewer->can(Permission::REPORTS_GENERATE));

        $migration = $this->permissionMigration();
        $migration->up();
        $firstGrants = DB::table('role_permissions')->orderBy('role_permission_id')->get()->map(fn ($row): array => (array) $row)->all();
        $migration->up();

        $freshViewer = $viewer->fresh();
        $this->assertTrue($freshViewer->can(Permission::REPORTS_EXPORT));
        $this->assertFalse($freshViewer->can(Permission::REPORTS_GENERATE));
        $this->assertTrue($custom->permissions()->where('permission_name', Permission::REPORTS_GENERATE)->exists());
        $this->assertTrue($custom->permissions()->where('permission_name', Permission::REPORTS_EXPORT)->exists());
        $this->assertSame($firstGrants, DB::table('role_permissions')->orderBy('role_permission_id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($userRoles, DB::table('user_roles')->orderBy('user_role_id')->get()->map(fn ($row): array => (array) $row)->all());
        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame(1, AuditLog::where('action', 'REPORT_PERMISSIONS_SPLIT')->count());
        $this->actingAs($freshViewer)->postJson(route('payroll-runs.payslips.generate', $detail->payrollRun))->assertForbidden();
    }

    public function test_migration_rollback_does_not_restore_the_viewer_write_grant(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);

        $this->permissionMigration()->down();

        $this->assertFalse($viewer->fresh()->can(Permission::REPORTS_GENERATE));
        $this->assertTrue($viewer->fresh()->can(Permission::REPORTS_EXPORT));
    }

    public function test_reseeding_keeps_the_viewer_read_only_and_preserves_export_access(): void
    {
        $viewer = $this->userWithRole(Role::VIEWER);
        $role = Role::where('role_name', Role::VIEWER)->firstOrFail();
        $role->permissions()->attach(Permission::where('permission_name', Permission::REPORTS_GENERATE)->value('permission_id'));

        $this->seed(RolePermissionSeeder::class);
        $this->seed(RolePermissionSeeder::class);

        $this->assertTrue($viewer->fresh()->can(Permission::REPORTS_EXPORT));
        $this->assertFalse($viewer->fresh()->can(Permission::REPORTS_GENERATE));
    }

    public function test_guests_cannot_export_or_mutate_payslips(): void
    {
        $detail = $this->detail();
        $payslip = $this->payslip($detail);

        $this->get(route('payroll-runs.register.export', $detail->payrollRun))->assertRedirect(route('login'));
        $this->post(route('payroll-runs.payslips.generate', $detail->payrollRun))->assertRedirect(route('login'));
        $this->post(route('payslips.publish', $payslip))->assertRedirect(route('login'));

        $this->assertSame('GENERATED', $payslip->fresh()->publication_status);
        $this->assertDatabaseCount('generated_reports', 0);
    }
}
