<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmployeeEarningAssignment;
use App\Models\EmploymentHistory;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\MasterDataMutationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MasterDataDeletionTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = Role::ADMIN): User
    {
        $this->freezeTime();
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($user);

        return $user;
    }

    /** @return array<string, array{string, string}> */
    public static function references(): array
    {
        return [
            'department position' => ['department_position', 'Set its status to Inactive'],
            'department appointment' => ['department_appointment', 'Set its status to Inactive'],
            'department former history' => ['department_from_history', 'Set its status to Inactive'],
            'department destination history' => ['department_to_history', 'Set its status to Inactive'],
            'position appointment' => ['position_appointment', 'Set its status to Inactive'],
            'position former history' => ['position_from_history', 'Set its status to Inactive'],
            'position destination history' => ['position_to_history', 'Set its status to Inactive'],
            'appointment draft payroll' => ['appointment_draft', 'employment status to Inactive'],
            'appointment finalized payroll' => ['appointment_finalized', 'employment status to Inactive'],
            'grade step compensation' => ['step_compensation', 'salary schedule status or effective dates'],
            'schedule compensation' => ['schedule_compensation', 'Set its status to Inactive'],
            'earning assignment' => ['earning_assignment', 'Set its status to Inactive'],
            'earning payroll' => ['earning_payroll', 'Set its status to Inactive'],
            'deduction assignment' => ['deduction_assignment', 'Set its status to Inactive'],
            'deduction payroll' => ['deduction_payroll', 'Set its status to Inactive'],
            'contribution payroll' => ['contribution_payroll', 'effective dates for future rate changes'],
        ];
    }

    /** @return array{string, Model, list<Model>} */
    private function referencedFixture(string $case): array
    {
        if (str_starts_with($case, 'department_') || str_starts_with($case, 'position_')) {
            $department = str_starts_with($case, 'department_');
            $source = $department ? Department::factory()->create() : Position::factory()->create();
            $route = $department ? 'departments' : 'positions';
            if ($case === 'department_position') {
                return [$route, $source, [Position::factory()->create(['department_id' => $source->getKey()])]];
            }
            if (str_ends_with($case, '_appointment')) {
                return [$route, $source, [EmploymentRecord::factory()->create([
                    $department ? 'department_id' : 'position_id' => $source->getKey(),
                ])]];
            }
            $direction = str_contains($case, '_from_') ? 'from' : 'to';
            $history = EmploymentHistory::create([
                'employment_id' => EmploymentRecord::factory()->create()->getKey(),
                $direction.($department ? '_department_id' : '_position_id') => $source->getKey(),
                'change_type' => 'TRANSFER', 'effective_date' => '2026-01-01', 'recorded_by' => auth()->id(),
                'remarks' => 'Historical transfer reference retained by F14.',
            ]);

            return [$route, $source, [$history]];
        }
        if (str_starts_with($case, 'appointment_')) {
            $source = EmploymentRecord::factory()->create();
            $run = PayrollRun::factory()->create(['status' => $case === 'appointment_draft' ? 'DRAFT' : 'FINALIZED']);
            $detail = PayrollDetail::factory()->create(['employment_id' => $source->getKey(), 'payroll_run_id' => $run->getKey()]);

            return ['employment-records', $source, [$run, $detail]];
        }
        if (in_array($case, ['step_compensation', 'schedule_compensation'], true)) {
            $step = SalaryGradeStep::factory()->create();
            $profile = CompensationProfile::factory()->create(['grade_step_id' => $step->getKey(), 'basic_rate' => null]);

            return $case === 'step_compensation'
                ? ['salary-grade-steps', $step, [$profile]]
                : ['salary-schedules', $step->salarySchedule, [$step, $profile]];
        }
        if (in_array($case, ['earning_assignment', 'deduction_assignment'], true)) {
            $earning = $case === 'earning_assignment';
            $type = $earning ? EarningType::factory()->create() : DeductionType::factory()->create();
            $assignment = $earning ? EmployeeEarningAssignment::create([
                'employment_id' => EmploymentRecord::factory()->create()->getKey(), 'earning_type_id' => $type->getKey(),
                'frequency' => 'MONTHLY', 'amount' => 500, 'effective_from' => '2026-01-01',
            ]) : EmployeeDeductionAssignment::create([
                'employment_id' => EmploymentRecord::factory()->create()->getKey(), 'deduction_type_id' => $type->getKey(),
                'status' => 'INACTIVE', 'amount' => 100, 'effective_from' => '2026-01-01',
            ]);

            return [$earning ? 'earning-types' : 'deduction-types', $type, [$assignment]];
        }
        $detail = PayrollDetail::factory()->create(['payroll_run_id' => PayrollRun::factory()->create(['status' => 'FINALIZED'])->getKey()]);
        if ($case === 'earning_payroll') {
            $type = EarningType::factory()->create();
            $line = $detail->earnings()->create(['earning_type_id' => $type->getKey(), 'earning_name_snapshot' => 'Original earning', 'amount' => 500, 'is_taxable' => false]);

            return ['earning-types', $type, [$detail, $line]];
        }
        if ($case === 'deduction_payroll') {
            $type = DeductionType::factory()->create();
            $line = $detail->deductions()->create(['deduction_type_id' => $type->getKey(), 'deduction_name_snapshot' => 'Original deduction', 'amount' => 100]);

            return ['deduction-types', $type, [$detail, $line]];
        }
        $rate = ContributionRate::factory()->create();
        $line = $detail->employerContributions()->create(['contribution_rate_id' => $rate->getKey(), 'contribution_type_snapshot' => 'Original employer contribution', 'amount' => 200]);

        return ['contribution-rates', $rate, [$detail, $line]];
    }

    #[DataProvider('references')]
    public function test_referenced_source_deletion_returns_422_with_history_and_financial_rows_unchanged(string $case, string $alternative): void
    {
        $this->signIn();
        [$route, $source, $references] = $this->referencedFixture($case);
        $before = $source->refresh()->getAttributes();
        $referencedBefore = array_map(fn (Model $record): array => $record->refresh()->getAttributes(), $references);

        $this->deleteJson(route($route.'.destroy', $source))->assertUnprocessable()->assertJsonValidationErrors('delete')
            ->assertSee('cannot be deleted')->assertSee($alternative)->assertDontSee('SQLSTATE');

        $this->assertSame($before, $source->fresh()->getAttributes());
        foreach ($references as $index => $reference) {
            $this->assertSame($referencedBefore[$index], $reference->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{class-string<Model>, string}> */
    public static function unusedSources(): array
    {
        return [
            'department' => [Department::class, 'departments'], 'position' => [Position::class, 'positions'],
            'appointment' => [EmploymentRecord::class, 'employment-records'], 'step' => [SalaryGradeStep::class, 'salary-grade-steps'],
            'schedule' => [SalarySchedule::class, 'salary-schedules'], 'earning' => [EarningType::class, 'earning-types'],
            'deduction' => [DeductionType::class, 'deduction-types'], 'contribution' => [ContributionRate::class, 'contribution-rates'],
        ];
    }

    /** @param class-string<Model> $model */
    #[DataProvider('unusedSources')]
    public function test_unused_source_deletion_still_succeeds_with_transactional_audit(string $model, string $route): void
    {
        $actor = $this->signIn();
        $source = $model::factory()->create();

        $this->delete(route($route.'.destroy', $source))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertModelMissing($source);
        $this->assertDatabaseHas('audit_logs', ['record_table' => $source->getTable(), 'record_id' => (string) $source->getKey(), 'user_id' => $actor->getKey()]);
    }

    public function test_browser_delete_rejection_is_visible_on_the_existing_list_page(): void
    {
        $this->signIn();
        $department = Department::factory()->create();
        Position::factory()->create(['department_id' => $department->getKey()]);
        $url = route('departments.index');

        $this->followingRedirects()->from($url)->delete(route('departments.destroy', $department))->assertOk()
            ->assertSee('This department is referenced')->assertSee('Set its status to Inactive instead.');

        $this->assertModelExists($department);
    }

    public function test_inactivation_remains_available_for_a_referenced_type(): void
    {
        $this->signIn();
        [$route, $source, $references] = $this->referencedFixture('earning_assignment');

        $this->put(route($route.'.update', $source), [...$source->attributesToArray(), 'status' => 'INACTIVE'])->assertRedirect();

        $this->assertSame('INACTIVE', $source->fresh()->status);
        $this->assertModelExists($references[0]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'EARNING_TYPE_UPDATED']);
    }

    public function test_salary_schedule_with_only_unused_steps_can_be_cleaned_up_with_audited_children(): void
    {
        $this->signIn();
        $schedule = SalarySchedule::factory()->create();
        $step = SalaryGradeStep::factory()->create(['salary_schedule_id' => $schedule->getKey()]);

        $this->delete(route('salary-schedules.destroy', $schedule))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertModelMissing($schedule);
        $this->assertModelMissing($step);
        $event = AuditLog::where('action', 'SALARY_SCHEDULE_DELETED')->sole();
        $this->assertSame($step->getKey(), $event->old_values['gradeSteps'][0]['grade_step_id']);
    }

    public function test_foreign_key_fallback_handles_a_reference_added_after_the_guard_and_rolls_back_every_write(): void
    {
        $this->signIn();
        $type = DeductionType::factory()->create();
        $detail = PayrollDetail::factory()->create();
        DeductionType::deleting(function (DeductionType $record) use ($detail): void {
            $detail->deductions()->create(['deduction_type_id' => $record->getKey(), 'deduction_name_snapshot' => 'Late reference', 'amount' => 100]);
        });

        try {
            $this->deleteJson(route('deduction-types.destroy', $type))->assertUnprocessable()->assertJsonValidationErrors('delete')
                ->assertSee('cannot be deleted')->assertDontSee('SQLSTATE');
        } finally {
            DeductionType::flushEventListeners();
        }

        $this->assertModelExists($type);
        $this->assertModelExists($detail);
        $this->assertSame(0, $detail->deductions()->count());
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{string, int, string, string}> */
    public static function unrelatedDatabaseFailures(): array
    {
        return [
            'mysql duplicate' => ['23000', 1062, 'Duplicate entry', 'delete from departments where department_id = ?'],
            'sqlite unique' => ['23000', 19, 'UNIQUE constraint failed', 'delete from departments where department_id = ?'],
            'foreign key insert' => ['23000', 1452, 'Cannot add or update a child row', 'insert into unrelated_table values (?)'],
        ];
    }

    #[DataProvider('unrelatedDatabaseFailures')]
    public function test_unrelated_database_errors_are_not_misreported_as_a_referenced_delete(string $state, int $code, string $message, string $sql): void
    {
        $this->signIn();
        $source = Department::factory()->create();
        Department::deleting(function () use ($state, $code, $message, $sql): void {
            $failure = new PDOException($message);
            $failure->errorInfo = [$state, $code, $message];
            throw new QueryException('mysql', $sql, [], $failure);
        });

        try {
            $this->deleteJson(route('departments.destroy', $source))->assertServerError();
        } finally {
            Department::flushEventListeners();
        }

        $this->assertModelExists($source);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_audit_failure_after_an_unused_delete_still_rolls_back_the_deletion(): void
    {
        $this->signIn();
        $source = Department::factory()->create();
        AuditLog::creating(function (): void {
            throw new RuntimeException('Synthetic audit storage failure');
        });

        try {
            $this->deleteJson(route('departments.destroy', $source))->assertServerError();
        } finally {
            AuditLog::flushEventListeners();
        }

        $this->assertModelExists($source);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_shared_service_blocks_period_deletion_even_if_the_controller_check_is_bypassed(): void
    {
        $actor = $this->signIn();
        $period = PayrollPeriod::factory()->create();
        $run = PayrollRun::factory()->create(['payroll_period_id' => $period->getKey()]);

        try {
            app(MasterDataMutationService::class)->delete($period, $actor);
            $this->fail('Expected the period reference guard.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('delete', $exception->errors());
        }

        $this->assertModelExists($period);
        $this->assertModelExists($run);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_viewer_cannot_reach_source_deletion_or_learn_reference_details(): void
    {
        $this->signIn(Role::VIEWER);
        [$route, $source] = $this->referencedFixture('department_position');

        $this->deleteJson(route($route.'.destroy', $source))->assertForbidden()->assertDontSee('This department is referenced');

        $this->assertModelExists($source);
    }

    public function test_missing_source_returns_404(): void
    {
        $this->signIn();

        $this->deleteJson(route('departments.destroy', 999999))->assertNotFound();
    }

    public function test_guest_cannot_delete_source_data(): void
    {
        $source = Department::factory()->create();

        $this->delete(route('departments.destroy', $source))->assertRedirect(route('login'));

        $this->assertModelExists($source);
    }
}
