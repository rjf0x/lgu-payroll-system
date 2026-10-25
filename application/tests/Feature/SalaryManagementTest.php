<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\SalaryService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\EncryptedStore;
use Illuminate\Session\Store;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class SalaryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->travelTo(Carbon::parse('2030-01-15 12:00:00'));
        $this->admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actor($this->admin);
    }

    private function actor(User $actor): void
    {
        $this->actingAs($actor)->withSession(['auth_version' => $actor->auth_version]);
    }

    private function scheduleData(array $changes = []): array
    {
        return array_replace(['name' => 'Fictional Salary Schedule', 'personnel_classification' => 'permanent', 'effective_from' => '2020-01-01', 'is_demonstration' => '1'], $changes);
    }

    private function schedule(array $changes = [], bool $publish = true): SalarySchedule
    {
        $this->actor($this->admin);
        $this->post('/salary-schedules', $this->scheduleData($changes))->assertRedirect()->assertSessionHasNoErrors();
        $schedule = SalarySchedule::orderByDesc('id')->firstOrFail();
        foreach ([[5, 1, '10000.07'], [5, 2, '10100.08'], [6, 1, '11000.09']] as [$grade, $step, $amount]) {
            $this->post('/salary-schedules/'.$schedule->id.'/rates', ['grade' => $grade, 'step' => $step, 'monthly_salary' => $amount])->assertRedirect()->assertSessionHasNoErrors();
        }
        if ($publish) {
            $this->post('/salary-schedules/'.$schedule->id.'/publish', ['authority_reference' => 'FICTIONAL AUTHORITY - TEST ONLY', 'authorization_confirmed' => 1])->assertSessionHasNoErrors();
        }

        return $schedule->refresh();
    }

    private function employee(PersonnelClassification $classification = PersonnelClassification::Permanent): Employee
    {
        return Employee::factory()->create(['personnel_classification' => $classification, 'employment_start_date' => '2020-01-01']);
    }

    private function officer(): User
    {
        $officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actor($officer);

        return $officer;
    }

    private function recordData(SalarySchedule $schedule, array $changes = []): array
    {
        return array_replace([
            'salary_rate_id' => $schedule->rates()->where('grade', 5)->where('step', 1)->value('id'),
            'effective_from' => '2024-02-29', 'change_type' => 'initial', 'position' => 'Fictional Clerk',
            'step_basis_date' => '2024-02-29', 'authority_reference' => 'FICTIONAL APPOINTMENT - TEST ONLY',
            'reason' => 'Fictional salary assignment for automated testing', 'authorization_confirmed' => 1,
        ], $changes);
    }

    public static function classifications(): array
    {
        return array_map(fn ($value) => [$value], PersonnelClassification::cases());
    }

    #[DataProvider('classifications')]
    public function test_authorized_assignment_steps_promotions_and_history_for_each_category(PersonnelClassification $classification): void
    {
        $schedule = $this->schedule(['personnel_classification' => $classification->value]);
        $employee = $this->employee($classification);
        $this->officer();
        $path = '/employees/'.$employee->id.'/salary-records';
        $this->get($path)->assertOk()->assertSee('No authorized salary recorded');
        $this->get($path.'/create')->assertOk()->assertSee('Grade 5 / Step 1');
        $this->post($path, $this->recordData($schedule, ['monthly_salary' => '999999.99']))->assertRedirect()->assertSessionHasNoErrors();
        $first = EmployeeSalaryRecord::sole();
        $original = $first->getAttributes();
        $this->assertSame('10000.07', $first->monthly_salary);
        $this->assertTrue($first->is_demonstration);
        $this->assertSame($classification, $first->personnel_classification);
        $step = $schedule->rates()->where('grade', 5)->where('step', 2)->firstOrFail();
        $this->post($path, $this->recordData($schedule, ['salary_rate_id' => $step->id, 'effective_from' => '2030-01-15', 'change_type' => 'step_increment', 'step_basis_date' => '2030-01-15']))->assertSessionHasNoErrors();
        $promotion = $schedule->rates()->where('grade', 6)->firstOrFail();
        $this->post($path, $this->recordData($schedule, ['salary_rate_id' => $promotion->id, 'effective_from' => '2030-02-01', 'change_type' => 'promotion', 'position' => 'Fictional Senior Clerk', 'step_basis_date' => '2030-02-01']))->assertSessionHasNoErrors();
        $service = app(SalaryService::class);
        $this->assertNull($service->salaryAsOf($employee, '2024-02-28'));
        $this->assertSame('10000.07', $service->salaryAsOf($employee, '2029-12-31')->monthly_salary);
        $this->assertSame('10100.08', $service->salaryAsOf($employee, '2030-01-15')->monthly_salary);
        $this->assertSame('11000.09', $service->salaryAsOf($employee, '2030-02-01')->monthly_salary);
        $this->assertSame($original, $first->fresh()->getAttributes());
        $this->assertNotSame('Fictional Senior Clerk', $employee->fresh()->position);
        $this->get($path.'?as_of=2030-02-01')->assertOk()->assertSee('11000.09')->assertSee('Demonstration only')->assertSee('step increment')->assertSee('promotion');
        $this->assertDatabaseCount('employee_salary_records', 3);
    }

    public function test_effective_schedule_resolution_and_explicit_schedule_update_preserve_history(): void
    {
        $old = $this->schedule();
        $new = $this->schedule(['effective_from' => '2031-01-01']);
        $real = $this->schedule(['effective_from' => '2020-01-01', 'is_demonstration' => 0]);
        $employee = $this->employee();
        $this->officer();
        $path = '/employees/'.$employee->id.'/salary-records';
        $this->post($path, $this->recordData($old))->assertSessionHasNoErrors();
        $first = EmployeeSalaryRecord::sole();
        $before = $first->getAttributes();
        $service = app(SalaryService::class);
        $this->assertNull($service->applicableSchedule(PersonnelClassification::Permanent, '2019-12-31', true));
        $this->assertSame($old->id, $service->applicableSchedule(PersonnelClassification::Permanent, '2030-12-31', true)->id);
        $this->assertSame($new->id, $service->applicableSchedule(PersonnelClassification::Permanent, '2031-01-01', true)->id);
        $this->assertSame($real->id, $service->applicableSchedule(PersonnelClassification::Permanent, '2031-01-01', false)->id);
        $this->get($path.'?as_of=2031-01-01')->assertOk()->assertSee('A different schedule now applies');
        $this->post($path, $this->recordData($old, ['effective_from' => '2031-01-01', 'change_type' => 'schedule_update']))->assertSessionHasErrors('salary_rate_id');
        $this->post($path, $this->recordData($new, ['effective_from' => '2031-01-01', 'change_type' => 'schedule_update', 'step_basis_date' => '2031-01-01']))->assertSessionHasNoErrors();
        $latest = $service->salaryAsOf($employee, '2031-01-01');
        $this->assertSame($new->id, $latest->rate->salary_schedule_id);
        $this->assertSame('2024-02-29', $latest->step_basis_date->format('Y-m-d'));
        $this->assertSame($before, $first->fresh()->getAttributes());
    }

    public function test_category_changes_require_explicit_new_source_without_rewriting_old_salary(): void
    {
        $old = $this->schedule();
        $new = $this->schedule(['personnel_classification' => 'coterminous']);
        $employee = $this->employee();
        $this->officer();
        $path = '/employees/'.$employee->id.'/salary-records';
        $this->post($path, $this->recordData($old))->assertSessionHasNoErrors();
        $first = EmployeeSalaryRecord::sole();
        $before = $first->getAttributes();
        $employee->personnel_classification = PersonnelClassification::Coterminous;
        $employee->save();
        $this->get($path)->assertOk()->assertSee('The employee classification has changed');
        $this->post($path, $this->recordData($old, ['change_type' => 'schedule_update', 'effective_from' => '2030-01-15']))->assertSessionHasErrors('salary_rate_id');
        $this->post($path, $this->recordData($new, ['change_type' => 'schedule_update', 'effective_from' => '2030-01-15']))->assertSessionHasNoErrors();
        $this->assertSame(PersonnelClassification::Coterminous, app(SalaryService::class)->salaryAsOf($employee, '2030-01-15')->personnel_classification);
        $this->assertSame($before, $first->fresh()->getAttributes());
    }

    public function test_salary_assignment_rechecks_a_revoked_payroll_officer_inside_the_transaction(): void
    {
        $schedule = $this->schedule();
        $employee = $this->employee();
        $officer = $this->officer();
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $officer);
        $session = new Store('record-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', 1);
        $request->setLaravelSession($session);
        User::whereKey($officer->id)->update(['role' => Role::Viewer->value]);
        try {
            app(SalaryService::class)->recordSalary($request, $employee, $this->recordData($schedule));
            $this->fail('A revoked payroll officer recorded a salary.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('employee_salary_records', 0);
        }
    }

    public static function roles(): array
    {
        return array_map(fn ($value) => [$value], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_role_matrix_for_schedules_rates_publication_and_salary_actions(Role $role): void
    {
        $published = $this->schedule();
        $draft = $this->schedule(['effective_from' => '2032-01-01'], false);
        $employee = $this->employee();
        $actor = $role === Role::Administrator ? $this->admin : User::factory()->create(['role' => $role]);
        $this->actor($actor);
        $this->get('/salary-schedules')->assertOk();
        $this->get('/salary-schedules/'.$published->id)->assertOk();
        if ($role !== Role::Administrator) {
            $this->get('/salary-schedules/'.$draft->id)->assertForbidden();
            $this->get('/salary-schedules/create')->assertForbidden();
            $this->post('/salary-schedules', $this->scheduleData(['effective_from' => '2033-01-01']))->assertForbidden();
            $this->put('/salary-schedules/'.$draft->id, $this->scheduleData())->assertForbidden();
            $this->post('/salary-schedules/'.$draft->id.'/rates', [])->assertForbidden();
            $this->put('/salary-schedules/'.$draft->id.'/rates/'.$draft->rates()->first()->id, [])->assertForbidden();
            $this->post('/salary-schedules/'.$draft->id.'/publish', [])->assertForbidden();
        }
        $path = '/employees/'.$employee->id.'/salary-records';
        if ($role === Role::Administrator) {
            $this->get($path)->assertForbidden();
        } else {
            $this->get($path)->assertOk();
        }
        if ($role !== Role::PayrollOfficer) {
            $this->get($path.'/create')->assertForbidden();
            $this->post($path, $this->recordData($published))->assertForbidden();
        }
        $this->assertDatabaseCount('employee_salary_records', 0);
    }

    public function test_draft_maintenance_publish_requirements_duplicates_and_published_locks(): void
    {
        $this->get('/salary-schedules/create')->assertOk();
        $this->post('/salary-schedules', $this->scheduleData())->assertSessionHasNoErrors();
        $draft = SalarySchedule::sole();
        $this->get('/salary-schedules/'.$draft->id.'/edit')->assertOk();
        $this->post('/salary-schedules/'.$draft->id.'/publish', ['authority_reference' => 'Fictional', 'authorization_confirmed' => 1])->assertSessionHasErrors('schedule');
        $this->put('/salary-schedules/'.$draft->id, $this->scheduleData(['name' => 'Corrected Fictional Schedule']))->assertSessionHasNoErrors();
        $this->post('/salary-schedules', $this->scheduleData())->assertSessionHasErrors('effective_from');
        $rateData = ['grade' => 1, 'step' => 1, 'monthly_salary' => '9999999999.99'];
        $this->post('/salary-schedules/'.$draft->id.'/rates', $rateData)->assertSessionHasNoErrors();
        $this->post('/salary-schedules/'.$draft->id.'/rates', $rateData)->assertSessionHasErrors('grade');
        $rate = $draft->rates()->sole();
        $this->assertSame('9999999999.99', $rate->monthly_salary);
        $this->put('/salary-schedules/'.$draft->id.'/rates/'.$rate->id, array_replace($rateData, ['monthly_salary' => '12345.67']))->assertSessionHasNoErrors();
        $this->post('/salary-schedules/'.$draft->id.'/publish', ['authority_reference' => 'Fictional'])->assertSessionHasErrors('authorization_confirmed');
        $this->post('/salary-schedules/'.$draft->id.'/publish', ['authority_reference' => 'Fictional', 'authorization_confirmed' => 1])->assertSessionHasNoErrors();
        $this->put('/salary-schedules/'.$draft->id, $this->scheduleData())->assertSessionHasErrors('schedule');
        $this->put('/salary-schedules/'.$draft->id.'/rates/'.$rate->id, $rateData)->assertSessionHasErrors('schedule');
        $this->post('/salary-schedules/'.$draft->id.'/rates', ['grade' => 2, 'step' => 1, 'monthly_salary' => '10'])->assertSessionHasErrors('schedule');
        $this->post('/salary-schedules/'.$draft->id.'/publish', ['authority_reference' => 'Changed', 'authorization_confirmed' => 1])->assertSessionHasErrors('schedule');
        $this->assertSame('12345.67', $rate->fresh()->monthly_salary);
    }

    public static function badAmounts(): array
    {
        return [['0'], ['-1'], ['1.001'], ['1e3'], ['99999999999.99'], [['bad']]];
    }

    #[DataProvider('badAmounts')]
    public function test_invalid_rates_do_not_persist(mixed $amount): void
    {
        $draft = $this->schedule([], false);
        $this->post('/salary-schedules/'.$draft->id.'/rates', ['grade' => 2, 'step' => 1, 'monthly_salary' => $amount])->assertSessionHasErrors('monthly_salary');
        $this->assertSame(3, $draft->rates()->count());
    }

    public function test_invalid_headers_authority_and_text_shapes_are_rejected_and_render_safely(): void
    {
        $this->from('/salary-schedules/create')->post('/salary-schedules', $this->scheduleData(['name' => ['bad'], 'personnel_classification' => 'unknown', 'effective_from' => '2030-02-30', 'is_demonstration' => 'yes']))->assertSessionHasErrors(['name', 'personnel_classification', 'effective_from', 'is_demonstration']);
        $this->withCookie(config('session.cookie'), session()->getId())->get('/salary-schedules/create')->assertOk();
        $this->post('/salary-schedules', $this->scheduleData(['name' => "Invalid\xFFName"]))->assertSessionHasErrors('name');
        $draft = $this->schedule([], false);
        $this->post('/salary-schedules/'.$draft->id.'/publish', ['authority_reference' => ['bad'], 'authorization_confirmed' => 1])->assertSessionHasErrors('authority_reference');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/salary-schedules/'.$draft->id)->assertOk();
    }

    public static function invalidChanges(): array
    {
        return [['initial', 5, 1, '2030-01-15'], ['step_increment', 5, 1, '2030-01-15'], ['step_increment', 6, 1, '2030-01-15'], ['promotion', 5, 2, '2030-01-15'], ['schedule_update', 5, 1, '2030-01-15'], ['step_increment', 5, 2, '2025-01-01'], ['step_increment', 5, 2, '2024-02-29']];
    }

    #[DataProvider('invalidChanges')]
    public function test_change_types_and_history_dates_cannot_be_misrepresented(string $type, int $grade, int $step, string $date): void
    {
        $schedule = $this->schedule();
        $employee = $this->employee();
        $this->officer();
        $path = '/employees/'.$employee->id.'/salary-records';
        $this->post($path, $this->recordData($schedule))->assertSessionHasNoErrors();
        $this->post($path, $this->recordData($schedule, ['salary_rate_id' => $schedule->rates()->where('grade', $grade)->where('step', $step)->value('id'), 'change_type' => $type, 'effective_from' => $date]))->assertSessionHasErrors();
        $this->assertDatabaseCount('employee_salary_records', 1);
    }

    public function test_unpublished_future_and_wrong_category_rates_cannot_be_assigned(): void
    {
        $draft = $this->schedule([], false);
        $future = $this->schedule(['effective_from' => '2035-01-01']);
        $other = $this->schedule(['personnel_classification' => 'part_time']);
        $employee = $this->employee();
        $this->officer();
        foreach ([$draft, $future, $other] as $schedule) {
            $this->post('/employees/'.$employee->id.'/salary-records', $this->recordData($schedule))->assertSessionHasErrors('salary_rate_id');
        }
        $this->get('/employees/'.$employee->id.'/salary-records/create?effective_from=2019-01-01')->assertOk()->assertSee('No published schedule');
        $this->assertDatabaseCount('employee_salary_records', 0);
    }

    public function test_three_year_reminder_is_provisional_and_does_not_change_salary(): void
    {
        $schedule = $this->schedule();
        $employee = $this->employee();
        $this->officer();
        $path = '/employees/'.$employee->id.'/salary-records';
        $this->post($path, $this->recordData($schedule))->assertSessionHasNoErrors();
        $record = EmployeeSalaryRecord::sole();
        $original = $record->getAttributes();
        $service = app(SalaryService::class);
        $this->assertSame(['date' => '2027-02-28', 'due' => false], $service->stepReview($record, '2027-02-27'));
        $this->assertSame(['date' => '2027-02-28', 'due' => true], $service->stepReview($record, '2027-02-28'));
        $this->get($path.'?as_of=2030-01-15')->assertOk()->assertSee('Provisional reminder')->assertSee('Category-specific eligibility is unverified');
        $this->assertSame($original, $record->fresh()->getAttributes());
        $this->get($path.'?as_of[]=bad')->assertStatus(422);
        $this->get($path.'/create?is_demonstration[]=bad')->assertStatus(422);
    }

    public function test_invalid_salary_input_authorization_basis_and_error_sessions_are_safe(): void
    {
        $this->post('/salary-schedules', $this->scheduleData(['name' => "Invalid\xFFName"]))->assertSessionHasErrors('name');
        $this->assertPersistentJsonSession();
        $schedule = $this->schedule();
        $employee = $this->employee();
        $this->officer();
        $path = '/employees/'.$employee->id.'/salary-records';
        $this->post($path, $this->recordData($schedule, ['authority_reference' => '', 'authorization_confirmed' => 0, 'reason' => ['bad'], 'position' => ['bad']]))->assertSessionHasErrors(['authority_reference', 'authorization_confirmed', 'reason', 'position']);
        $this->withCookie(config('session.cookie'), session()->getId())->get($path.'/create')->assertOk();
        $this->post($path, $this->recordData($schedule, ['step_basis_date' => '2025-01-01']))->assertSessionHasErrors('step_basis_date');
        $this->post($path, $this->recordData($schedule, ['step_basis_date' => '2019-12-31']))->assertSessionHasErrors('step_basis_date');
        $this->post($path, $this->recordData($schedule, ['authority_reference' => "Bad\xFFReference"]))->assertSessionHasErrors('authority_reference');
        $this->assertPersistentJsonSession();
        $this->assertDatabaseCount('employee_salary_records', 0);
    }

    private function assertPersistentJsonSession(): void
    {
        $store = app('session.store');
        $stored = $store->getHandler()->read($store->getId());
        if ($store instanceof EncryptedStore) {
            $stored = $store->getEncrypter()->decrypt($stored);
        }
        $this->assertIsArray(json_decode($stored, true, 512, JSON_THROW_ON_ERROR));
    }

    public static function immutableTargets(): array
    {
        return [['schedule'], ['rate'], ['record'], ['delete-record'], ['delete-rate'], ['delete-schedule']];
    }

    #[DataProvider('immutableTargets')]
    public function test_published_sources_and_employee_history_reject_model_mutation(string $target): void
    {
        $schedule = $this->schedule();
        $employee = $this->employee();
        $this->officer();
        $this->post('/employees/'.$employee->id.'/salary-records', $this->recordData($schedule))->assertSessionHasNoErrors();
        $model = match ($target) {
            'schedule', 'delete-schedule' => $schedule, 'rate', 'delete-rate' => $schedule->rates()->first(), default => EmployeeSalaryRecord::sole(),
        };
        $this->expectException(LogicException::class);
        if (str_starts_with($target, 'delete-')) {
            $model->delete();
        } else {
            $model->setAttribute($target === 'schedule' ? 'name' : 'monthly_salary', $target === 'schedule' ? 'Tamper' : '0.01');
            $model->save();
        }
    }

    public function test_guests_csrf_inactive_employees_and_revoked_inflight_actors_are_blocked(): void
    {
        $schedule = $this->schedule();
        $employee = $this->employee();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/salary-schedules')->assertRedirect('/login');
        $this->post('/salary-schedules', $this->scheduleData())->assertRedirect('/login');
        $this->get('/employees/'.$employee->id.'/salary-records')->assertRedirect('/login');
        $officer = $this->officer();
        $employee->is_active = false;
        $employee->save();
        $this->post('/employees/'.$employee->id.'/salary-records', $this->recordData($schedule))->assertSessionHasErrors('employee');
        $request = Request::create('/salary-schedules', 'POST');
        $request->setUserResolver(fn () => $this->admin);
        $session = new Store('salary-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', 1);
        $request->setLaravelSession($session);
        User::whereKey($this->admin->id)->update(['auth_version' => 2]);
        try {
            app(SalaryService::class)->saveSchedule($request, $this->scheduleData(['effective_from' => '2040-01-01']));
            $this->fail('Revoked salary writer was allowed.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actor($officer);
        $this->post('/employees/'.$employee->id.'/salary-records', $this->recordData($schedule))->assertStatus(419);
        $this->actor($this->admin->fresh());
        $this->post('/salary-schedules', $this->scheduleData(['effective_from' => '2040-01-01']))->assertStatus(419);
        $this->assertDatabaseCount('salary_schedules', 1);
    }
}
