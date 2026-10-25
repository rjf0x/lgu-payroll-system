<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollEntry;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollPreparationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollPreparationTest extends TestCase
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

    private function actor(User $actor): void
    {
        $this->actingAs($actor)->withSession(['auth_version' => $actor->auth_version]);
    }

    private function batch(bool $demo = true, string $month = '2030-01'): PayrollBatch
    {
        $this->post('/payroll-batches', ['period' => $month, 'is_demonstration' => (int) $demo])->assertRedirect()->assertSessionHasNoErrors();

        return PayrollBatch::orderByDesc('id')->firstOrFail();
    }

    private function schedule(PersonnelClassification $category = PersonnelClassification::Permanent, bool $demo = true, string $date = '2020-01-01'): SalarySchedule
    {
        $existing = SalarySchedule::where('personnel_classification', $category)->where('is_demonstration', $demo)->whereDate('effective_from', $date)->first();
        if ($existing) {
            return $existing;
        }
        $schedule = SalarySchedule::create(['name' => 'FICTIONAL PAYROLL SOURCE', 'personnel_classification' => $category, 'effective_from' => $date, 'is_demonstration' => $demo, 'created_by' => $this->officer->id]);
        $schedule->rates()->create(['grade' => 5, 'step' => 1, 'monthly_salary' => '12345.67']);
        $schedule->authority_reference = 'FICTIONAL AUTHORITY - TEST ONLY';
        $schedule->published_at = now();
        $schedule->save();

        return $schedule;
    }

    private function employee(PersonnelClassification $category = PersonnelClassification::Permanent, bool $demo = true): Employee
    {
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $this->record($employee, $this->schedule($category, $demo));

        return $employee;
    }

    private function record(Employee $employee, SalarySchedule $schedule, string $date = '2020-01-01'): EmployeeSalaryRecord
    {
        return EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $schedule->rates()->sole()->id,
            'effective_from' => $date, 'change_type' => $date === '2020-01-01' ? 'initial' : 'schedule_update',
            'personnel_classification' => $employee->personnel_classification->value,
            'position' => 'Fictional Authorized Clerk', 'monthly_salary' => '12345.67',
            'is_demonstration' => $schedule->is_demonstration, 'step_basis_date' => '2020-01-01',
            'authority_reference' => 'FICTIONAL APPOINTMENT - TEST ONLY', 'reason' => 'Fictional payroll source fixture', 'created_by' => $this->officer->id,
        ]);
    }

    private function add(PayrollBatch $batch, array $ids, ?int $version = null)
    {
        return $this->post('/payroll-batches/'.$batch->id.'/employees', ['employee_ids' => $ids, 'record_version' => $version ?? $batch->fresh()->record_version, 'monthly_salary' => '999999.99', 'status' => 'approved']);
    }

    public function test_empty_monthly_draft_can_be_created_listed_and_reopened(): void
    {
        $batch = $this->batch();
        $this->assertSame('2030-01-01', $batch->period_start->format('Y-m-d'));
        $this->assertSame('draft', $batch->status);
        $this->assertTrue($batch->is_demonstration);
        $this->assertSame($this->officer->id, $batch->created_by);
        $this->get('/payroll-batches')->assertOk()->assertSee('January 2030')->assertSee('0 employees covered');
        $this->get('/payroll-batches/'.$batch->id)->assertOk()->assertSee('awaiting coverage')->assertSee('not calculated pay');
        $this->get('/payroll-batches/'.$batch->id.'/employees/create')->assertOk();
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_selected_employee_and_exact_salary_source_are_saved_and_reopened(PersonnelClassification $category): void
    {
        $employee = $this->employee($category);
        $unselected = $this->employee($category);
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertRedirect()->assertSessionHasNoErrors();
        $entry = PayrollEntry::sole();
        $this->assertSame($employee->id, $entry->employee_id);
        $this->assertSame('12345.67', $entry->monthly_salary);
        $this->assertSame($category, $entry->personnel_classification);
        $this->assertSame($employee->full_name, $entry->employee_name);
        $this->assertSame('Fictional Authorized Clerk', $entry->position);
        $this->assertSame(EmployeeSalaryRecord::where('employee_id', $employee->id)->sole()->id, $entry->employee_salary_record_id);
        $this->assertDatabaseMissing('payroll_entries', ['employee_id' => $unselected->id]);
        $this->assertSame('draft', $batch->fresh()->status);
        $this->get('/payroll-batches/'.$batch->id)->assertOk()->assertSee($employee->full_name)->assertSee('12345.67')->assertSee('Salary source checked')->assertDontSee('Needs review:');
        $this->get('/payroll-batches/'.$batch->id.'/employees/create')->assertOk()->assertDontSee($employee->employee_number)->assertSee($unselected->employee_number);
    }

    public function test_month_mode_duplicates_are_rejected_and_modes_remain_separate(): void
    {
        $batch = $this->batch();
        $this->post('/payroll-batches', ['period' => '2030-01', 'is_demonstration' => 1])->assertSessionHasErrors('period');
        $real = $this->batch(false);
        $demoEmployee = $this->employee();
        $realEmployee = $this->employee(demo: false);
        $this->add($real, [$demoEmployee->id])->assertSessionHasErrors('employee_ids');
        $this->add($batch, [$realEmployee->id])->assertSessionHasErrors('employee_ids');
        $this->add($real, [$realEmployee->id])->assertSessionHasNoErrors();
        $this->add($batch, [$demoEmployee->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_batches', 2);
        $this->assertDatabaseCount('payroll_entries', 2);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_role_access_to_batch_read_and_write_actions(Role $role): void
    {
        $employee = $this->employee();
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertSessionHasNoErrors();
        $entry = PayrollEntry::sole();
        $this->actor(User::factory()->create(['role' => $role]));
        foreach (['/payroll-batches', '/payroll-batches/'.$batch->id] as $url) {
            $this->get($url)->assertStatus($role === Role::Administrator ? 403 : 200);
        }
        if ($role !== Role::PayrollOfficer) {
            $this->get('/payroll-batches/create')->assertForbidden();
            $this->post('/payroll-batches', [])->assertForbidden();
            $this->get('/payroll-batches/'.$batch->id.'/employees/create')->assertForbidden();
            $this->post('/payroll-batches/'.$batch->id.'/employees', [])->assertForbidden();
            $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$entry->id, ['record_version' => 2])->assertForbidden();
        } else {
            $this->get('/payroll-batches/create')->assertOk();
            $this->get('/payroll-batches/'.$batch->id.'/employees/create')->assertOk();
            $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$entry->id, ['record_version' => 2])->assertSessionHasNoErrors();
        }
    }

    public static function invalidMonths(): array
    {
        return [['2030-13'], ['2030-02-01'], ['0999-12'], ['10000-01'], [['bad']], ["Bad\xFFMonth"]];
    }

    #[DataProvider('invalidMonths')]
    public function test_invalid_months_and_error_inputs_are_safe(mixed $period): void
    {
        $this->from('/payroll-batches/create')->post('/payroll-batches', ['period' => $period, 'is_demonstration' => 1])->assertSessionHasErrors('period');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/payroll-batches/create')->assertOk();
        $this->assertDatabaseCount('payroll_batches', 0);
    }

    public static function unavailableSources(): array
    {
        return array_map(fn ($case) => [$case], ['inactive', 'midmonth-hire', 'corrected-start', 'mismatched-amount', 'missing', 'wrong-mode', 'changed-category', 'superseded', 'midmonth-salary', 'midmonth-schedule', 'future-only']);
    }

    #[DataProvider('unavailableSources')]
    public function test_unusable_sources_cannot_be_added_even_by_forged_requests(string $case): void
    {
        $employee = $case === 'missing' || $case === 'future-only'
            ? Employee::factory()->create(['personnel_classification' => PersonnelClassification::Permanent, 'employment_start_date' => '2020-01-01'])
            : $this->employee(demo: $case !== 'wrong-mode');
        if ($case === 'inactive') {
            $employee->update(['is_active' => false]);
        } elseif ($case === 'midmonth-hire') {
            $employee->update(['employment_start_date' => '2030-01-02']);
        } elseif ($case === 'corrected-start') {
            $employee->update(['employment_start_date' => '2025-01-01']);
        } elseif ($case === 'mismatched-amount') {
            EmployeeSalaryRecord::where('employee_id', $employee->id)->update(['monthly_salary' => '99999.99']);
        } elseif ($case === 'changed-category') {
            $employee->update(['personnel_classification' => PersonnelClassification::Coterminous]);
        } elseif ($case === 'superseded' || $case === 'midmonth-schedule') {
            $this->schedule(date: $case === 'superseded' ? '2030-01-01' : '2030-01-15');
        } elseif ($case === 'midmonth-salary' || $case === 'future-only') {
            $this->record($employee, $this->schedule(), '2030-01-15');
        }
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertSessionHasErrors('employee_ids');
        $this->assertDatabaseCount('payroll_entries', 0);
        $this->assertSame(1, $batch->fresh()->record_version);
    }

    public function test_latest_salary_is_selected_without_falling_back_across_modes_and_future_changes_are_excluded(): void
    {
        $employee = $this->employee();
        $real = $this->schedule(demo: false);
        $this->record($employee, $real, '2030-01-01');
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertSessionHasErrors('employee_ids');
        $other = $this->employee();
        $this->record($other, $this->schedule(), '2030-02-01');
        $this->add($batch, [$other->id])->assertSessionHasNoErrors();
        $this->assertSame('2020-01-01', PayrollEntry::sole()->salaryRecord->effective_from->format('Y-m-d'));
    }

    public function test_saved_snapshots_are_preserved_and_source_changes_are_flagged(): void
    {
        $employee = $this->employee();
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertSessionHasNoErrors();
        $entry = PayrollEntry::sole();
        $original = $entry->getAttributes();
        $employee->update(['office' => 'Fictional Changed Office']);
        $this->get('/payroll-batches/'.$batch->id)->assertOk()->assertSee('Needs review:')->assertSee('Saved values remain unchanged');
        $this->assertSame($original, $entry->fresh()->getAttributes());
        $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$entry->id, ['record_version' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('employee_salary_records', 1);
        $this->add($batch, [$employee->id])->assertSessionHasNoErrors();
        $this->assertSame('Fictional Changed Office', PayrollEntry::sole()->office);
    }

    public function test_duplicate_submissions_stale_edits_and_cross_batch_removal_are_rejected(): void
    {
        $employee = $this->employee();
        $batch = $this->batch();
        $this->add($batch, [$employee->id], 1)->assertSessionHasNoErrors();
        $this->add($batch, [$employee->id], 1)->assertSessionHasErrors('record_version');
        $this->add($batch, [$employee->id], 2)->assertSessionHasErrors('employee_ids');
        $entry = PayrollEntry::sole();
        $other = $this->batch(month: '2030-02');
        $this->delete('/payroll-batches/'.$other->id.'/employees/'.$entry->id, ['record_version' => 1])->assertNotFound();
        $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$entry->id, ['record_version' => 1])->assertSessionHasErrors('record_version');
        $this->assertDatabaseCount('payroll_entries', 1);
        $this->assertSame(2, $batch->fresh()->record_version);
    }

    public function test_invalid_selection_and_partial_failure_never_save_partial_coverage(): void
    {
        $employee = $this->employee();
        $missing = Employee::factory()->create();
        $batch = $this->batch();
        foreach ([[], [$employee->id, $employee->id], [999999], [['bad']]] as $ids) {
            $this->add($batch, $ids)->assertSessionHasErrors();
        }
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['employee_ids' => [$employee->id]])->assertSessionHasErrors('record_version');
        $this->add($batch, [$employee->id, $missing->id])->assertSessionHasErrors('employee_ids');
        $this->assertDatabaseCount('payroll_entries', 0);
        $this->assertSame(1, $batch->fresh()->record_version);
    }

    public function test_employee_eligibility_is_rechecked_after_selection_page_was_opened(): void
    {
        $employee = $this->employee();
        $batch = $this->batch();
        $this->get('/payroll-batches/'.$batch->id.'/employees/create')->assertOk()->assertSee('12345.67');
        $employee->update(['is_active' => false]);
        $this->add($batch, [$employee->id])->assertSessionHasErrors('employee_ids');
        $this->assertDatabaseCount('payroll_entries', 0);
    }

    public function test_guest_csrf_and_revoked_actor_protections(): void
    {
        $batch = $this->batch();
        $employee = $this->employee();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/payroll-batches')->assertRedirect('/login');
        $this->post('/payroll-batches', [])->assertRedirect('/login');
        $this->actor($this->officer);
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->officer);
        $session = new Store('payroll-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->officer->auth_version);
        $request->setLaravelSession($session);
        User::whereKey($this->officer->id)->update(['role' => Role::Viewer->value]);
        try {
            app(PayrollPreparationService::class)->add($request, $batch, ['employee_ids' => [$employee->id], 'record_version' => 1]);
            $this->fail('A revoked officer saved coverage.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('payroll_entries', 0);
        }
        User::whereKey($this->officer->id)->update(['role' => Role::PayrollOfficer->value, 'auth_version' => 2]);
        try {
            app(PayrollPreparationService::class)->create($request, ['period' => '2030-02', 'is_demonstration' => 1]);
            $this->fail('An expired actor created a batch.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->actor($this->officer->fresh());
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/payroll-batches', ['period' => '2030-02', 'is_demonstration' => 1])->assertStatus(419);
        $this->add($batch, [$employee->id])->assertStatus(419);
    }

    public function test_leap_month_and_pagination_keep_previously_saved_coverage(): void
    {
        $batch = $this->batch(month: '2032-02');
        $employees = collect(range(1, 21))->map(fn () => $this->employee());
        $this->add($batch, $employees->take(20)->pluck('id')->all())->assertSessionHasNoErrors();
        $this->add($batch, [$employees->last()->id])->assertSessionHasNoErrors();
        $this->get('/payroll-batches/'.$batch->id)->assertOk()->assertViewHas('entries', fn ($entries) => $entries->total() === 21 && $entries->count() === 20);
        $this->get('/payroll-batches/'.$batch->id.'?page=2')->assertOk()->assertViewHas('entries', fn ($entries) => $entries->count() === 1);
        $this->assertDatabaseCount('payroll_entries', 21);
    }

    public function test_month_storage_boundaries_and_required_data_use_are_validated(): void
    {
        $this->batch(month: '1000-01');
        $this->batch(month: '9999-12');
        foreach ([null, 'unknown', ['bad']] as $mode) {
            $this->post('/payroll-batches', ['period' => '2030-01', 'is_demonstration' => $mode])->assertSessionHasErrors('is_demonstration');
        }
        $this->assertDatabaseCount('payroll_batches', 2);
    }

    public function test_new_schedule_flags_a_saved_draft_without_rewriting_its_amount(): void
    {
        $employee = $this->employee();
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertSessionHasNoErrors();
        $entry = PayrollEntry::sole();
        $before = $entry->getAttributes();
        $this->schedule(date: '2030-01-01');
        $this->get('/payroll-batches/'.$batch->id)->assertOk()->assertSee('Needs review:')->assertSee('superseded');
        $this->assertSame($before, $entry->fresh()->getAttributes());
    }

    public function test_latest_authorized_record_on_month_start_is_saved(): void
    {
        $employee = $this->employee();
        $latest = $this->record($employee, $this->schedule(date: '2030-01-01'), '2030-01-01');
        $batch = $this->batch();
        $this->add($batch, [$employee->id])->assertSessionHasNoErrors();
        $this->assertSame($latest->id, PayrollEntry::sole()->employee_salary_record_id);
        $this->get('/payroll-batches/'.$batch->id)->assertOk()->assertSee('Salary source checked');
    }

    public function test_leap_month_end_change_is_blocked_but_next_month_change_is_excluded(): void
    {
        $employee = $this->employee();
        $batch = $this->batch(month: '2032-02');
        $this->record($employee, $this->schedule(), '2032-03-01');
        $this->assertNull(app(PayrollPreparationService::class)->source($employee, $batch)['issue']);
        $this->record($employee, $this->schedule(), '2032-02-29');
        $this->add($batch, [$employee->id])->assertSessionHasErrors('employee_ids');
        $this->assertDatabaseCount('payroll_entries', 0);
    }
}
