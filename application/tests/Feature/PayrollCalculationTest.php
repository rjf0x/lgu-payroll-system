<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationItem;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollInputRevision;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollCalculationService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
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

    private function entry(PersonnelClassification $category = PersonnelClassification::Permanent, bool $demo = true, bool $inputs = true): array
    {
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::where('personnel_classification', $category)->where('is_demonstration', $demo)->first();
        if (! $schedule) {
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL CALCULATION SOURCE', 'personnel_classification' => $category, 'is_demonstration' => $demo, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
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
        $batch = PayrollBatch::where('is_demonstration', $demo)->first();
        if (! $batch) {
            $this->post('/payroll-batches', ['period' => '2030-01', 'is_demonstration' => (int) $demo])->assertSessionHasNoErrors();
            $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        }
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
        if ($inputs) {
            $this->put('/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs', $this->inputData($batch))->assertSessionHasNoErrors();
        }

        return [$batch->fresh(), $entry];
    }

    private function inputData(PayrollBatch $batch): array
    {
        return [
            'record_version' => $batch->fresh()->record_version,
            'allowances' => [['label' => 'Fictional PERA', 'amount' => '100.07', 'is_applicable' => 1], ['label' => 'Fictional excluded hazard', 'amount' => '999.99', 'is_applicable' => 0]],
            'bonuses' => [['label' => 'Fictional bonus', 'amount' => '500.01']],
            'loans' => [['label' => 'Fictional GSIS loan', 'amount' => '200.02']],
            'adjustments' => [
                ['label' => 'Fictional deduction replacement', 'direction' => 'deduction', 'original_amount' => '300.03', 'adjusted_amount' => '111.11', 'reason' => 'Fictional approval'],
                ['label' => 'Fictional earning replacement', 'direction' => 'earning', 'original_amount' => '999.99', 'adjusted_amount' => '50.09', 'reason' => 'Fictional approval'],
            ],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUT AUTHORITY', 'change_reason' => 'Fictional full-month inputs', 'authorization_confirmed' => 1,
        ];
    }

    private function calculate(PayrollBatch $batch, ?int $version = null)
    {
        return $this->post('/payroll-batches/'.$batch->id.'/calculations', ['record_version' => $version ?? $batch->fresh()->record_version, 'net_pay' => '999999.99', 'status' => 'approved']);
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_core_preview_matches_expected_values_for_each_category_and_preserves_manual_inputs(PersonnelClassification $category): void
    {
        [$batch, $entry] = $this->entry($category);
        $input = PayrollInputRevision::sole();
        $before = $input->getAttributes();
        $entryBefore = $entry->getAttributes();
        $batchBefore = $batch->getAttributes();
        $this->calculate($batch)->assertRedirect()->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::sole();
        $item = $run->items()->sole();
        $this->assertSame('provisional', $run->status);
        $this->assertSame('12684.71', $item->breakdown['totals']['net_pay']);
        $this->assertSame('12995.84', $run->totals['total_earnings']);
        $this->assertSame('311.13', $run->totals['total_deductions']);
        $this->assertSame($input->id, $item->payroll_input_revision_id);
        $this->assertSame($input->payload, $item->input_snapshot['payload']);
        $this->assertSame($before, $input->fresh()->getAttributes());
        $this->assertSame($entryBefore, $entry->fresh()->getAttributes());
        $this->assertSame($batchBefore, $batch->fresh()->getAttributes());
        $this->get('/payroll-batches/'.$batch->id.'/calculations/'.$run->id)->assertOk()->assertSee('12684.71')->assertSee('Excluded — not applicable')->assertSee('Original reference PHP 300.03')->assertSee('not calculated')->assertSee('not for real payroll');
        $this->calculate($batch)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_calculation_runs', 1);
        $this->assertDatabaseCount('payroll_calculation_items', 1);
    }

    public function test_batch_totals_use_only_selected_employees_and_latest_revisions(): void
    {
        [$batch, $first] = $this->entry();
        [$batch, $second] = $this->entry();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $old = PayrollCalculationRun::sole();
        $before = $old->getAttributes();
        $this->assertSame('25369.42', $old->totals['net_pay']);
        $data = $this->inputData($batch);
        $data['loans'][0]['amount'] = '300.02';
        $this->put('/payroll-batches/'.$batch->id.'/employees/'.$first->id.'/inputs', $data)->assertSessionHasNoErrors();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $latest = PayrollCalculationRun::orderByDesc('id')->firstOrFail();
        $this->assertSame('25269.42', $latest->totals['net_pay']);
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->get('/payroll-batches/'.$batch->id.'/calculations/'.$old->id)->assertOk()->assertSee('Draft changed since this preview')->assertSee('Needs review:')->assertSee('25369.42');
        $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$second->id, ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $latest = PayrollCalculationRun::orderByDesc('id')->firstOrFail();
        $this->assertSame('12584.71', $latest->totals['net_pay']);
        $this->assertSame(1, $latest->items()->count());
        $this->assertDatabaseCount('payroll_input_revisions', 3);
        $this->assertSame(2, $old->items()->count());
    }

    public function test_missing_inputs_empty_batches_and_real_batches_are_blocked(): void
    {
        $this->post('/payroll-batches', ['period' => '2030-02', 'is_demonstration' => 1])->assertSessionHasNoErrors();
        $empty = PayrollBatch::sole();
        $this->calculate($empty)->assertSessionHasErrors('employees');
        [$batch] = $this->entry(inputs: false);
        $this->calculate($batch)->assertSessionHasErrors('inputs');
        [$real] = $this->entry(demo: false);
        $this->calculate($real)->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('payroll_calculation_runs', 0);
    }

    public function test_attendance_stale_versions_and_changed_sources_are_not_silently_ignored(): void
    {
        [$batch, $entry] = $this->entry();
        $version = $batch->record_version;
        $data = $this->inputData($batch);
        $data['attendance']['absence_days'] = '1.00';
        $this->put('/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs', $data)->assertSessionHasNoErrors();
        $this->calculate($batch, $version)->assertSessionHasErrors('record_version');
        $this->calculate($batch)->assertSessionHasErrors('attendance');
        $this->assertSame('1.00', PayrollInputRevision::orderByDesc('revision')->firstOrFail()->payload['attendance']['absence_days']);
        $this->put('/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs', $this->inputData($batch))->assertSessionHasNoErrors();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::sole();
        $entry->employee->update(['office' => 'Fictional changed office']);
        $this->calculate($batch)->assertSessionHasErrors('employees');
        $this->assertDatabaseCount('payroll_calculation_runs', 1);
        $this->get('/payroll-batches/'.$batch->id.'/calculations/'.$run->id)->assertOk()->assertSee('Needs review:')->assertSee('12684.71');
    }

    public function test_a_changed_input_context_requires_review_after_coverage_refresh(): void
    {
        [$batch, $entry] = $this->entry();
        $entry->employee->update(['office' => 'Fictional changed office']);
        $this->delete('/payroll-batches/'.$batch->id.'/employees/'.$entry->id, ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$entry->employee_id]])->assertSessionHasNoErrors();
        $this->calculate($batch)->assertSessionHasErrors('inputs');
        $this->assertDatabaseCount('payroll_calculation_runs', 0);
    }

    public function test_any_invalid_member_rolls_back_the_whole_preview(): void
    {
        [$batch] = $this->entry();
        $this->entry(inputs: false);
        $this->calculate($batch)->assertSessionHasErrors('inputs');
        $this->assertDatabaseCount('payroll_calculation_runs', 0);
        $this->assertDatabaseCount('payroll_calculation_items', 0);
    }

    public function test_negative_net_is_visible_without_rewriting_or_capping_manual_deductions(): void
    {
        [$batch, $entry] = $this->entry();
        $data = $this->inputData($batch);
        $data['loans'][0]['amount'] = '99999.99';
        $this->put('/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs', $data)->assertSessionHasNoErrors();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::sole();
        $this->assertSame('-87115.26', $run->totals['net_pay']);
        $this->get('/payroll-batches/'.$batch->id.'/calculations/'.$run->id)->assertOk()->assertSee('Negative core net pay')->assertSee('-87115.26');
        $this->assertSame('99999.99', PayrollInputRevision::orderByDesc('revision')->firstOrFail()->payload['loans'][0]['amount']);
    }

    public function test_failure_after_run_creation_rolls_back_results_without_changing_inputs(): void
    {
        [$batch] = $this->entry();
        $before = PayrollInputRevision::sole()->getAttributes();
        PayrollCalculationItem::creating(fn () => throw new RuntimeException('Fictional calculation persistence failure'));
        $this->calculate($batch)->assertStatus(500);
        $this->assertDatabaseCount('payroll_calculation_runs', 0);
        $this->assertDatabaseCount('payroll_calculation_items', 0);
        $this->assertSame($before, PayrollInputRevision::sole()->getAttributes());
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_role_restrictions_and_cross_batch_result_scope(Role $role): void
    {
        [$batch] = $this->entry();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::sole();
        [$other] = $this->entry(demo: false);
        $this->actor(User::factory()->create(['role' => $role]));
        $base = '/payroll-batches/'.$batch->id.'/calculations';
        $this->get($base)->assertStatus($role === Role::Administrator ? 403 : 200);
        $this->get($base.'/'.$run->id)->assertStatus($role === Role::Administrator ? 403 : 200);
        if ($role !== Role::PayrollOfficer) {
            $this->calculate($batch)->assertForbidden();
        } else {
            $this->calculate($batch)->assertSessionHasNoErrors();
        }
        if ($role !== Role::Administrator) {
            $this->get('/payroll-batches/'.$other->id.'/calculations/'.$run->id)->assertNotFound();
        }
        $this->assertDatabaseCount('payroll_calculation_runs', 1);
    }

    public static function mutations(): array
    {
        return [['run', 'update'], ['run', 'delete'], ['item', 'update'], ['item', 'delete']];
    }

    #[DataProvider('mutations')]
    public function test_saved_previews_are_immutable(string $target, string $operation): void
    {
        [$batch] = $this->entry();
        $this->calculate($batch)->assertSessionHasNoErrors();
        $model = $target === 'run' ? PayrollCalculationRun::sole() : PayrollCalculationItem::sole();
        $this->expectException(LogicException::class);
        if ($operation === 'delete') {
            $model->delete();
        } else {
            $model->setAttribute($target === 'run' ? 'totals' : 'breakdown', []);
            $model->save();
        }
    }

    public function test_guest_csrf_and_current_actor_rechecks(): void
    {
        [$batch] = $this->entry();
        $base = '/payroll-batches/'.$batch->id.'/calculations';
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($base)->assertRedirect('/login');
        $this->post($base, [])->assertRedirect('/login');
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->officer);
        $session = new Store('calc-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->officer->auth_version);
        $request->setLaravelSession($session);
        User::whereKey($this->officer->id)->update(['role' => Role::Viewer->value]);
        try {
            app(PayrollCalculationService::class)->calculate($request, $batch, $batch->record_version);
            $this->fail('Revoked officer calculated a preview.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('payroll_calculation_runs', 0);
        }
        User::whereKey($this->officer->id)->update(['role' => Role::PayrollOfficer->value, 'auth_version' => 2]);
        try {
            app(PayrollCalculationService::class)->calculate($request, $batch, $batch->record_version);
            $this->fail('Expired actor calculated a preview.');
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
        $this->calculate($batch)->assertStatus(419);
    }
}
