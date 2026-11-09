<?php

namespace Tests\Feature;

use App\Models\CompensationProfile;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentRecord;
use App\Models\EmploymentType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\Blockchain\PayrollRegistryClient;
use App\Services\PayrollComputationService;
use App\Services\PayrollVerificationService;
use Database\Seeders\DeductionTypeSeeder;
use Database\Seeders\EarningTypeSeeder;
use Database\Seeders\EmploymentTypeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\StatutoryConfigSeeder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('registry-pilot')]
class PayrollRegistryPilotTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (! config('blockchain.registry.pilot_enabled')) {
            $this->markTestSkipped('Opt in using the isolated config/besu/pilot.mjs environment.');
        }
        $this->assertSame('mysql', DB::getDriverName());
        $this->assertSame('lgu_payroll_qa_registry_pilot', DB::connection()->getDatabaseName());
        $this->assertSame('127.0.0.1', config('database.connections.mysql.host'));
        $this->assertSame('33316', (string) config('database.connections.mysql.port'));
        $this->artisan('migrate:fresh', ['--force' => true])->assertSuccessful();
        config(['blockchain.registry.enabled' => true, 'queue.default' => 'database']);
    }

    public function test_real_mysql_queue_bridge_and_besu_confirm_and_detect_a_synthetic_payroll_change(): void
    {
        $this->seed([RolePermissionSeeder::class, EmploymentTypeSeeder::class, EarningTypeSeeder::class, DeductionTypeSeeder::class, StatutoryConfigSeeder::class]);
        $officer = $this->roleUser(Role::PAYROLL_OFFICER);
        $reviewer = $this->roleUser(Role::APPROVER);
        $department = Department::factory()->create();
        $position = Position::factory()->create(['department_id' => $department->getKey(), 'salary_grade' => 15]);
        $employment = EmploymentRecord::factory()->create(['employee_id' => Employee::factory()->create()->getKey(),
            'department_id' => $department->getKey(), 'position_id' => $position->getKey(),
            'employment_type_id' => EmploymentType::where('employment_type_name', EmploymentType::PERMANENT)->value('employment_type_id')]);
        $schedule = SalarySchedule::factory()->create(['effective_from' => '2024-01-01', 'status' => 'ACTIVE']);
        $step = SalaryGradeStep::factory()->create(['salary_schedule_id' => $schedule->getKey(), 'grade_number' => 15, 'step_number' => 1, 'monthly_salary' => '45000.00']);
        CompensationProfile::factory()->create(['employment_id' => $employment->getKey(), 'grade_step_id' => $step->getKey(), 'basic_rate' => null, 'effective_from' => '2024-01-01']);
        $period = PayrollPeriod::factory()->create(['start_date' => '2026-11-01', 'end_date' => '2026-11-30', 'pay_date' => '2026-11-30']);
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey(), 'created_by' => $officer->getKey()]);
        app(PayrollComputationService::class)->computeRun($run);
        $detail = $run->details()->firstOrFail();
        $detail->update(['payable_days' => '22.00', 'input_reference' => 'SYNTHETIC-BESU-PILOT',
            'input_recorded_by' => $officer->getKey(), 'input_confirmed_at' => now()]);
        app(PayrollComputationService::class)->computeRun($run);
        $this->actingAs($officer)->post(route('payroll-runs.submit', $run))->assertRedirect();
        $this->actingAs($reviewer)->post(route('payroll-runs.approve', $run), ['remarks' => 'Reviewed synthetic pilot payroll'])->assertRedirect();
        $this->post(route('payroll-runs.finalize', $run))->assertRedirect();
        $registration = $run->registration()->firstOrFail();
        $this->assertSame('QUEUED', $registration->status);
        $this->artisan('blockchain:dispatch-registrations')->assertSuccessful();
        $this->workOnce();
        $evidence = null;
        for ($attempt = 0; $attempt < 40; $attempt++) {
            $evidence = app(PayrollRegistryClient::class)->read($registration)['registration'];
            if ($evidence !== null) {
                break;
            }
            usleep(500000);
        }
        $this->assertNotNull($evidence, 'The synthetic registration did not confirm on local Besu.');
        $registration->refresh()->update(['next_attempt_at' => now()]);
        $this->artisan('blockchain:dispatch-registrations')->assertSuccessful();
        $this->workOnce();
        $this->assertSame('CONFIRMED', $registration->fresh()->status);
        $verification = app(PayrollVerificationService::class);
        $this->assertSame('MATCH', $verification->verify($registration, $reviewer)->verification_result);
        $original = $detail->fresh()->net_pay;
        DB::table('payroll_details')->where('payroll_detail_id', $detail->getKey())->update(['net_pay' => '0.01']);
        $this->assertSame('MISMATCH', $verification->verify($registration, $reviewer)->verification_result);
        DB::table('payroll_details')->where('payroll_detail_id', $detail->getKey())->update(['net_pay' => $original]);
        $this->assertSame('MATCH', $verification->verify($registration, $reviewer)->verification_result);
        $this->artisan('blockchain:readiness', ['--json' => true])->assertSuccessful();
        file_put_contents(storage_path('app/private/besu/pilot-laravel-report.json'), json_encode([
            'database' => DB::connection()->getDatabaseName(), 'registration_id' => $registration->getKey(),
            'transaction_hash' => $evidence['transaction_hash'], 'block_number' => $evidence['block_number'],
            'registration_status' => 'CONFIRMED', 'checks' => ['MATCH', 'MISMATCH', 'MATCH'],
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)."\n");
    }

    private function workOnce(): void
    {
        $this->artisan('queue:work', ['connection' => 'database', '--queue' => 'blockchain', '--once' => true, '--tries' => 1, '--sleep' => 0])->assertSuccessful();
    }

    private function roleUser(string $name): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $name)->value('role_id'), ['assigned_at' => now()]);

        return $user;
    }
}
