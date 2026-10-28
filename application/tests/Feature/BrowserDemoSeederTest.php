<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollFinalization;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollWorkflowService;
use Database\Seeders\BrowserDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class BrowserDemoSeederTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function records(): array
    {
        $records = [];
        foreach (['users', 'employees', 'salary_schedules', 'salary_rates', 'employee_salary_records', 'payroll_batches',
            'payroll_entries', 'payroll_input_revisions', 'payroll_calculation_runs', 'payroll_calculation_items', 'statutory_previews'] as $table) {
            $records[$table] = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();
        }

        return $records;
    }

    public function test_opt_in_seed_creates_the_fictional_browser_scenario_and_real_login_accounts(): void
    {
        $this->seed(BrowserDemoSeeder::class);
        $this->assertDatabaseCount('users', 4);
        $this->assertDatabaseCount('employees', 3);
        $this->assertDatabaseCount('salary_schedules', 1);
        $this->assertDatabaseCount('salary_rates', 1);
        $this->assertDatabaseCount('employee_salary_records', 3);
        $this->assertDatabaseCount('payroll_input_revisions', 3);
        $this->assertDatabaseCount('statutory_previews', 3);
        foreach (['demo.admin' => Role::Administrator, 'demo.payroll' => Role::PayrollOfficer, 'demo.approver' => Role::Approver, 'demo.viewer' => Role::Viewer] as $username => $role) {
            $user = User::where('username', $username)->sole();
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check(BrowserDemoSeeder::PASSWORD, $user->password));
            $this->post('/login', ['username' => $username, 'password' => BrowserDemoSeeder::PASSWORD])->assertRedirect(route('dashboard'));
            $this->post('/logout')->assertRedirect(route('login'));
        }
        $batch = PayrollBatch::sole();
        $this->assertSame('2026-11-01', $batch->period_start->format('Y-m-d'));
        $this->assertTrue($batch->is_demonstration);
        $this->assertSame('draft', $batch->status);
        $this->assertSame(1, $batch->payroll_version);
        $snapshot = app(PayrollWorkflowService::class)->currentSnapshot($batch);
        $this->assertSame(['total_earnings' => '90000.00', 'total_deductions' => '13432.65', 'net_pay' => '76567.35'], $snapshot['totals']);
        $this->assertCount(3, $snapshot['employees']);
        foreach (['payroll_submissions', 'payroll_workflow_events', 'payroll_finalizations', 'payroll_correction_requests', 'payroll_commitments', 'payroll_anchor_attempts', 'payroll_verifications', 'payroll_rule_versions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->assertSame(['coterminous', 'part_time', 'permanent'], Employee::orderBy('personnel_classification')->pluck('personnel_classification')->map(fn ($category) => $category->value)->all());
        Http::assertNothingSent();
    }

    public function test_rerun_preserves_existing_passwords_inputs_and_exact_records(): void
    {
        $this->seed(BrowserDemoSeeder::class);
        User::where('username', 'demo.viewer')->sole()->forceFill(['password' => 'ChangedDemo2026!'])->save();
        $before = $this->records();
        $this->seed(BrowserDemoSeeder::class);
        $this->assertSame($before, $this->records());
        $this->assertTrue(Hash::check('ChangedDemo2026!', User::where('username', 'demo.viewer')->sole()->password));
    }

    public function test_browser_approval_remains_independent_and_rerun_preserves_finalized_payroll(): void
    {
        $this->seed(BrowserDemoSeeder::class);
        $batch = PayrollBatch::sole();
        $officer = User::where('username', 'demo.payroll')->sole();
        $approver = User::where('username', 'demo.approver')->sole();
        $service = app(PayrollWorkflowService::class);
        foreach (['submit', 'approve', 'finalize'] as $action) {
            $actor = $action === 'submit' ? $officer : $approver;
            $this->actingAs($actor)->withSession(['auth_version' => $actor->auth_version]);
            $this->post(route('payroll-reviews.transition', [$batch, $action]), [
                'record_version' => $batch->fresh()->record_version,
                'submission_id' => $service->latestSubmission($batch)?->id,
                'review_token' => $action === 'submit' ? $service->reviewToken($service->currentSnapshot($batch->fresh())) : null,
                'reason' => 'FICTIONAL browser demo '.$action, 'review_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }
        $this->assertFalse($service->isIndependent($officer, $batch, $service->latestSubmission($batch)));
        $final = PayrollFinalization::sole();
        $before = [$this->records(), $final->getAttributes()];
        $this->seed(BrowserDemoSeeder::class);
        $this->assertSame($before, [$this->records(), $final->fresh()->getAttributes()]);
        $this->assertSame('finalized', $batch->fresh()->status);
        $this->get(route('payroll-documents.index', $batch))->assertOk();
        $this->get(route('payroll-corrections.index', $batch))->assertOk();
    }

    public function test_existing_account_collision_is_rejected_without_resetting_it(): void
    {
        $user = User::factory()->create(['username' => 'demo.admin', 'name' => 'Existing person', 'role' => Role::Viewer]);
        $before = $user->fresh()->getAttributes();
        try {
            $this->seed(BrowserDemoSeeder::class);
            $this->fail('An unrelated account was adopted.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('conflicts with existing account demo.admin', $error->getMessage());
        }
        $this->assertSame($before, $user->fresh()->getAttributes());
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('employees', 0);
    }

    public static function collisions(): array
    {
        return [['employee'], ['schedule'], ['payroll']];
    }

    #[DataProvider('collisions')]
    public function test_conflicting_data_rolls_back_all_new_fixture_records(string $collision): void
    {
        $owner = User::factory()->create(['role' => Role::PayrollOfficer]);
        if ($collision === 'employee') {
            Employee::factory()->create(['employee_number' => 'DEMO-T001', 'first_name' => 'Existing employee']);
        } elseif ($collision === 'schedule') {
            SalarySchedule::create(['name' => 'Existing operator schedule', 'personnel_classification' => 'part_time', 'effective_from' => '2026-11-01', 'is_demonstration' => true, 'created_by' => $owner->id]);
        } else {
            PayrollBatch::forceCreate(['period_start' => '2026-11-01', 'is_demonstration' => true, 'status' => 'draft', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        }
        $before = $this->records();
        try {
            $this->seed(BrowserDemoSeeder::class);
            $this->fail('Conflicting data was overwritten.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('conflicts with existing', $error->getMessage());
        }
        $this->assertSame($before, $this->records());
    }

    public function test_default_seeder_still_creates_no_shared_accounts_or_demo_data(): void
    {
        $this->seed();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('employees', 0);
        $this->assertDatabaseCount('payroll_batches', 0);
    }

    public static function forbiddenEnvironments(): array
    {
        return [['production'], ['staging']];
    }

    #[DataProvider('forbiddenEnvironments')]
    public function test_nonlocal_environments_are_rejected_before_writing(string $environment): void
    {
        $this->app->detectEnvironment(fn () => $environment);
        try {
            $this->artisan('db:seed', ['--class' => BrowserDemoSeeder::class, '--force' => true]);
            $this->fail('A shared demo password was seeded outside local/testing.');
        } catch (RuntimeException $error) {
            $this->assertStringContainsString('restricted to local/testing', $error->getMessage());
        }
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('employees', 0);
    }
}
