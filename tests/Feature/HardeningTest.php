<?php

namespace Tests\Feature;

use App\Http\Controllers\LeaveApplicationController;
use App\Models\AuditLog;
use App\Models\DeductionType;
use App\Models\Employee;
use App\Models\LeaveApplication;
use App\Models\LeaveType;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PayrollApprovalService;
use App\Services\PayrollComputationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HardeningTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $roleName): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $roleName)->firstOrFail(), ['assigned_at' => now()]);

        return $user;
    }

    public function test_last_administrator_cannot_be_deactivated_or_lose_administrative_access(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $adminRole = Role::where('role_name', Role::ADMIN)->firstOrFail();
        $viewerRole = Role::where('role_name', Role::VIEWER)->firstOrFail();
        $payload = ['username' => $admin->username, 'account_status' => 'INACTIVE', 'roles' => [$adminRole->role_id]];

        $this->actingAs($admin)->putJson(route('users.update', $admin), $payload)->assertUnprocessable()->assertJsonValidationErrors('account_status');
        $this->putJson(route('users.update', $admin), array_replace($payload, ['account_status' => 'ACTIVE', 'roles' => [$viewerRole->role_id]]))
            ->assertUnprocessable()->assertJsonValidationErrors('account_status');

        $this->assertSame('ACTIVE', $admin->fresh()->account_status);
        $this->assertTrue($admin->fresh()->hasRole(Role::ADMIN));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_UPDATED']);
    }

    public function test_user_and_role_changes_are_audited_without_credentials(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = User::factory()->create();
        $role = Role::where('role_name', Role::VIEWER)->firstOrFail();

        $this->actingAs($admin)->put(route('users.update', $target), ['username' => 'updated-user', 'account_status' => 'ACTIVE', 'roles' => [$role->role_id], 'password' => 'never-log-this-password'])->assertRedirect();

        $audit = AuditLog::where('action', 'USER_UPDATED')->firstOrFail();
        $this->assertSame('updated-user', $target->fresh()->username);
        $this->assertSame([$role->role_id], $audit->new_values['roles']);
        $this->assertTrue($audit->new_values['password_changed']);
        $this->assertStringNotContainsString('never-log-this-password', json_encode($audit->new_values));
        $this->assertArrayNotHasKey('password_hash', $audit->new_values);
    }

    public function test_another_administrator_can_be_deactivated_when_access_remains(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $other = User::factory()->create();
        $role = Role::where('role_name', Role::ADMIN)->firstOrFail();
        $other->roles()->attach($role, ['assigned_at' => now()]);

        $this->actingAs($admin)->put(route('users.update', $other), ['username' => $other->username, 'account_status' => 'INACTIVE', 'roles' => [$role->role_id]])->assertRedirect();

        $this->assertSame('INACTIVE', $other->fresh()->account_status);
        $this->assertSame('ACTIVE', $admin->fresh()->account_status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'USER_UPDATED', 'record_id' => (string) $other->getKey()]);
    }

    public function test_deleting_an_account_with_payroll_history_preserves_records_and_explains_the_constraint(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $target = User::factory()->create();
        $run = PayrollRun::factory()->create(['created_by' => $target->getKey()]);

        $this->actingAs($admin)->from(route('users.show', $target))->delete(route('users.destroy', $target))
            ->assertRedirect(route('users.show', $target))->assertSessionHas('error', 'This account is referenced by records and cannot be deleted. Deactivate it instead.');

        $this->assertModelExists($target);
        $this->assertModelExists($run);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'USER_DELETED']);
    }

    public function test_audit_logger_redacts_nested_secrets(): void
    {
        $log = app(AuditLogger::class)->log('TEST', 'Security', newValues: ['Password' => 'secret-one', 'nested' => ['api_key' => 'secret-two', 'safe' => 'keep']]);

        $this->assertSame('[REDACTED]', $log->new_values['Password']);
        $this->assertSame('[REDACTED]', $log->new_values['nested']['api_key']);
        $this->assertSame('keep', $log->new_values['nested']['safe']);
    }

    public function test_period_dates_cannot_change_after_a_run_exists_but_period_can_be_closed(): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'pay_date' => '2026-01-31']);
        PayrollRun::factory()->create(['payroll_period_id' => $period->getKey()]);
        $payload = ['period_name' => $period->period_name, 'payroll_type' => 'MONTHLY', 'start_date' => '2026-01-02', 'end_date' => '2026-01-31', 'pay_date' => '2026-01-31', 'status' => 'OPEN'];

        $this->actingAs($user)->putJson(route('payroll-periods.update', $period), $payload)->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->put(route('payroll-periods.update', $period), array_replace($payload, ['start_date' => '2026-01-01', 'status' => 'CLOSED']))->assertRedirect();

        $this->assertSame('2026-01-01', $period->fresh()->start_date->toDateString());
        $this->assertSame('CLOSED', $period->fresh()->status);
    }

    public function test_closed_period_rejects_new_payroll_and_open_period_numbers_are_unique(): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $closed = PayrollPeriod::factory()->create(['status' => 'CLOSED']);
        $open = PayrollPeriod::factory()->create(['status' => 'OPEN']);

        $this->actingAs($user)->postJson(route('payroll-runs.store'), ['payroll_period_id' => $closed->getKey(), 'run_type' => 'REGULAR'])->assertUnprocessable()->assertJsonValidationErrors('payroll_period_id');
        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $open->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();
        $this->post(route('payroll-runs.store'), ['payroll_period_id' => $open->getKey(), 'run_type' => 'REGULAR'])->assertRedirect();

        $this->assertSame([1, 2], PayrollRun::where('payroll_period_id', $open->getKey())->orderBy('run_number')->pluck('run_number')->all());
        $this->assertDatabaseMissing('payroll_runs', ['payroll_period_id' => $closed->getKey()]);
    }

    public function test_database_prevents_period_deletion_from_cascading_payroll_history(): void
    {
        $run = PayrollRun::factory()->create(['status' => 'FINALIZED']);

        try {
            DB::table('payroll_periods')->where('payroll_period_id', $run->payroll_period_id)->delete();
            $this->fail('Expected the foreign key to prevent deletion.');
        } catch (QueryException) {
            $this->assertModelExists($run);
        }
    }

    public function test_settings_keys_cannot_be_duplicated(): void
    {
        $this->expectException(QueryException::class);

        SystemSetting::factory()->create(['setting_key' => 'system_name']);
    }

    public function test_stale_pending_model_cannot_approve_a_finalized_run(): void
    {
        $user = $this->userWithRole(Role::APPROVER);
        $stale = PayrollRun::factory()->create(['status' => 'PENDING_APPROVAL']);
        PayrollRun::whereKey($stale->getKey())->update(['status' => 'FINALIZED']);

        try {
            app(PayrollApprovalService::class)->approve($stale, $user);
            $this->fail('A stale run must not bypass the current status.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('FINALIZED', $stale->fresh()->status);
        $this->assertDatabaseCount('payroll_approvals', 0);
    }

    public function test_stale_draft_model_cannot_recompute_a_finalized_run(): void
    {
        $stale = PayrollRun::factory()->create();
        PayrollRun::whereKey($stale->getKey())->update(['status' => 'FINALIZED']);

        try {
            app(PayrollComputationService::class)->computeRun($stale);
            $this->fail('A stale run must not overwrite finalized details.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('FINALIZED', $stale->fresh()->status);
    }

    public function test_stale_leave_review_cannot_apply_usage_after_another_decision(): void
    {
        $user = $this->userWithRole(Role::ADMIN);
        $employee = Employee::factory()->create();
        $type = LeaveType::factory()->create();
        $this->actingAs($user)->post(route('leave-applications.store'), ['employee_id' => $employee->getKey(), 'leave_type_id' => $type->getKey(), 'start_date' => '2026-11-09', 'end_date' => '2026-11-09', 'total_days' => 1])->assertRedirect();
        $stale = LeaveApplication::firstOrFail();
        LeaveApplication::whereKey($stale->getKey())->update(['approval_status' => 'REJECTED']);

        try {
            app(LeaveApplicationController::class)->approve($stale);
            $this->fail('A stale review must not record leave usage.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame('REJECTED', $stale->fresh()->approval_status);
        $this->assertDatabaseCount('leave_transactions', 0);
    }

    public static function restrictedDeductions(): array
    {
        return [[['is_statutory' => true]], [['status' => 'INACTIVE']], [['deduction_category' => 'LBP']]];
    }

    #[DataProvider('restrictedDeductions')]
    public function test_manual_payroll_cannot_inject_statutory_inactive_or_lbp_deductions(array $attributes): void
    {
        $user = $this->userWithRole(Role::PAYROLL_OFFICER);
        $detail = PayrollDetail::factory()->create();
        $type = DeductionType::factory()->create(array_replace(['status' => 'ACTIVE', 'is_statutory' => false, 'deduction_category' => 'OTHER'], $attributes));

        $this->actingAs($user)->putJson(route('payroll-details.update', [$detail->payrollRun, $detail]), ['deductions' => [$type->getKey() => 100]])
            ->assertUnprocessable()->assertJsonValidationErrors('deductions');

        $this->assertSame('45000.00', $detail->fresh()->net_pay);
        $this->assertDatabaseCount('payroll_deductions', 0);
    }
}
