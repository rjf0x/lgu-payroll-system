<?php

namespace Tests\Feature;

use App\Http\Requests\EmploymentRecordRequest;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentHistory;
use App\Models\EmploymentRecord;
use App\Models\PayrollDetail;
use App\Models\PayrollRun;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Services\MasterDataMutationService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmploymentRecordValidationTest extends TestCase
{
    use RefreshDatabase;

    private function signIn(string $role = Role::PAYROLL_OFFICER): User
    {
        $this->freezeTime();
        $this->seed(RolePermissionSeeder::class);
        $actor = User::factory()->create();
        $actor->roles()->attach(Role::where('role_name', $role)->value('role_id'), ['assigned_at' => now()]);
        $this->actingAs($actor);

        return $actor;
    }

    /** @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function input(array $attributes = []): array
    {
        return EmploymentRecord::factory()->make($attributes)->attributesToArray();
    }

    /** @return array<string, array{bool}> */
    public static function operations(): array
    {
        return ['create' => [false], 'update' => [true]];
    }

    #[DataProvider('operations')]
    public function test_mismatched_department_and_position_return_422_without_changing_source_or_history(bool $update): void
    {
        $this->signIn();
        $record = EmploymentRecord::factory()->create();
        $before = $record->fresh()->getAttributes();
        $input = [...$record->attributesToArray(), 'department_id' => Department::factory()->create()->getKey()];

        $response = $update
            ? $this->putJson(route('employment-records.update', $record), $input)
            : $this->postJson(route('employment-records.store'), $input);

        $response->assertUnprocessable()->assertJsonValidationErrors('position_id')
            ->assertSee('Select a position that belongs to the selected department.');
        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('employment_records', 1);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    /** @return array<string, array{string, ?string}> */
    public static function overlappingDates(): array
    {
        return [
            'same day as start' => ['2026-01-01', '2026-01-01'],
            'same day as end' => ['2026-01-31', '2026-02-10'],
            'contained' => ['2026-01-10', '2026-01-20'],
            'enclosing' => ['2025-12-01', '2026-02-28'],
            'open-ended' => ['2026-01-15', null],
        ];
    }

    #[DataProvider('overlappingDates')]
    public function test_overlapping_active_appointments_return_422_even_for_a_different_department_or_type(string $start, ?string $end): void
    {
        $this->signIn();
        $original = EmploymentRecord::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $input = $this->input(['employee_id' => $original->employee_id, 'start_date' => $start, 'end_date' => $end]);

        $this->postJson(route('employment-records.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('start_date')
            ->assertSee('Dates are inclusive')->assertSee('Concurrent dual appointments are unsupported');

        $this->assertDatabaseCount('employment_records', 1);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_an_open_ended_active_appointment_blocks_future_appointments_until_closed(): void
    {
        $this->signIn();
        $original = EmploymentRecord::factory()->create(['start_date' => '2024-01-01']);
        $input = $this->input(['employee_id' => $original->employee_id, 'start_date' => '2027-01-01']);

        $this->postJson(route('employment-records.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('start_date');
        $this->putJson(route('employment-records.update', $original), [...$original->attributesToArray(), 'end_date' => '2026-12-31'])->assertRedirect();
        $this->postJson(route('employment-records.store'), $input)->assertRedirect();

        $this->assertSame(2, EmploymentRecord::where('employee_id', $original->employee_id)->count());
        $this->assertSame(1, EmploymentHistory::where('change_type', 'APPOINTMENT_CREATED')->count());
    }

    /** @return array<string, array{string, ?string}> */
    public static function sequentialDates(): array
    {
        return ['before' => ['2025-12-01', '2025-12-31'], 'after' => ['2026-02-01', null]];
    }

    #[DataProvider('sequentialDates')]
    public function test_nonoverlapping_active_appointments_are_saved_with_history(string $start, ?string $end): void
    {
        $actor = $this->signIn();
        $original = EmploymentRecord::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);

        $this->postJson(route('employment-records.store'), $this->input(['employee_id' => $original->employee_id, 'start_date' => $start, 'end_date' => $end]))->assertRedirect();

        $created = EmploymentRecord::where('employment_id', '!=', $original->getKey())->sole();
        $this->assertSame($start, $created->start_date->toDateString());
        $this->assertSame($end, $created->end_date?->toDateString());
        $this->assertDatabaseHas('employment_history', ['employment_id' => $created->getKey(), 'change_type' => 'APPOINTMENT_CREATED', 'recorded_by' => $actor->getKey()]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'EMPLOYMENT_RECORD_CREATED', 'record_id' => (string) $created->getKey()]);
    }

    public function test_inactive_history_can_overlap_but_reactivation_cannot(): void
    {
        $this->signIn();
        $active = EmploymentRecord::factory()->create();
        $this->postJson(route('employment-records.store'), $this->input(['employee_id' => $active->employee_id, 'employment_status' => 'INACTIVE']))->assertRedirect();
        $history = EmploymentRecord::where('employment_status', 'INACTIVE')->sole();
        $before = $history->getAttributes();

        $this->putJson(route('employment-records.update', $history), [...$history->attributesToArray(), 'employment_status' => 'ACTIVE'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->assertSame($before, $history->fresh()->getAttributes());
        $this->assertDatabaseCount('employment_history', 1);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'EMPLOYMENT_RECORD_UPDATED']);
    }

    public function test_extending_an_active_appointment_into_another_is_rejected_without_rewriting_either(): void
    {
        $this->signIn();
        $earlier = EmploymentRecord::factory()->create(['start_date' => '2026-01-01', 'end_date' => '2026-01-31']);
        $later = EmploymentRecord::factory()->create(['employee_id' => $earlier->employee_id, 'start_date' => '2026-02-01']);
        $before = $earlier->fresh()->getAttributes();

        $this->putJson(route('employment-records.update', $earlier), [...$earlier->attributesToArray(), 'end_date' => '2026-02-01'])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        $this->assertSame($before, $earlier->fresh()->getAttributes());
        $this->assertSame('2026-02-01', $later->fresh()->start_date->toDateString());
        $this->assertDatabaseCount('employment_history', 0);
    }

    public function test_update_excludes_itself_and_a_consistent_transfer_records_history(): void
    {
        $actor = $this->signIn();
        $record = EmploymentRecord::factory()->create();
        $position = Position::factory()->create();

        $this->putJson(route('employment-records.update', $record), [
            ...$record->attributesToArray(), 'department_id' => $position->department_id, 'position_id' => $position->getKey(),
            'change_reason' => 'Authorized transfer REF-F10', 'change_effective_date' => '2026-02-01',
        ])->assertRedirect();

        $this->assertSame($position->department_id, $record->fresh()->department_id);
        $this->assertDatabaseHas('employment_history', ['employment_id' => $record->getKey(), 'change_type' => 'TRANSFER', 'recorded_by' => $actor->getKey()]);
        $this->assertSame('2026-02-01', $record->history()->sole()->effective_date->toDateString());
    }

    /** @return array<string, array{string}> */
    public static function statuses(): array
    {
        return ['active' => ['ACTIVE'], 'inactive' => ['INACTIVE']];
    }

    #[DataProvider('statuses')]
    public function test_archived_employees_cannot_receive_new_appointments(string $status): void
    {
        $this->signIn();
        $employee = Employee::factory()->create();
        $employee->delete();

        $this->postJson(route('employment-records.store'), $this->input(['employee_id' => $employee->getKey(), 'employment_status' => $status]))
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id')->assertSee('Archived employees cannot receive new or active appointments.');

        $this->assertDatabaseCount('employment_records', 0);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_archived_appointment_can_be_closed_and_corrected_as_inactive_history_but_cannot_reactivate(): void
    {
        $this->signIn();
        $record = EmploymentRecord::factory()->create();
        $record->employee->delete();
        $input = [...$record->attributesToArray(), 'employment_status' => 'INACTIVE', 'end_date' => '2026-01-31'];

        $this->putJson(route('employment-records.update', $record), $input)->assertRedirect();
        $this->putJson(route('employment-records.update', $record), [...$input, 'appointment_number' => 'CORRECTED-HISTORY'])->assertRedirect();
        $this->putJson(route('employment-records.update', $record), [...$input, 'employment_status' => 'ACTIVE'])
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id');

        $this->assertSame('INACTIVE', $record->fresh()->employment_status);
        $this->assertSame('CORRECTED-HISTORY', $record->fresh()->appointment_number);
        $this->assertSame(2, $record->history()->count());
        $this->get(route('employment-records.edit', $record))->assertOk()->assertSee('(archived; inactive history only)');
        $this->get(route('employment-records.create'))->assertOk()->assertDontSee($record->employee->employee_number);
        $this->get(route('employment-records.show', $record))->assertOk()->assertSee('CORRECTED-HISTORY');
    }

    public function test_existing_appointment_cannot_be_reassigned_or_detach_saved_payroll_history(): void
    {
        $this->signIn();
        $record = EmploymentRecord::factory()->create();
        $detail = PayrollDetail::factory()->create(['employment_id' => $record->getKey(), 'payroll_run_id' => PayrollRun::factory()->create(['status' => 'FINALIZED'])->getKey()]);
        $before = $detail->fresh()->getAttributes();

        $this->putJson(route('employment-records.update', $record), [...$record->attributesToArray(), 'employee_id' => Employee::factory()->create()->getKey()])
            ->assertUnprocessable()->assertJsonValidationErrors('employee_id')->assertSee('cannot be reassigned');

        $this->assertSame($before, $detail->fresh()->getAttributes());
        $this->assertSame($record->employee_id, $record->fresh()->employee_id);
        $this->assertDatabaseCount('employment_history', 0);
    }

    public function test_transaction_rechecks_new_conflicts_after_request_validation(): void
    {
        $actor = $this->signIn();
        $input = $this->input();
        $request = EmploymentRecordRequest::create('/employment-records', 'POST', $input);
        $request->setContainer($this->app)->setRedirector(app('redirect'));
        $request->validateResolved();
        EmploymentRecord::factory()->create(['employee_id' => $input['employee_id']]);

        try {
            app(MasterDataMutationService::class)->create(new EmploymentRecord, $request->validated(), $actor);
            $this->fail('Expected the newly committed appointment conflict to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('start_date', $exception->errors());
        }

        $this->assertDatabaseCount('employment_records', 1);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_transaction_rechecks_archival_and_position_membership_before_save(): void
    {
        $actor = $this->signIn();
        $record = EmploymentRecord::factory()->create();
        $before = $record->fresh()->getAttributes();
        $record->employee->delete();
        Position::whereKey($record->position_id)->update(['department_id' => Department::factory()->create()->getKey()]);

        try {
            app(MasterDataMutationService::class)->update($record, ['appointment_number' => 'INVALID-SAVE'], $actor);
            $this->fail('Expected changed employee and position sources to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('employee_id', $exception->errors());
            $this->assertArrayHasKey('position_id', $exception->errors());
        }

        $this->assertSame($before, $record->fresh()->getAttributes());
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_base_validation_errors_remain_actionable_without_attempting_cross_field_checks(): void
    {
        $this->signIn();

        $this->postJson(route('employment-records.store'), [])->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id', 'department_id', 'position_id', 'employment_type_id', 'start_date', 'employment_status']);
        $this->postJson(route('employment-records.store'), $this->input(['start_date' => '2026-01-31', 'end_date' => '2026-01-01']))
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');

        $this->assertDatabaseCount('employment_records', 0);
    }

    public function test_validation_errors_are_visible_and_keep_the_submitted_appointment_values(): void
    {
        $this->signIn();
        $input = $this->input(['department_id' => Department::factory()->create()->getKey(), 'appointment_number' => 'RETAIN-F10']);
        $url = route('employment-records.create');

        $this->followingRedirects()->from($url)->post(route('employment-records.store'), $input)
            ->assertOk()->assertSee('Select a position that belongs to the selected department.')
            ->assertSee('value="RETAIN-F10"', false);
    }

    public function test_viewer_cannot_bypass_employment_write_permissions(): void
    {
        $this->signIn(Role::VIEWER);
        $record = EmploymentRecord::factory()->create();

        $this->postJson(route('employment-records.store'), $this->input())->assertForbidden();
        $this->putJson(route('employment-records.update', $record), [...$record->attributesToArray(), 'appointment_number' => 'FORBIDDEN'])->assertForbidden();

        $this->assertDatabaseCount('employment_records', 1);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_inactive_history_does_not_block_reactivation_when_no_active_appointment_conflicts(): void
    {
        $this->signIn();
        $record = EmploymentRecord::factory()->create(['employment_status' => 'INACTIVE']);
        EmploymentRecord::factory()->create(['employee_id' => $record->employee_id, 'employment_status' => 'INACTIVE']);

        $this->putJson(route('employment-records.update', $record), [...$record->attributesToArray(), 'employment_status' => 'ACTIVE'])->assertRedirect();

        $this->assertSame('ACTIVE', $record->fresh()->employment_status);
        $this->assertSame(1, $record->history()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'EMPLOYMENT_RECORD_UPDATED']);
    }

    public function test_other_employees_appointments_do_not_block_the_same_dates(): void
    {
        $this->signIn();
        $other = EmploymentRecord::factory()->create();
        $input = $this->input();

        $this->postJson(route('employment-records.store'), $input)->assertRedirect();

        $this->assertNotSame($other->employee_id, $input['employee_id']);
        $this->assertDatabaseHas('employment_records', ['employee_id' => $input['employee_id'], 'employment_status' => 'ACTIVE']);
    }

    public function test_guest_cannot_create_an_appointment(): void
    {
        $this->post(route('employment-records.store'), $this->input())->assertRedirect(route('login'));

        $this->assertDatabaseCount('employment_records', 0);
        $this->assertDatabaseCount('employment_history', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }
}
