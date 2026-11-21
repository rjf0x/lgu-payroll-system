<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CompensationProfile;
use App\Models\ContributionRate;
use App\Models\DeductionType;
use App\Models\Department;
use App\Models\EarningType;
use App\Models\Employee;
use App\Models\EmployeeDeductionAssignment;
use App\Models\EmployeeEarningAssignment;
use App\Models\EmploymentHistory;
use App\Models\EmploymentRecord;
use App\Models\PayrollPeriod;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryGradeStep;
use App\Models\SalarySchedule;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\MasterDataMutationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class MasterDataAuditTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::where('role_name', Role::ADMIN)->value('role_id'), ['assigned_at' => now()]);
        $actor->roles()->attach(Role::where('role_name', Role::PAYROLL_OFFICER)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($actor);

        return $actor;
    }

    /** @return array<string, array{string, class-string<Model>, string, string, string}> */
    public static function resources(): array
    {
        return [
            'department' => ['departments', Department::class, 'DEPARTMENT', 'department_name', 'Revised office'],
            'position' => ['positions', Position::class, 'POSITION', 'position_title', 'Revised position'],
            'employee' => ['employees', Employee::class, 'EMPLOYEE', 'first_name', 'Revisedname'],
            'employment' => ['employment-records', EmploymentRecord::class, 'EMPLOYMENT_RECORD', 'appointment_number', 'APPT-REVISED'],
            'schedule' => ['salary-schedules', SalarySchedule::class, 'SALARY_SCHEDULE', 'schedule_name', 'Revised salary schedule'],
            'grade step' => ['salary-grade-steps', SalaryGradeStep::class, 'SALARY_GRADE_STEP', 'monthly_salary', '45678.90'],
            'compensation' => ['compensation-profiles', CompensationProfile::class, 'COMPENSATION_PROFILE', 'basic_rate', '45678.90'],
            'earning type' => ['earning-types', EarningType::class, 'EARNING_TYPE', 'earning_name', 'Revised allowance'],
            'deduction type' => ['deduction-types', DeductionType::class, 'DEDUCTION_TYPE', 'deduction_name', 'Revised deduction'],
            'earning assignment' => ['employee-earning-assignments', EmployeeEarningAssignment::class, 'EMPLOYEE_EARNING_ASSIGNMENT', 'amount', '1500.00'],
            'deduction assignment' => ['employee-deduction-assignments', EmployeeDeductionAssignment::class, 'EMPLOYEE_DEDUCTION_ASSIGNMENT', 'amount', '500.00'],
            'tax bracket' => ['tax-brackets', TaxBracket::class, 'TAX_BRACKET', 'base_tax', '123.45'],
            'contribution' => ['contribution-rates', ContributionRate::class, 'CONTRIBUTION_RATE', 'employee_rate', '0.0300'],
            'period' => ['payroll-periods', PayrollPeriod::class, 'PERIOD', 'period_name', 'Revised payroll period'],
        ];
    }

    /** @param class-string<Model> $modelClass */
    private function fixture(string $modelClass): Model
    {
        return match ($modelClass) {
            EmployeeEarningAssignment::class => new EmployeeEarningAssignment([
                'employment_id' => EmploymentRecord::factory()->create()->getKey(), 'earning_type_id' => EarningType::factory()->create()->getKey(),
                'amount' => 1000, 'percentage' => null, 'frequency' => 'MONTHLY', 'effective_from' => '2026-01-01', 'effective_to' => null,
            ]),
            EmployeeDeductionAssignment::class => new EmployeeDeductionAssignment([
                'employment_id' => EmploymentRecord::factory()->create()->getKey(), 'deduction_type_id' => DeductionType::factory()->create()->getKey(),
                'amount' => 100, 'percentage' => null, 'reference_number' => 'REF-001', 'status' => 'ACTIVE', 'effective_from' => '2026-01-01', 'effective_to' => null,
            ]),
            default => $modelClass::factory()->make(),
        };
    }

    /** @return array<string, mixed> */
    private function payload(Model $record): array
    {
        $attributes = array_intersect_key($record->getAttributes(), array_flip($record->getFillable()));
        foreach (['birth_date', 'start_date', 'end_date', 'pay_date', 'effective_from', 'effective_to'] as $field) {
            if (isset($attributes[$field])) {
                $attributes[$field] = substr((string) $attributes[$field], 0, 10);
            }
        }
        if ($record instanceof CompensationProfile) {
            $attributes['basis'] = 'DIRECT_RATE';
        }

        return $attributes;
    }

    #[DataProvider('resources')]
    public function test_source_creation_updates_and_deletion_record_the_actor_reason_and_persisted_values(string $route, string $modelClass, string $prefix, string $field, string $replacement): void
    {
        $this->freezeTime();
        $actor = $this->signIn();
        $fixture = $this->fixture($modelClass);
        $table = $fixture->getTable();
        $payload = $this->payload($fixture);

        $this->post(route($route.'.store'), [...$payload, 'change_reason' => 'Approved setup REF-100', 'user_id' => 999999])->assertRedirect()->assertSessionHasNoErrors();

        $created = AuditLog::where('action', $prefix.'_CREATED')->where('record_table', $table)->firstOrFail();
        $record = $modelClass::findOrFail($created->record_id);
        $this->assertSame($actor->getKey(), $created->user_id);
        $this->assertNull($created->old_values);
        $this->assertSame('Approved setup REF-100', $created->new_values['audit_context']['reason']);
        $this->assertSame('user', $created->new_values['audit_context']['reason_source']);
        $this->assertSame($record->getRawOriginal($field), $created->new_values[$field]);
        $before = $record->getAttributes();

        $this->put(route($route.'.update', $record), [...$this->payload($record), $field => $replacement, 'change_reason' => 'Revised authority REF-101'])->assertRedirect()->assertSessionHasNoErrors();

        $updated = AuditLog::where('action', $prefix.'_UPDATED')->where('record_id', (string) $record->getKey())->firstOrFail();
        $record->refresh();
        $this->assertSame($actor->getKey(), $updated->user_id);
        $this->assertSame($before[$field], $updated->old_values[$field]);
        $this->assertSame($record->getRawOriginal($field), $updated->new_values[$field]);
        $this->assertNotSame($before[$field], $record->getRawOriginal($field));
        $this->assertSame('Revised authority REF-101', $updated->new_values['audit_context']['reason']);

        $this->delete(route($route.'.destroy', $record), ['change_reason' => 'Retired REF-102', 'reason' => 'Retired REF-102'])->assertRedirect()->assertSessionHasNoErrors();

        $deleted = AuditLog::where('action', $prefix.($record instanceof Employee ? '_ARCHIVED' : '_DELETED'))->where('record_id', (string) $record->getKey())->firstOrFail();
        $this->assertSame($actor->getKey(), $deleted->user_id);
        $this->assertSame($record->getRawOriginal($field), $deleted->old_values[$field]);
        if ($record instanceof Employee) {
            $this->assertSoftDeleted($record);
        } else {
            $this->assertModelMissing($record);
            $this->assertSame('Retired REF-102', $deleted->new_values['audit_context']['reason']);
        }
    }

    /** @return array<string, array{string, class-string<Model>, string, string, string, string}> */
    public static function failedMutations(): array
    {
        $cases = [];
        foreach (self::resources() as $label => $resource) {
            foreach (['create', 'update', 'delete'] as $operation) {
                $cases[$label.' '.$operation] = [...$resource, $operation];
            }
        }

        return $cases;
    }

    #[DataProvider('failedMutations')]
    public function test_audit_failure_rolls_back_the_entire_source_operation(string $route, string $modelClass, string $prefix, string $field, string $replacement, string $operation): void
    {
        $this->signIn();
        $record = $this->fixture($modelClass);
        $payload = $this->payload($record);
        if ($operation !== 'create') {
            $record->save();
            $record->refresh();
        }
        $count = $modelClass::count();
        $before = $record->getAttributes();
        $historyCount = EmploymentHistory::count();
        AuditLog::creating(function (AuditLog $event) use ($record): void {
            if ($event->record_table === $record->getTable()) {
                throw new RuntimeException('Audit storage unavailable.');
            }
        });

        try {
            match ($operation) {
                'create' => $this->postJson(route($route.'.store'), $payload)->assertServerError(),
                'update' => $this->putJson(route($route.'.update', $record), [...$payload, $field => $replacement])->assertServerError(),
                'delete' => $this->deleteJson(route($route.'.destroy', $record))->assertServerError(),
            };

            $this->assertSame($count, $modelClass::count());
            $this->assertSame($historyCount, EmploymentHistory::count());
            if ($operation !== 'create') {
                $this->assertSame($before, $record->fresh()->getAttributes());
            }
            $this->assertDatabaseMissing('audit_logs', ['record_table' => $record->getTable()]);
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_appointment_creation_and_transfer_populate_history_and_preserve_it_in_the_delete_audit(): void
    {
        $actor = $this->signIn();
        $record = EmploymentRecord::factory()->make();
        $this->post(route('employment-records.store'), [...$this->payload($record), 'change_reason' => 'Appointment approved.'])->assertRedirect();
        $record = EmploymentRecord::findOrFail(AuditLog::where('action', 'EMPLOYMENT_RECORD_CREATED')->firstOrFail()->record_id);
        $original = $record->getAttributes();
        $targetDepartment = Department::factory()->create();
        $targetPosition = Position::factory()->create(['department_id' => $targetDepartment->getKey()]);

        $this->put(route('employment-records.update', $record), [
            ...$this->payload($record), 'department_id' => $targetDepartment->getKey(), 'position_id' => $targetPosition->getKey(),
            'change_reason' => 'Transfer order REF-500', 'change_effective_date' => '2026-10-01',
        ])->assertRedirect();

        $history = $record->history()->where('change_type', 'TRANSFER')->firstOrFail();
        $this->assertSame($original['department_id'], $history->from_department_id);
        $this->assertSame($targetDepartment->getKey(), $history->to_department_id);
        $this->assertSame($original['position_id'], $history->from_position_id);
        $this->assertSame($targetPosition->getKey(), $history->to_position_id);
        $this->assertSame('2026-10-01', $history->effective_date->toDateString());
        $this->assertSame($actor->getKey(), $history->recorded_by);
        $this->assertStringContainsString('Transfer order REF-500', $history->remarks);
        $this->assertSame(2, $record->history()->count());
        $this->get(route('employment-records.show', $record))->assertOk()->assertSee('Employment history')->assertSee('Transfer order REF-500')->assertSee($targetDepartment->department_name);
        CompensationProfile::factory()->create(['employment_id' => $record->getKey()]);
        $this->delete(route('employment-records.destroy', $record))->assertRedirect();
        $event = AuditLog::where('action', 'EMPLOYMENT_RECORD_DELETED')->firstOrFail();
        $this->assertCount(2, $event->old_values['history']);
        $this->assertSame('TRANSFER', $event->old_values['history'][1]['change_type']);
        $this->assertCount(1, $event->old_values['compensationProfiles']);
    }

    public function test_repeated_unchanged_employment_saves_do_not_fabricate_history_or_update_events(): void
    {
        $this->signIn();
        $record = EmploymentRecord::factory()->make();
        $payload = $this->payload($record);
        $this->post(route('employment-records.store'), $payload)->assertRedirect();
        $record = EmploymentRecord::findOrFail(AuditLog::where('action', 'EMPLOYMENT_RECORD_CREATED')->firstOrFail()->record_id);

        $this->put(route('employment-records.update', $record), $payload)->assertRedirect();

        $this->assertSame(1, $record->history()->count());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'EMPLOYMENT_RECORD_UPDATED']);
        $this->assertSame('operation', AuditLog::where('action', 'EMPLOYMENT_RECORD_CREATED')->firstOrFail()->new_values['audit_context']['reason_source']);
    }

    public function test_invalid_change_notes_and_effective_dates_are_rejected_before_mutation(): void
    {
        $this->signIn();
        $department = Department::factory()->create();
        $before = $department->fresh()->getAttributes();

        $this->putJson(route('departments.update', $department), [...$this->payload($department), 'department_name' => 'Changed', 'change_reason' => ['invalid'], 'change_effective_date' => 'tomorrow'])
            ->assertUnprocessable()->assertJsonValidationErrors(['change_reason', 'change_effective_date']);

        $this->assertSame($before, $department->fresh()->getAttributes());
        $this->assertDatabaseMissing('audit_logs', ['action' => 'DEPARTMENT_UPDATED']);
    }

    public function test_an_unprivileged_user_cannot_mutate_a_source_or_fabricate_an_audit_event(): void
    {
        $department = Department::factory()->create();
        $this->actingAs(User::factory()->create());

        $this->putJson(route('departments.update', $department), [...$this->payload($department), 'department_name' => 'Unauthorized'])->assertForbidden();

        $this->assertDatabaseMissing('audit_logs', ['action' => 'DEPARTMENT_UPDATED']);
        $this->assertDatabaseMissing('departments', ['department_name' => 'Unauthorized']);
    }

    public function test_failure_to_create_employment_history_rolls_back_the_appointment_change(): void
    {
        $this->signIn();
        $record = EmploymentRecord::factory()->create();
        $before = $record->fresh()->getAttributes();
        EmploymentHistory::creating(function (): void {
            throw new RuntimeException('History storage unavailable.');
        });

        try {
            $this->putJson(route('employment-records.update', $record), [...$this->payload($record), 'appointment_number' => 'FAILED-APPOINTMENT'])->assertServerError();

            $this->assertSame($before, $record->fresh()->getAttributes());
            $this->assertDatabaseCount('employment_history', 0);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'EMPLOYMENT_RECORD_UPDATED']);
        } finally {
            EmploymentHistory::flushEventListeners();
        }
    }

    public function test_stale_route_models_do_not_replace_the_actual_before_values(): void
    {
        $actor = $this->signIn();
        $department = Department::factory()->create(['description' => 'First version']);
        Department::whereKey($department->getKey())->update(['description' => 'Concurrent committed version']);

        app(MasterDataMutationService::class)->update($department, ['description' => 'New version'], $actor);

        $event = AuditLog::where('action', 'DEPARTMENT_UPDATED')->firstOrFail();
        $this->assertSame('Concurrent committed version', $event->old_values['description']);
        $this->assertSame('New version', $event->new_values['description']);
    }
}
