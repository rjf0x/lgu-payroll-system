<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollEntry;
use App\Models\PayrollInputRevision;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollInputService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollInputTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2030-01-15 12:00:00'));
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actor($this->officer);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function entry(PersonnelClassification $category = PersonnelClassification::Permanent, bool $demo = true, string $month = '2030-01'): array
    {
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::where('personnel_classification', $category)->where('is_demonstration', $demo)->first();
        if (! $schedule) {
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL INPUT SOURCE', 'personnel_classification' => $category, 'is_demonstration' => $demo, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
            $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '12345.67']);
            $schedule->authority_reference = 'FICTIONAL AUTHORITY';
            $schedule->published_at = now();
            $schedule->save();
        }
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $schedule->rates()->sole()->id,
            'effective_from' => '2020-01-01', 'change_type' => 'initial', 'personnel_classification' => $category->value,
            'position' => 'Fictional Clerk', 'monthly_salary' => '12345.67', 'is_demonstration' => $demo,
            'step_basis_date' => '2020-01-01', 'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Fictional fixture', 'created_by' => $this->officer->id,
        ]);
        $batch = PayrollBatch::whereDate('period_start', $month.'-01')->where('is_demonstration', $demo)->first();
        if (! $batch) {
            $this->post('/payroll-batches', ['period' => $month, 'is_demonstration' => (int) $demo])->assertSessionHasNoErrors();
            $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        }
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();

        return [$batch->fresh(), $batch->entries()->where('employee_id', $employee->id)->sole()];
    }

    private function path(PayrollBatch $batch, PayrollEntry $entry): string
    {
        return '/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs';
    }

    private function data(PayrollBatch $batch, array $changes = []): array
    {
        return array_replace_recursive([
            'record_version' => $batch->fresh()->record_version,
            'allowances' => [
                ['label' => 'PERA - fictional', 'amount' => '100.07', 'is_applicable' => '1'],
                ['label' => 'Hazard - fictional', 'amount' => '999.99', 'is_applicable' => '0'],
            ],
            'bonuses' => [['label' => 'Fictional bonus', 'amount' => '500.01']],
            'loans' => [['label' => 'Fictional GSIS loan', 'amount' => '200.02']],
            'adjustments' => [['label' => 'Fictional attendance correction', 'direction' => 'deduction', 'original_amount' => '300.03', 'adjusted_amount' => '111.11', 'reason' => 'Fictional authorized replacement']],
            'attendance' => ['absence_days' => '1.25', 'undertime_minutes' => 15, 'notes' => 'Fictional attendance record'],
            'authority_reference' => 'FICTIONAL INPUT AUTHORITY', 'change_reason' => 'Fictional initial input entry', 'authorization_confirmed' => 1,
            'monthly_salary' => '999999.99', 'allowance_total' => '999999.99', 'status' => 'approved',
        ], $changes);
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_per_employee_inputs_persist_and_inapplicable_allowances_are_excluded(PersonnelClassification $category): void
    {
        [$batch, $entry] = $this->entry($category);
        [$batch, $other] = $this->entry($category);
        $this->get($this->path($batch, $entry))->assertOk()->assertSee('No inputs saved yet')->assertSee('data-input-template', false);
        $this->put($this->path($batch, $entry), $this->data($batch))->assertRedirect()->assertSessionHasNoErrors();
        $first = PayrollInputRevision::sole();
        $this->assertSame('100.07', $first->allowanceTotal());
        $this->assertCount(1, $first->applicableAllowances());
        $this->assertSame('999.99', $first->payload['allowances'][1]['amount']);
        $this->assertFalse($first->payload['allowances'][1]['is_applicable']);
        $this->assertSame('200.02', $first->payload['loans'][0]['amount']);
        $this->assertSame('1.25', $first->payload['attendance']['absence_days']);
        $this->assertSame(15, $first->payload['attendance']['undertime_minutes']);
        $this->put($this->path($batch, $other), $this->data($batch, ['allowances' => [0 => ['amount' => '321.09']], 'loans' => [0 => ['amount' => '450.06']]]))->assertSessionHasNoErrors();
        $second = PayrollInputRevision::where('employee_id', $other->employee_id)->sole();
        $this->assertSame('321.09', $second->allowanceTotal());
        $this->assertSame('450.06', $second->payload['loans'][0]['amount']);
        $this->assertSame('200.02', $first->fresh()->payload['loans'][0]['amount']);
        $this->assertSame('12345.67', $entry->fresh()->monthly_salary);
        $this->assertSame('draft', $batch->fresh()->status);
        $this->get($this->path($batch, $entry))->assertOk()->assertSee('Applicable allowance subtotal: PHP 100.07')->assertSee('Excluded')->assertSee('300.03')->assertSee('111.11');
    }

    public function test_editing_preserves_original_revision_and_historical_values_are_read_only(): void
    {
        [$batch, $entry] = $this->entry(demo: false);
        $path = $this->path($batch, $entry);
        $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        $original = PayrollInputRevision::sole();
        $before = $original->getAttributes();
        $this->put($path, $this->data($batch, ['adjustments' => [0 => ['adjusted_amount' => '222.22', 'reason' => 'Fictional revised authority']], 'change_reason' => 'Fictional revision two']))->assertSessionHasNoErrors();
        $latest = PayrollInputRevision::orderByDesc('revision')->firstOrFail();
        $this->assertSame(2, $latest->revision);
        $this->assertSame('300.03', $latest->payload['adjustments'][0]['original_amount']);
        $this->assertSame('222.22', $latest->payload['adjustments'][0]['adjusted_amount']);
        $this->assertSame($before, $original->fresh()->getAttributes());
        $this->get($path.'?revision=1')->assertOk()->assertSee('111.11')->assertDontSee('222.22')->assertDontSee('Save draft input revision');
        $this->get($path)->assertOk()->assertSee('222.22')->assertSee('Revision 1')->assertSee('Revision 2');
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_input_read_and_write_permissions_follow_batch_roles(Role $role): void
    {
        [$batch, $entry] = $this->entry();
        $this->actor(User::factory()->create(['role' => $role]));
        $path = $this->path($batch, $entry);
        $response = $this->get($path)->assertStatus($role === Role::Administrator ? 403 : 200);
        if ($role === Role::PayrollOfficer) {
            $response->assertSee('Save draft input revision');
            $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        } else {
            if ($role !== Role::Administrator) {
                $response->assertDontSee('Save draft input revision');
            }
            $this->put($path, $this->data($batch))->assertForbidden();
            $this->assertDatabaseCount('payroll_input_revisions', 0);
        }
    }

    public static function badMoney(): array
    {
        return [['-1'], ['1.001'], ['1e3'], ['99999999999.99'], [['bad']], [null], ['1,000.00'], ['NaN']];
    }

    #[DataProvider('badMoney')]
    public function test_invalid_money_never_persists(mixed $amount): void
    {
        [$batch, $entry] = $this->entry();
        foreach (['allowances.0.amount', 'bonuses.0.amount', 'loans.0.amount', 'adjustments.0.original_amount', 'adjustments.0.adjusted_amount'] as $field) {
            $data = $this->data($batch);
            data_set($data, $field, $amount);
            $this->put($this->path($batch, $entry), $data)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('payroll_input_revisions', 0);
        $this->assertSame(2, $batch->fresh()->record_version);
    }

    public static function moneyBoundaries(): array
    {
        return [['0', '0.00'], ['9999999999.99', '9999999999.99'], ['0000000001.2', '1.20']];
    }

    #[DataProvider('moneyBoundaries')]
    public function test_money_is_normalized_without_float_rounding(string $amount, string $expected): void
    {
        [$batch, $entry] = $this->entry();
        $this->put($this->path($batch, $entry), $this->data($batch, ['allowances' => [0 => ['amount' => $amount]]]))->assertSessionHasNoErrors();
        $this->assertSame($expected, PayrollInputRevision::sole()->allowanceTotal());
    }

    public function test_attendance_month_bounds_and_integer_minutes_are_enforced(): void
    {
        [$batch, $entry] = $this->entry(month: '2032-02');
        $path = $this->path($batch, $entry);
        foreach (['-1', '29.01', '30', '0.001', '1e3'] as $days) {
            $this->put($path, $this->data($batch, ['attendance' => ['absence_days' => $days]]))->assertSessionHasErrors('attendance.absence_days');
        }
        foreach ([-1, 41761, '1.5', ['bad']] as $minutes) {
            $this->put($path, $this->data($batch, ['attendance' => ['undertime_minutes' => $minutes]]))->assertSessionHasErrors('attendance.undertime_minutes');
        }
        $this->put($path, $this->data($batch, ['attendance' => ['absence_days' => '29.00', 'undertime_minutes' => 41760]]))->assertSessionHasNoErrors();
        $this->assertSame('29.00', PayrollInputRevision::sole()->payload['attendance']['absence_days']);
    }

    public function test_multiple_applicable_allowances_use_exact_sum_and_manual_adjustments_keep_both_directions(): void
    {
        [$batch, $entry] = $this->entry();
        $data = $this->data($batch);
        $data['allowances'] = [
            ['label' => 'Fictional A', 'amount' => '0.10', 'is_applicable' => 1],
            ['label' => 'Fictional B', 'amount' => '0.20', 'is_applicable' => 1],
            ['label' => 'Fictional excluded', 'amount' => '999.99', 'is_applicable' => 0],
        ];
        $data['adjustments'][] = ['label' => 'Fictional extra earning correction', 'direction' => 'earning', 'original_amount' => '0', 'adjusted_amount' => '50.07', 'reason' => 'Fictional approved earning'];
        $this->put($this->path($batch, $entry), $data)->assertSessionHasNoErrors();
        $revision = PayrollInputRevision::sole();
        $this->assertSame('0.30', $revision->allowanceTotal());
        $this->assertCount(2, $revision->applicableAllowances());
        $this->assertSame('earning', $revision->payload['adjustments'][1]['direction']);
        $this->assertSame('0.00', $revision->payload['adjustments'][1]['original_amount']);
        $this->assertSame('50.07', $revision->payload['adjustments'][1]['adjusted_amount']);
    }

    public function test_adjustments_and_revisions_require_reasons_authority_and_confirmation(): void
    {
        [$batch, $entry] = $this->entry();
        foreach (['adjustments.0.reason' => '', 'adjustments.0.direction' => 'unknown', 'authority_reference' => '', 'change_reason' => '', 'authorization_confirmed' => 0] as $field => $value) {
            $data = $this->data($batch);
            data_set($data, $field, $value);
            $this->put($this->path($batch, $entry), $data)->assertSessionHasErrors($field);
        }
        $this->assertDatabaseCount('payroll_input_revisions', 0);
    }

    public function test_array_shapes_applicability_duplicate_labels_and_limits_are_validated(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        foreach (['allowances' => 'bad', 'allowances.0' => 'bad', 'allowances.0.is_applicable' => 'yes', 'loans.0.balance' => '999', 'bonuses.0.label' => ['bad'], 'adjustments.0.reason' => ['bad']] as $field => $value) {
            $data = $this->data($batch);
            data_set($data, $field, $value);
            $this->from($path)->put($path, $data)->assertSessionHasErrors();
            $this->withCookie(config('session.cookie'), session()->getId())->get($path)->assertOk();
        }
        $data = $this->data($batch);
        $data['allowances'][1]['label'] = 'pera - FICTIONAL';
        $this->put($path, $data)->assertSessionHasErrors('allowances.0.label');
        $data = $this->data($batch);
        $data['loans'] = array_map(fn ($index) => ['label' => 'Fictional loan '.$index, 'amount' => '1.00'], range(1, 31));
        $this->put($path, $data)->assertSessionHasErrors('loans');
        $this->assertDatabaseCount('payroll_input_revisions', 0);
    }

    public function test_rejected_utf8_and_rendered_labels_are_safe(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $this->from($path)->put($path, $this->data($batch, ['authority_reference' => "Invalid\xFFReference"]))->assertSessionHasErrors('authority_reference');
        $this->withCookie(config('session.cookie'), session()->getId())->get($path)->assertOk();
        $label = '<script>alert("fictional")</script>';
        $this->put($path, $this->data($batch, ['allowances' => [0 => ['label' => $label]]]))->assertSessionHasNoErrors();
        $this->get($path)->assertOk()->assertSee($label)->assertDontSee($label, false);
    }

    public function test_empty_groups_clear_current_inputs_without_erasing_history(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        $data = $this->data($batch);
        unset($data['allowances'], $data['bonuses'], $data['loans'], $data['adjustments']);
        $this->put($path, $data)->assertSessionHasNoErrors();
        $latest = PayrollInputRevision::orderByDesc('revision')->firstOrFail();
        $this->assertSame([], $latest->payload['allowances']);
        $this->assertSame('0.00', $latest->allowanceTotal());
        $this->assertDatabaseCount('payroll_input_revisions', 2);
        $this->assertSame('200.02', PayrollInputRevision::where('revision', 1)->sole()->payload['loans'][0]['amount']);
    }

    public function test_removing_all_lines_survives_validation_failure_in_another_field(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        $data = $this->data($batch, ['change_reason' => '']);
        unset($data['allowances'], $data['bonuses'], $data['loans'], $data['adjustments']);
        $this->from($path)->put($path, $data)->assertSessionHasErrors('change_reason')->assertSessionHas('_payroll_inputs_context');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($path)->assertOk();
        $response->assertDontSee('value="PERA - fictional"', false)->assertDontSee('value="Fictional GSIS loan"', false);
        $this->assertDatabaseCount('payroll_input_revisions', 1);
    }

    public function test_duplicate_save_stale_version_and_foreign_entry_are_rejected(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $data = $this->data($batch);
        $this->put($path, $data)->assertSessionHasNoErrors();
        $this->put($path, $data)->assertSessionHasErrors('record_version');
        [$otherBatch, $otherEntry] = $this->entry(demo: false);
        $this->get($this->path($otherBatch, $entry))->assertNotFound();
        $this->put($this->path($otherBatch, $entry), $this->data($otherBatch))->assertNotFound();
        $this->get($this->path($otherBatch, $otherEntry).'?revision=1')->assertNotFound();
        foreach (['0', '-1', 'abc', '99999999999', ['bad']] as $value) {
            $this->get($path.'?'.http_build_query(['revision' => $value]))->assertStatus(422);
        }
        $this->get($path.'?revision=99')->assertNotFound();
        $this->assertDatabaseCount('payroll_input_revisions', 1);
    }

    public static function retryBoundaries(): array
    {
        return [['another-employee'], ['unsaved-employee'], ['another-month']];
    }

    #[DataProvider('retryBoundaries')]
    public function test_rejected_input_is_not_restored_on_another_employee_or_batch_form(string $boundary): void
    {
        [$originBatch, $origin] = $this->entry();
        if ($boundary === 'another-month') {
            $this->post('/payroll-batches', ['period' => '2030-02', 'is_demonstration' => 1])->assertSessionHasNoErrors();
            $recipientBatch = PayrollBatch::orderByDesc('id')->firstOrFail();
            $this->post('/payroll-batches/'.$recipientBatch->id.'/employees', ['record_version' => 1, 'employee_ids' => [$origin->employee_id]])->assertSessionHasNoErrors();
            $recipient = $recipientBatch->entries()->sole();
        } else {
            [$recipientBatch, $recipient] = $this->entry();
        }
        $recipientPath = $this->path($recipientBatch, $recipient);
        if ($boundary !== 'unsaved-employee') {
            $this->put($recipientPath, $this->data($recipientBatch, [
                'allowances' => [0 => ['amount' => '777.77']], 'loans' => [0 => ['amount' => '888.88']],
                'bonuses' => [0 => ['amount' => '666.66']],
                'adjustments' => [0 => ['original_amount' => '400.04', 'adjusted_amount' => '444.44']],
                'attendance' => ['absence_days' => '2.50', 'undertime_minutes' => 30, 'notes' => 'Recipient attendance'],
                'authority_reference' => 'Recipient saved authority',
            ]))->assertSessionHasNoErrors();
        }
        $before = PayrollInputRevision::get()->map->getAttributes()->all();
        $originPath = $this->path($originBatch, $origin);
        $rejected = $this->data($originBatch, ['change_reason' => '', 'authority_reference' => 'Rejected origin authority']);
        // A posted context must not be able to choose which form receives old input.
        $rejected['_payroll_inputs_context'] = [
            'batch_id' => (string) $recipientBatch->id, 'entry_id' => (string) $recipient->id,
            'employee_id' => (string) $recipient->employee_id,
        ];
        $this->from($originPath)->put($originPath, $rejected)->assertSessionHasErrors('change_reason');
        $response = $this->withCookie(config('session.cookie'), session()->getId())->get($recipientPath)->assertOk();
        $response->assertDontSee('Rejected origin authority')->assertDontSee('The change reason field is required.');
        if ($boundary === 'unsaved-employee') {
            $response->assertDontSee('name="allowances[0][amount]"', false)
                ->assertDontSee('name="loans[0][amount]"', false)
                ->assertSee('name="attendance[absence_days]" value="0.00"', false)
                ->assertSee('name="attendance[undertime_minutes]" value="0"', false);
        } else {
            foreach (['allowances[0][amount]' => '777.77', 'loans[0][amount]' => '888.88', 'bonuses[0][amount]' => '666.66', 'adjustments[0][original_amount]' => '400.04', 'adjustments[0][adjusted_amount]' => '444.44', 'attendance[absence_days]' => '2.50', 'attendance[undertime_minutes]' => '30'] as $name => $value) {
                $response->assertSee('name="'.$name.'" value="'.$value.'"', false);
            }
            $response->assertSee('Recipient attendance');
        }
        $this->assertSame($before, PayrollInputRevision::get()->map->getAttributes()->all());
    }

    public function test_rejected_inputs_and_errors_stay_available_on_the_matching_form(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $data = $this->data($batch, ['allowances' => [0 => ['amount' => '555.55']], 'authority_reference' => 'Matching form authority', 'change_reason' => '']);
        $this->from($path)->put($path, $data)->assertSessionHasErrors('change_reason');
        $this->withCookie(config('session.cookie'), session()->getId())->get($path)->assertOk()
            ->assertSee('name="allowances[0][amount]" value="555.55"', false)
            ->assertSee('Matching form authority')->assertSee('The change reason field is required.');
    }

    public function test_encoding_rejection_is_scoped_before_form_request_validation(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $data = $this->data($batch, ['allowances' => [0 => ['amount' => '555.55']], 'authority_reference' => "Invalid\xFFReference"]);
        $this->from($path)->put($path, $data)->assertSessionHasErrors('authority_reference');
        $this->withCookie(config('session.cookie'), session()->getId())->get($path)->assertOk()
            ->assertSee('name="allowances[0][amount]" value="555.55"', false)
            ->assertSee('This field must contain valid UTF-8 text.');
        $this->assertDatabaseCount('payroll_input_revisions', 0);
    }

    public function test_json_validation_does_not_create_a_form_restore_context(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        $this->putJson($path, $this->data($batch, ['change_reason' => '']))->assertUnprocessable()->assertJsonValidationErrors('change_reason');
        $this->assertFalse(session()->has('_payroll_inputs_context'));
        $this->get($path)->assertOk()->assertSee('name="allowances[0][amount]" value="100.07"', false);
        $this->assertDatabaseCount('payroll_input_revisions', 1);
    }

    public function test_service_validation_errors_restore_only_the_matching_form(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $stale = $this->data($batch, ['allowances' => [0 => ['amount' => '555.55']]]);
        $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        $this->from($path)->put($path, $stale)->assertSessionHasErrors('record_version');
        $this->withCookie(config('session.cookie'), session()->getId())->get($path)->assertOk()
            ->assertSee('This draft changed in another session. Reload before saving inputs.')
            ->assertSee('name="allowances[0][amount]" value="555.55"', false);
        $this->assertDatabaseCount('payroll_input_revisions', 1);
    }

    public function test_stale_salary_profile_blocks_save_and_readding_preserves_revision_history(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $this->put($path, $this->data($batch))->assertSessionHasNoErrors();
        $original = PayrollInputRevision::sole()->getAttributes();
        $entry->employee->update(['office' => 'Fictional Revised Office']);
        $this->put($path, $this->data($batch))->assertSessionHasErrors('entry');
        $this->get($path)->assertOk()->assertSee('Salary source needs review:')->assertDontSee('Save draft input revision');
        $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$entry->id, ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_input_revisions', 1);
        $this->get($path)->assertNotFound();
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$entry->employee_id]])->assertSessionHasNoErrors();
        $newEntry = $batch->entries()->sole();
        $newPath = $this->path($batch, $newEntry);
        $this->get($newPath)->assertOk()->assertSee('different employee/salary details')->assertSee('200.02');
        $this->put($newPath, $this->data($batch))->assertSessionHasNoErrors();
        $this->assertSame(2, PayrollInputRevision::orderByDesc('revision')->firstOrFail()->revision);
        $this->assertSame($original, PayrollInputRevision::where('revision', 1)->sole()->getAttributes());
    }

    public static function revocations(): array
    {
        return [['role'], ['inactive'], ['session']];
    }

    #[DataProvider('revocations')]
    public function test_transaction_rechecks_current_actor(string $kind): void
    {
        [$batch, $entry] = $this->entry();
        $data = $this->data($batch);
        $request = Request::create('/', 'PUT');
        $request->setUserResolver(fn () => $this->officer);
        $session = new Store('inputs-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->officer->auth_version);
        $request->setLaravelSession($session);
        User::whereKey($this->officer->id)->update(match ($kind) {
            'role' => ['role' => Role::Viewer->value], 'inactive' => ['is_active' => false], default => ['auth_version' => 2],
        });
        try {
            app(PayrollInputService::class)->save($request, $batch, $entry, $data);
            $this->fail('Revoked actor saved inputs.');
        } catch (AuthorizationException|HttpException $exception) {
            if ($exception instanceof HttpException) {
                $this->assertSame(403, $exception->getStatusCode());
            }
            $this->assertDatabaseCount('payroll_input_revisions', 0);
        }
    }

    public function test_guests_and_csrf_are_protected(): void
    {
        [$batch, $entry] = $this->entry();
        $path = $this->path($batch, $entry);
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($path)->assertRedirect('/login');
        $this->put($path, [])->assertRedirect('/login');
        $this->actor($this->officer);
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->put($path, $this->data($batch))->assertStatus(419);
    }

    public static function mutations(): array
    {
        return [['update'], ['delete']];
    }

    #[DataProvider('mutations')]
    public function test_existing_revisions_cannot_be_changed(string $operation): void
    {
        [$batch, $entry] = $this->entry();
        $this->put($this->path($batch, $entry), $this->data($batch))->assertSessionHasNoErrors();
        $revision = PayrollInputRevision::sole();
        $this->expectException(LogicException::class);
        if ($operation === 'delete') {
            $revision->delete();
        } else {
            $revision->change_reason = 'Tamper';
            $revision->save();
        }
    }
}
