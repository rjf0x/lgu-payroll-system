<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollPreparationService;
use App\Services\SalaryService;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DepartmentSalaryScheduleTest extends TestCase
{
    private User $admin;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => Role::Administrator]);
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function employee(string $department, PersonnelClassification $classification = PersonnelClassification::Permanent): Employee
    {
        return Employee::factory()->create(['office' => $department, 'personnel_classification' => $classification, 'employment_start_date' => '2020-01-01']);
    }

    private function data(string $scope = 'all', string $date = '2026-01-01', bool $demo = true): array
    {
        return ['name' => 'FICTIONAL department salary', 'department_scope' => $scope, 'effective_from' => $date, 'is_demonstration' => $demo];
    }

    private function schedule(string $scope = 'all', string $date = '2026-01-01', bool $publish = true, bool $demo = true): SalarySchedule
    {
        $this->actor($this->admin);
        $this->post('/salary-schedules', $this->data($scope, $date, $demo))->assertSessionHasNoErrors();
        $schedule = SalarySchedule::orderByDesc('id')->firstOrFail();
        $this->post(route('salary-rates.store', $schedule), ['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00'])->assertSessionHasNoErrors();
        if ($publish) {
            $this->post(route('salary-schedules.publish', $schedule), ['authority_reference' => 'FICTIONAL DEPARTMENT AUTHORITY', 'authorization_confirmed' => 1])->assertSessionHasNoErrors();
        }

        return $schedule->fresh();
    }

    private function assign(Employee $employee, SalarySchedule $schedule, string $date = '2026-01-01')
    {
        $this->actor($this->officer);

        return $this->post(route('employee-salaries.store', $employee), [
            'salary_rate_id' => $schedule->rates()->sole()->id, 'effective_from' => $date,
            'change_type' => 'initial', 'position' => $employee->position, 'step_basis_date' => '2020-01-01',
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Fictional department scenario', 'authorization_confirmed' => 1,
        ]);
    }

    private function batch(): PayrollBatch
    {
        return PayrollBatch::forceCreate(['period_start' => '2026-10-01', 'is_demonstration' => true, 'status' => 'draft', 'created_by' => $this->officer->id, 'updated_by' => $this->officer->id]);
    }

    public function test_dropdown_uses_existing_departments_and_all_instead_of_classification(): void
    {
        $this->employee('Accounting');
        $this->employee('Human Resources')->update(['is_active' => false]);
        $this->employee('<Fictional & Department>');
        $this->employee('Niño Office');
        $this->employee('Nino Office');
        $this->actor($this->admin);
        $this->get('/salary-schedules/create')->assertOk()->assertSee('All departments')->assertSee('Accounting')->assertSee('Human Resources')
            ->assertSee('&lt;Fictional &amp; Department&gt;', false)->assertSee('Niño Office')->assertSee('Nino Office')
            ->assertDontSee('name="personnel_classification"', false)->assertDontSee('Covered personnel classification');
        $specific = $this->schedule('department:Accounting');
        $all = $this->schedule();
        $this->assertSame('Accounting', $specific->department);
        $this->assertNull($specific->personnel_classification);
        $this->assertSame(SalarySchedule::coverageKey('accounting'), $specific->coverage_key);
        $this->assertNull($all->department);
        $this->assertSame('All departments', $all->coverage_label);
        $this->get('/salary-schedules')->assertOk()->assertSee('Accounting')->assertSee('All departments');
        $this->get(route('salary-schedules.show', $specific))->assertOk()->assertSee('Accounting');
    }

    public function test_department_specific_precedes_newer_global_and_unpublished_or_future_sources_do_not_apply(): void
    {
        $accounting = $this->employee('Accounting');
        $hr = $this->employee('Human Resources');
        $old = $this->schedule();
        $specific = $this->schedule('department:Accounting', '2026-04-01');
        $newGlobal = $this->schedule('all', '2026-07-01');
        $this->schedule('department:Accounting', '2026-09-01', false);
        $this->schedule('department:Accounting', '2027-01-01');
        $service = app(SalaryService::class);
        $this->assertSame($old->id, $service->applicableSchedule($accounting, '2026-03-01', true)->id);
        $this->assertSame($specific->id, $service->applicableSchedule($accounting, '2026-10-01', true)->id);
        $this->assertSame($newGlobal->id, $service->applicableSchedule($hr, '2026-10-01', true)->id);
        $this->assertNull($service->applicableSchedule($accounting, '2026-10-01', false));
        $this->assertNull($service->applicableSchedule($accounting, '2025-12-31', true));
    }

    public function test_all_departments_can_authorize_each_personnel_classification(): void
    {
        $all = $this->schedule();
        foreach (PersonnelClassification::cases() as $classification) {
            $employee = $this->employee('Department '.$classification->value, $classification);
            $this->assign($employee, $all)->assertSessionHasNoErrors();
            $record = EmployeeSalaryRecord::where('employee_id', $employee->id)->sole();
            $this->assertSame($classification, $record->personnel_classification);
            $this->assertSame('30000.00', $record->monthly_salary);
            $this->assertSame($all->id, $record->rate->salary_schedule_id);
            $this->get(route('employee-salaries.create', $employee).'?effective_from=2026-10-01')->assertOk()->assertSee('All departments');
        }
    }

    public function test_wrong_department_rate_is_rejected_and_transfer_does_not_rewrite_salary_history(): void
    {
        $accounting = $this->employee('Accounting');
        $hr = $this->employee('Human Resources');
        $specific = $this->schedule('department:Accounting');
        $this->assign($hr, $specific)->assertSessionHasErrors('salary_rate_id');
        $this->assign($accounting, $specific)->assertSessionHasNoErrors();
        $record = EmployeeSalaryRecord::sole();
        $before = $record->getAttributes();
        $accounting->update(['office' => 'Human Resources']);
        $source = app(PayrollPreparationService::class)->source($accounting->fresh(), $this->batch());
        $this->assertNotNull($source['issue']);
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->get(route('employee-salaries.create', $accounting).'?effective_from=2026-10-01')->assertOk()->assertSee('No published schedule matches this department');
    }

    public function test_irrelevant_global_midmonth_change_does_not_block_specific_department(): void
    {
        $employee = $this->employee('Accounting');
        $specific = $this->schedule('department:Accounting');
        $this->assign($employee, $specific)->assertSessionHasNoErrors();
        $this->schedule('all', '2026-10-15');
        $source = app(PayrollPreparationService::class)->source($employee, $this->batch());
        $this->assertNull($source['issue']);
        $this->assertSame($specific->id, $source['record']->rate->salary_schedule_id);
    }

    public function test_actual_midmonth_department_switch_blocks_whole_month_preparation(): void
    {
        $employee = $this->employee('Accounting');
        $global = $this->schedule();
        $this->assign($employee, $global)->assertSessionHasNoErrors();
        $this->schedule('department:Accounting', '2026-10-15');
        $source = app(PayrollPreparationService::class)->source($employee, $this->batch());
        $this->assertStringContainsString('within this month', $source['issue']);
    }

    public function test_normalized_duplicate_scope_is_rejected_but_all_and_one_can_share_a_date(): void
    {
        $employee = $this->employee('Accounting');
        $this->schedule('department:Accounting');
        $employee->update(['office' => ' accounting ']);
        $this->actor($this->admin);
        $this->post('/salary-schedules', $this->data('department:accounting'))->assertSessionHasErrors('effective_from');
        $this->schedule();
        $this->assertDatabaseCount('salary_schedules', 2);
    }

    public function test_case_only_department_edits_keep_the_selected_scope_and_cached_form_valid(): void
    {
        $employee = $this->employee('Accounting');
        $draft = $this->schedule('department:Accounting', '2026-01-01', false);
        $key = $draft->coverage_key;
        $employee->update(['office' => ' accounting ']);
        $this->actor($this->admin);
        $this->get(route('salary-schedules.edit', $draft))->assertOk()->assertSee('value="department:accounting" selected', false)
            ->assertDontSee('value="all" selected', false);
        $this->put(route('salary-schedules.update', $draft), $this->data('department:Accounting'))->assertSessionHasNoErrors();
        $this->assertSame($key, $draft->fresh()->coverage_key);
        $this->assertNotNull($draft->fresh()->department);
    }

    public static function invalidCoverage(): array
    {
        return [[null], ['department:Does not exist'], [['all']], ['invalid']];
    }

    #[DataProvider('invalidCoverage')]
    public function test_missing_or_forged_department_coverage_is_rejected(mixed $value): void
    {
        $this->actor($this->admin);
        $this->post('/salary-schedules', array_replace($this->data(), ['department_scope' => $value]))->assertSessionHasErrors('department_scope');
        $this->assertDatabaseCount('salary_schedules', 0);
    }

    public function test_classification_cannot_be_overposted_and_draft_edits_update_only_valid_scope(): void
    {
        $this->employee('Accounting');
        $draft = $this->schedule('all', '2026-01-01', false);
        $this->post('/salary-schedules', $this->data() + ['personnel_classification' => 'permanent'])->assertSessionHasErrors('personnel_classification');
        $this->put(route('salary-schedules.update', $draft), $this->data('department:Accounting'))->assertSessionHasNoErrors();
        $this->assertSame('Accounting', $draft->fresh()->department);
        $this->post(route('salary-schedules.publish', $draft), ['authority_reference' => 'FICTIONAL', 'authorization_confirmed' => 1])->assertSessionHasNoErrors();
        $before = $draft->fresh()->getAttributes();
        $this->put(route('salary-schedules.update', $draft), $this->data())->assertSessionHasErrors('schedule');
        $this->assertSame($before, $draft->fresh()->getAttributes());
        try {
            $draft->fresh()->forceFill(['department' => 'Human Resources'])->save();
            $this->fail('Published coverage was mutable.');
        } catch (LogicException) {
            $this->assertSame($before, $draft->fresh()->getAttributes());
        }
    }

    public function test_legacy_sources_and_amounts_survive_schema_round_trip_without_expanding_coverage(): void
    {
        $legacy = SalarySchedule::create(['name' => 'Preserved legacy source', 'personnel_classification' => 'permanent', 'effective_from' => '2020-01-01', 'is_demonstration' => true, 'created_by' => $this->admin->id]);
        $legacy->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
        $legacy->forceFill(['published_at' => now(), 'authority_reference' => 'ORIGINAL AUTHORITY'])->save();
        $employee = $this->employee('Accounting');
        $this->assign($employee, $legacy)->assertSessionHasNoErrors();
        $before = [$legacy->fresh()->getAttributes(), EmployeeSalaryRecord::sole()->getAttributes()];
        $migration = require database_path('migrations/2026_10_28_000001_add_department_coverage_to_salary_schedules.php');
        $migration->down();
        $migration->up();
        $this->assertSame($before, [$legacy->fresh()->getAttributes(), EmployeeSalaryRecord::sole()->getAttributes()]);
        $this->assertSame($legacy->id, app(SalaryService::class)->applicableSchedule($employee, '2026-10-01', true)->id);
        $other = $this->employee('Accounting', PersonnelClassification::PartTime);
        $this->assertNull(app(SalaryService::class)->applicableSchedule($other, '2026-10-01', true));
        $this->assertNull($legacy->fresh()->coverage_key);
        $this->actor($this->admin);
        $this->get(route('salary-schedules.show', $legacy))->assertOk()->assertSee('Legacy classification: Permanent');
    }

    public function test_department_history_prevents_destructive_schema_rollback(): void
    {
        $schedule = $this->schedule();
        try {
            (require database_path('migrations/2026_10_28_000001_add_department_coverage_to_salary_schedules.php'))->down();
            $this->fail('Department coverage was discarded.');
        } catch (LogicException) {
            $this->assertSame($schedule->coverage_key, SalarySchedule::sole()->coverage_key);
        }
    }
}
