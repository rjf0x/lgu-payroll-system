<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollFinalization;
use App\Models\PayrollInputRevision;
use App\Models\PayrollRuleVersion;
use App\Models\PayrollSubmission;
use App\Models\PayrollWorkflowEvent;
use App\Models\SalarySchedule;
use App\Models\StatutoryPreview;
use App\Models\User;
use App\Services\PayrollWorkflowService;
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

class PayrollWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->approver = User::factory()->create(['role' => Role::Approver]);
        $this->actor($this->officer);
    }

    private function actor(User $actor): void
    {
        $this->actingAs($actor)->withSession(['auth_version' => $actor->auth_version]);
    }

    private function fixture(PersonnelClassification $category = PersonnelClassification::Permanent, string $period = '2026-10'): PayrollBatch
    {
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::create(['name' => 'FICTIONAL REVIEW SOURCE', 'personnel_classification' => $category, 'is_demonstration' => true, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
        $rate = $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
        $schedule->forceFill(['published_at' => now(), 'authority_reference' => 'FICTIONAL AUTHORITY'])->save();
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => '2020-01-01',
            'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Clerk',
            'monthly_salary' => '30000.00', 'is_demonstration' => true, 'step_basis_date' => '2020-01-01',
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Fictional review fixture', 'created_by' => $this->officer->id,
        ]);
        $this->post('/payroll-batches', ['period' => $period, 'is_demonstration' => 1])->assertSessionHasNoErrors();
        $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['record_version' => $batch->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $this->saveInputs($batch);
        $this->calculateAndReview($batch);

        return $batch->fresh();
    }

    private function saveInputs(PayrollBatch $batch, string $loan = '0.00'): void
    {
        $entry = $batch->entries()->sole();
        $this->put('/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs', [
            'record_version' => $batch->fresh()->record_version, 'loans' => [['label' => 'FICTIONAL LOAN', 'amount' => $loan]],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional reviewed inputs', 'authorization_confirmed' => 1,
        ])->assertSessionHasNoErrors();
    }

    private function calculateAndReview(PayrollBatch $batch): void
    {
        $this->post('/payroll-batches/'.$batch->id.'/calculations', ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::where('payroll_batch_id', $batch->id)->orderByDesc('id')->firstOrFail();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->fresh()->record_version,
        ]))->assertSessionHasNoErrors();
    }

    private function data(PayrollBatch $batch, string $action): array
    {
        $service = app(PayrollWorkflowService::class);

        return [
            'record_version' => $batch->fresh()->record_version,
            'submission_id' => $service->latestSubmission($batch)?->id,
            'review_token' => $action === 'submit' ? $service->reviewToken($service->currentSnapshot($batch->fresh())) : null,
            'reason' => 'FICTIONAL reviewed '.$action, 'review_confirmed' => 1,
        ];
    }

    private function act(PayrollBatch $batch, string $action, ?array $data = null)
    {
        return $this->post(route('payroll-reviews.transition', [$batch, $action]), $data ?? $this->data($batch, $action));
    }

    private function finalize(PayrollBatch $batch): void
    {
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $this->actor($this->approver);
        $this->act($batch, 'approve')->assertSessionHasNoErrors();
        $this->act($batch, 'finalize')->assertSessionHasNoErrors();
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_separate_approver_finalizes_exact_values_without_changing_inputs(PersonnelClassification $category): void
    {
        $batch = $this->fixture($category);
        $inputs = PayrollInputRevision::sole()->getAttributes();
        $core = PayrollCalculationRun::sole()->getAttributes();
        $reference = StatutoryPreview::sole()->getAttributes();
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('25522.45')->assertSee('Submit for approval');
        $this->finalize($batch);
        $final = PayrollFinalization::sole();
        $this->assertSame('finalized', $batch->fresh()->status);
        $this->assertSame(['total_earnings' => '30000.00', 'total_deductions' => '4477.55', 'net_pay' => '25522.45'], $final->snapshot['payroll']['totals']);
        $this->assertFalse($final->snapshot['payroll']['production_ready']);
        $this->assertTrue($final->snapshot['payroll']['is_demonstration']);
        $this->assertSame(PayrollSubmission::sole()->snapshot, $final->snapshot['payroll']);
        $this->assertSame(['submit', 'approve', 'finalize'], array_column($final->snapshot['workflow'], 'action'));
        $this->assertSame($inputs, PayrollInputRevision::sole()->getAttributes());
        $this->assertSame($core, PayrollCalculationRun::sole()->getAttributes());
        $this->assertSame($reference, StatutoryPreview::sole()->getAttributes());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Preserved finalized payroll')->assertSee('25522.45')->assertSee('Blockchain anchoring has not been performed')->assertDontSee('name="review_confirmed"', false);
        $this->get(route('payroll-batches.index'))->assertOk()->assertSee('Finalized');
    }

    public function test_return_correction_resubmission_preserves_every_submission_and_reason(): void
    {
        $batch = $this->fixture();
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $original = PayrollSubmission::sole()->getAttributes();
        $this->actor($this->approver);
        $this->act($batch, 'return', array_replace($this->data($batch, 'return'), ['reason' => 'Correct the fictional loan']))->assertSessionHasNoErrors();
        $this->assertSame('returned', $batch->fresh()->status);
        $this->actor($this->officer);
        $this->saveInputs($batch, '100.01');
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Calculate a current core preview');
        $this->calculateAndReview($batch);
        $this->finalize($batch);
        $this->assertSame('25422.44', PayrollFinalization::sole()->snapshot['payroll']['totals']['net_pay']);
        $this->assertSame($original, PayrollSubmission::orderBy('id')->first()->getAttributes());
        $this->assertDatabaseCount('payroll_submissions', 2);
        $this->assertDatabaseCount('payroll_workflow_events', 5);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Correct the fictional loan')->assertSee('Submission 1')->assertSee('Submission 2')->assertSee('25522.45')->assertSee('25422.44');
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_role_authorization_for_read_submission_review_and_finalization(Role $role): void
    {
        $batch = $this->fixture();
        $data = $this->data($batch, 'submit');
        $user = User::factory()->create(['role' => $role]);
        $this->actor($user);
        $this->get(route('payroll-reviews.show', $batch))->assertStatus($role === Role::Administrator ? 403 : 200);
        $this->act($batch, 'submit', $data)->assertStatus($role === Role::PayrollOfficer ? 302 : 403);
        if ($role !== Role::PayrollOfficer) {
            $this->actor($this->officer);
            $this->act($batch, 'submit', $data)->assertSessionHasNoErrors();
        }
        $this->actor($user);
        $this->act($batch, 'approve')->assertStatus($role === Role::Approver ? 302 : 403);
        if ($role !== Role::Approver) {
            $this->actor($this->approver);
            $this->act($batch, 'approve')->assertSessionHasNoErrors();
        }
        $this->actor($user);
        $this->act($batch, 'finalize')->assertStatus($role === Role::Approver ? 302 : 403);
    }

    public function test_submitter_cannot_approve_or_return_after_becoming_an_approver(): void
    {
        $batch = $this->fixture();
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $this->officer->forceFill(['role' => Role::Approver])->save();
        $this->actor($this->officer->fresh());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'approve']), false)
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'return']), false);
        $this->act($batch, 'approve')->assertForbidden();
        $this->act($batch, 'return')->assertForbidden();
        $this->actor($this->approver);
        $this->act($batch, 'approve')->assertSessionHasNoErrors();
        $this->actor($this->officer->fresh());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'finalize']), false)
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'return']), false);
        $this->act($batch, 'finalize')->assertForbidden();
        $this->assertDatabaseCount('payroll_finalizations', 0);
    }

    public static function reviewPreparationSources(): array
    {
        return [['submitter'], ['input'], ['core'], ['statutory'], ['coverage']];
    }

    #[DataProvider('reviewPreparationSources')]
    public function test_review_controls_follow_preparation_independence_after_role_changes(string $source): void
    {
        $batch = $this->fixture();
        $formerPreparer = User::factory()->create(['role' => Role::PayrollOfficer]);
        if ($source === 'core') {
            $this->saveInputs($batch);
        }
        $this->actor($formerPreparer);
        if ($source === 'input') {
            $this->saveInputs($batch);
        } elseif ($source === 'core') {
            $this->post(route('payroll-calculations.store', $batch), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        } elseif ($source === 'statutory') {
            $run = PayrollCalculationRun::sole();
            $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
                'record_version' => $batch->record_version, 'base_revision' => 1, 'gsis_coverage' => 'not_applicable',
            ]))->assertSessionHasNoErrors();
        } elseif ($source === 'coverage') {
            $entry = $batch->entries()->sole();
            $this->delete(route('payroll-employees.destroy', [$batch, $entry]), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
            $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$entry->employee_id]])->assertSessionHasNoErrors();
        }
        if ($source !== 'submitter') {
            $this->actor($this->officer);
            if ($source !== 'statutory') {
                $this->calculateAndReview($batch);
            }
        }
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $snapshot = PayrollSubmission::sole()->getAttributes();
        $submittedPayroll = PayrollSubmission::sole()->snapshot;
        $formerPreparer->forceFill(['role' => Role::Approver])->save();
        $this->actor($formerPreparer->fresh());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Fixed submitted payroll')
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'approve']), false)
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'return']), false);
        $this->act($batch, 'approve')->assertForbidden();
        $this->act($batch, 'return')->assertForbidden();

        $this->actor($this->approver);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()
            ->assertSee(route('payroll-reviews.transition', [$batch, 'approve']), false)
            ->assertSee(route('payroll-reviews.transition', [$batch, 'return']), false);
        $this->act($batch, 'approve')->assertSessionHasNoErrors();
        $this->actor($formerPreparer->fresh());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'finalize']), false)
            ->assertDontSee(route('payroll-reviews.transition', [$batch, 'return']), false);
        $this->act($batch, 'finalize')->assertForbidden();
        $this->act($batch, 'return')->assertForbidden();
        $this->assertSame('approved', $batch->fresh()->status);
        $this->assertSame($snapshot, PayrollSubmission::sole()->getAttributes());
        $this->assertDatabaseCount('payroll_workflow_events', 2);
        $this->assertDatabaseCount('payroll_finalizations', 0);

        $this->actor($this->approver);
        $this->get(route('payroll-reviews.show', $batch))->assertOk()
            ->assertSee(route('payroll-reviews.transition', [$batch, 'finalize']), false)
            ->assertSee(route('payroll-reviews.transition', [$batch, 'return']), false);
        $this->act($batch, 'finalize')->assertSessionHasNoErrors();
        $this->assertSame($submittedPayroll, PayrollFinalization::sole()->snapshot['payroll']);
    }

    public function test_other_preparers_cannot_approve_even_when_another_officer_submits(): void
    {
        $batch = $this->fixture();
        $other = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actor($other);
        $this->saveInputs($batch);
        $this->calculateAndReview($batch);
        $this->actor($this->officer);
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $other->forceFill(['role' => Role::Approver])->save();
        $this->actor($other->fresh());
        $this->act($batch, 'approve')->assertForbidden();
    }

    public function test_all_coverage_editors_are_retained_for_separation_of_duties(): void
    {
        $batch = $this->fixture();
        $editor = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actor($editor);
        $entry = $batch->entries()->sole();
        $this->delete(route('payroll-employees.destroy', [$batch, $entry]), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $this->actor($this->officer);
        $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$entry->employee_id]])->assertSessionHasNoErrors();
        $this->calculateAndReview($batch);
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $editor->forceFill(['role' => Role::Approver])->save();
        $this->actor($editor->fresh());
        $this->act($batch, 'approve')->assertForbidden();
    }

    public static function lockedStates(): array
    {
        return [['submitted'], ['approved'], ['finalized']];
    }

    #[DataProvider('lockedStates')]
    public function test_every_preparation_endpoint_is_locked_during_review_and_after_finalization(string $state): void
    {
        $batch = $this->fixture();
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $this->actor($this->approver);
        if ($state !== 'submitted') {
            $this->act($batch, 'approve')->assertSessionHasNoErrors();
        }
        if ($state === 'finalized') {
            $this->act($batch, 'finalize')->assertSessionHasNoErrors();
        }
        $this->actor($this->officer);
        $entry = $batch->entries()->sole();
        $run = PayrollCalculationRun::sole();
        $data = ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$entry->employee_id]];
        $this->post(route('payroll-employees.store', $batch), $data)->assertForbidden();
        $this->delete(route('payroll-employees.destroy', [$batch, $entry]), $data)->assertForbidden();
        $this->put(route('payroll-inputs.update', [$batch, $entry]), $data)->assertForbidden();
        $this->post(route('payroll-calculations.store', $batch), $data)->assertForbidden();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), $data)->assertForbidden();
        $this->assertDatabaseCount('payroll_input_revisions', 1);
        $this->assertDatabaseCount('statutory_previews', 1);
        $this->assertDatabaseCount('payroll_calculation_runs', 1);
    }

    public function test_stale_and_duplicate_transitions_do_not_duplicate_history_or_snapshots(): void
    {
        $batch = $this->fixture();
        $submit = $this->data($batch, 'submit');
        $this->act($batch, 'submit', array_replace($submit, ['record_version' => 1]))->assertSessionHasErrors('record_version');
        $this->act($batch, 'submit', $submit)->assertSessionHasNoErrors();
        $this->act($batch, 'submit', $submit)->assertForbidden();
        $this->actor($this->approver);
        $approve = $this->data($batch, 'approve');
        $this->act($batch, 'approve', array_replace($approve, ['record_version' => 1]))->assertSessionHasErrors('record_version');
        $this->act($batch, 'approve', $approve)->assertSessionHasNoErrors();
        $this->act($batch, 'approve', $approve)->assertSessionHasErrors('record_version');
        $finalize = $this->data($batch, 'finalize');
        $this->act($batch, 'finalize', $finalize)->assertSessionHasNoErrors();
        $this->act($batch, 'finalize', $finalize)->assertForbidden();
        $this->assertDatabaseCount('payroll_submissions', 1);
        $this->assertDatabaseCount('payroll_workflow_events', 3);
        $this->assertDatabaseCount('payroll_finalizations', 1);
    }

    public function test_missing_reason_confirmation_forged_payload_and_cross_batch_submission(): void
    {
        $batch = $this->fixture();
        $data = $this->data($batch, 'submit');
        $this->act($batch, 'submit', array_replace($data, ['reason' => ' ', 'review_confirmed' => 0]))->assertSessionHasErrors(['reason', 'review_confirmed']);
        $this->assertNull(session('_old_input'));
        $this->act($batch, 'submit', array_replace($data, ['snapshot' => ['totals' => ['net_pay' => '999999.99']], 'status' => 'finalized']))->assertSessionHasNoErrors();
        $first = PayrollSubmission::sole();
        $this->assertSame('25522.45', $first->snapshot['totals']['net_pay']);
        $second = $this->fixture(PersonnelClassification::Coterminous, '2026-11');
        $this->act($second, 'submit')->assertSessionHasNoErrors();
        $this->actor($this->approver);
        $this->act($second, 'approve', array_replace($this->data($second, 'approve'), ['submission_id' => $first->id]))->assertStatus(409);
        $this->act($batch, 'finalize')->assertForbidden();
        $this->assertDatabaseCount('payroll_workflow_events', 2);
    }

    public function test_statutory_change_after_opening_review_requires_reload_even_without_batch_version_change(): void
    {
        $batch = $this->fixture();
        $data = $this->data($batch, 'submit');
        $run = PayrollCalculationRun::sole();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->record_version, 'base_revision' => 1, 'gsis_coverage' => 'not_applicable',
        ]))->assertSessionHasNoErrors();
        $this->act($batch, 'submit', $data)->assertSessionHasErrors('review');
        $this->assertDatabaseCount('payroll_submissions', 0);
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $this->assertSame('28222.45', PayrollSubmission::sole()->snapshot['totals']['net_pay']);
    }

    public function test_changed_employee_or_configuration_blocks_approval_and_finalization_but_allows_return(): void
    {
        $batch = $this->fixture();
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $this->actor($this->approver);
        $employee = $batch->entries()->sole()->employee;
        $employee->update(['office' => 'Fictional changed office']);
        $this->act($batch, 'approve')->assertSessionHasErrors('review');
        $employee->update(['office' => $batch->entries()->sole()->office]);
        $this->act($batch, 'approve')->assertSessionHasNoErrors();
        config(['payroll_statutory.contributions.gsis.rate' => '0.10']);
        $this->act($batch, 'finalize')->assertSessionHasErrors('review');
        $this->act($batch, 'return')->assertSessionHasNoErrors();
        $this->assertSame('returned', $batch->fresh()->status);
        $this->assertDatabaseCount('payroll_finalizations', 0);
    }

    public function test_final_snapshot_remains_fixed_after_sources_and_configuration_change(): void
    {
        $batch = $this->fixture();
        $this->finalize($batch);
        $final = PayrollFinalization::sole()->getAttributes();
        $batch->entries()->sole()->employee->update(['office' => 'Changed after finalization', 'is_active' => false]);
        config(['payroll_statutory.contributions.gsis.rate' => '0.10']);
        $this->assertSame($final, PayrollFinalization::sole()->getAttributes());
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('25522.45')->assertDontSee('Changed after finalization');
    }

    private function activateConfiguration(): void
    {
        $admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actor($admin);
        $data = PayrollRuleVersionTest::data();
        $data['parameters']['contributions']['gsis']['rate'] = '0.10';
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $this->post('/payroll-rule-versions/'.PayrollRuleVersion::sole()->id.'/activate', PayrollRuleVersionTest::activation())->assertSessionHasNoErrors();
    }

    public function test_real_configuration_activation_does_not_recalculate_finalized_payroll(): void
    {
        $batch = $this->fixture();
        $this->finalize($batch);
        $final = PayrollFinalization::sole()->getAttributes();
        $submission = PayrollSubmission::sole()->getAttributes();
        $this->activateConfiguration();
        $this->assertSame($final, PayrollFinalization::sole()->getAttributes());
        $this->assertSame($submission, PayrollSubmission::sole()->getAttributes());
        $this->assertSame('0.09', PayrollFinalization::sole()->snapshot['payroll']['employees'][0]['parameters']['contributions']['gsis']['rate']);
        $this->actor(User::factory()->create(['role' => Role::Viewer]));
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('25522.45')->assertSee('Preserved finalized payroll');
    }

    public function test_configuration_activation_requires_return_and_review_before_finalization(): void
    {
        $batch = $this->fixture();
        $this->act($batch, 'submit')->assertSessionHasNoErrors();
        $before = PayrollSubmission::sole()->getAttributes();
        $this->activateConfiguration();
        $this->actor($this->approver);
        $this->act($batch, 'approve')->assertSessionHasErrors('review');
        $this->act($batch, 'return')->assertSessionHasNoErrors();
        $this->actor($this->officer);
        $this->post(route('payroll-calculations.store', $batch), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::orderByDesc('id')->firstOrFail();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->fresh()->record_version, 'configuration_version_id' => PayrollRuleVersion::sole()->id,
        ]))->assertSessionHasNoErrors();
        $this->finalize($batch);
        $this->assertSame('25222.45', PayrollFinalization::sole()->snapshot['payroll']['totals']['net_pay']);
        $this->assertSame($before, PayrollSubmission::orderBy('id')->first()->getAttributes());
    }

    public function test_multiple_employee_totals_are_exact_and_a_missing_review_blocks_the_whole_submission(): void
    {
        $batch = $this->fixture();
        $entry = $batch->entries()->sole();
        $employee = Employee::factory()->create(['personnel_classification' => PersonnelClassification::Permanent, 'employment_start_date' => '2020-01-01']);
        $source = $entry->salaryRecord;
        $attributes = $source->getAttributes();
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at']);
        $attributes['employee_id'] = $employee->id;
        EmployeeSalaryRecord::forceCreate($attributes);
        $this->post(route('payroll-employees.store', $batch), ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $second = $batch->entries()->where('employee_id', $employee->id)->sole();
        $this->put(route('payroll-inputs.update', [$batch, $second]), [
            'record_version' => $batch->fresh()->record_version, 'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'loans' => [['label' => 'FICTIONAL SECOND LOAN', 'amount' => '123.45']],
            'authority_reference' => 'FICTIONAL', 'change_reason' => 'Second employee', 'authorization_confirmed' => 1,
        ])->assertSessionHasNoErrors();
        $this->post(route('payroll-calculations.store', $batch), ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::orderByDesc('id')->firstOrFail();
        foreach ($run->items()->orderBy('employee_id')->get() as $index => $item) {
            $this->post(route('statutory-previews.store', [$batch, $run, $item]), StatutoryCalculatorTest::data(['record_version' => $batch->fresh()->record_version]))->assertSessionHasNoErrors();
            if ($index === 0) {
                $this->act($batch, 'submit', ['record_version' => $batch->fresh()->record_version, 'review_token' => str_repeat('a', 64), 'reason' => 'FICTIONAL', 'review_confirmed' => 1])->assertSessionHasErrors('review');
                $this->assertDatabaseCount('payroll_submissions', 0);
            }
        }
        $this->finalize($batch);
        $snapshot = PayrollFinalization::sole()->snapshot['payroll'];
        $this->assertCount(2, $snapshot['employees']);
        $this->assertSame(['total_earnings' => '60000.00', 'total_deductions' => '9078.55', 'net_pay' => '50921.45'], $snapshot['totals']);
    }

    public function test_a_stale_batch_model_cannot_overwrite_a_finalized_batch(): void
    {
        $batch = $this->fixture();
        $this->finalize($batch);
        $this->expectException(LogicException::class);
        $batch->forceFill(['record_version' => 999])->save();
    }

    public function test_unrecorded_approval_cannot_finalize_and_invalid_text_does_not_flash_to_other_forms(): void
    {
        $batch = $this->fixture();
        $data = $this->data($batch, 'submit');
        $this->act($batch, 'submit', array_replace($data, ['reason' => "Bad\xFF"]))->assertSessionHasErrors('reason');
        $this->assertNull(session('_old_input'));
        $this->act($batch, 'submit', array_replace($data, ['reason' => '<script>fictional</script>']))->assertSessionHasNoErrors();
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('&lt;script&gt;fictional&lt;/script&gt;', false)->assertDontSee('<script>fictional</script>', false);
        $batch->fresh()->forceFill(['status' => 'approved'])->save();
        $this->actor($this->approver);
        $this->act($batch, 'finalize')->assertSessionHasErrors('review');
        $this->assertDatabaseCount('payroll_finalizations', 0);
    }

    public function test_empty_missing_incomplete_stale_and_real_payroll_cannot_be_submitted(): void
    {
        $this->post('/payroll-batches', ['period' => '2026-09', 'is_demonstration' => 1])->assertSessionHasNoErrors();
        $empty = PayrollBatch::sole();
        $fake = ['record_version' => $empty->record_version, 'review_token' => str_repeat('a', 64), 'reason' => 'FICTIONAL', 'review_confirmed' => 1];
        $this->act($empty, 'submit', $fake)->assertSessionHasErrors('review');
        $batch = $this->fixture();
        $data = $this->data($batch, 'submit');
        $run = PayrollCalculationRun::sole();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->record_version, 'base_revision' => 1, 'gsis_coverage' => 'unknown',
        ]))->assertSessionHasNoErrors();
        $this->act($batch, 'submit', $data)->assertSessionHasErrors('review');
        $this->saveInputs($batch);
        $this->act($batch, 'submit', array_replace($data, ['record_version' => $batch->fresh()->record_version]))->assertSessionHasErrors('review');
        $batch->fresh()->forceFill(['is_demonstration' => false])->save();
        $this->act($batch, 'submit', array_replace($data, ['record_version' => $batch->fresh()->record_version]))->assertSessionHasErrors('review');
        $this->assertDatabaseCount('payroll_submissions', 0);
    }

    public function test_negative_net_and_unexecuted_refunds_are_blocked(): void
    {
        $batch = $this->fixture();
        $this->saveInputs($batch, '99999.99');
        $this->calculateAndReview($batch);
        $this->act($batch, 'submit', ['record_version' => $batch->fresh()->record_version, 'review_token' => str_repeat('a', 64), 'reason' => 'FICTIONAL', 'review_confirmed' => 1])->assertSessionHasErrors('review');
        $this->saveInputs($batch);
        $this->calculateAndReview($batch);
        $run = PayrollCalculationRun::orderByDesc('id')->firstOrFail();
        $this->post(route('statutory-previews.store', [$batch, $run, $run->items()->sole()]), StatutoryCalculatorTest::data([
            'record_version' => $batch->fresh()->record_version, 'base_revision' => 1,
            'tax_method' => 'cumulative', 'elapsed_periods' => 1, 'prior_withheld' => '99999.99',
        ]))->assertSessionHasNoErrors();
        $this->get(route('payroll-reviews.show', $batch))->assertOk()->assertSee('Negative net pay or tax credits/refunds');
        $this->assertDatabaseCount('payroll_submissions', 0);
    }

    public static function persistenceFailures(): array
    {
        return [['submission'], ['submit-event'], ['approve-event'], ['finalize-event'], ['finalization']];
    }

    #[DataProvider('persistenceFailures')]
    public function test_persistence_failure_rolls_back_entire_transition(string $stage): void
    {
        $batch = $this->fixture();
        $action = 'submit';
        if (in_array($stage, ['approve-event', 'finalize-event', 'finalization'], true)) {
            $this->act($batch, 'submit')->assertSessionHasNoErrors();
            $this->actor($this->approver);
            $action = 'approve';
        }
        if (in_array($stage, ['finalize-event', 'finalization'], true)) {
            $this->act($batch, 'approve')->assertSessionHasNoErrors();
            $action = 'finalize';
        }
        $before = $batch->fresh()->getAttributes();
        $events = PayrollWorkflowEvent::count();
        $submissions = PayrollSubmission::count();
        $model = match ($stage) {
            'submission' => PayrollSubmission::class, 'finalization' => PayrollFinalization::class, default => PayrollWorkflowEvent::class,
        };
        $model::creating(fn () => throw new RuntimeException('FICTIONAL persistence failure'));
        $this->act($batch, $action)->assertStatus(500);
        $this->assertSame($before, $batch->fresh()->getAttributes());
        $this->assertDatabaseCount('payroll_workflow_events', $events);
        $this->assertDatabaseCount('payroll_submissions', $submissions);
        $this->assertDatabaseCount('payroll_finalizations', 0);
    }

    public static function immutableModels(): array
    {
        $cases = [];
        foreach (['submission', 'event', 'finalization', 'batch', 'entry'] as $target) {
            foreach (['update', 'delete'] as $operation) {
                $cases[] = [$target, $operation];
            }
        }

        return $cases;
    }

    #[DataProvider('immutableModels')]
    public function test_finalized_records_and_review_history_reject_ordinary_model_mutations(string $target, string $operation): void
    {
        $batch = $this->fixture();
        $this->finalize($batch);
        $model = match ($target) {
            'submission' => PayrollSubmission::sole(), 'event' => PayrollWorkflowEvent::first(),
            'finalization' => PayrollFinalization::sole(), 'batch' => $batch->fresh(), 'entry' => $batch->entries()->sole(),
        };
        $this->expectException(LogicException::class);
        if ($operation === 'delete') {
            $model->delete();
        } else {
            $model->setAttribute(match ($target) {
                'batch' => 'status', 'entry' => 'employee_name', 'event' => 'reason', default => 'snapshot'
            }, $target === 'batch' ? 'draft' : ($target === 'entry' || $target === 'event' ? 'Changed' : []));
            $model->save();
        }
    }

    public function test_guest_csrf_and_current_actor_revocation_checks(): void
    {
        $batch = $this->fixture();
        $data = $this->data($batch, 'submit');
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->officer);
        $session = new Store('workflow-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->officer->auth_version);
        $request->setLaravelSession($session);
        User::whereKey($this->officer->id)->update(['role' => Role::Viewer->value]);
        try {
            app(PayrollWorkflowService::class)->transition($request, $batch, 'submit', $data);
            $this->fail('Revoked officer submitted payroll.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('payroll_submissions', 0);
        }
        User::whereKey($this->officer->id)->update(['role' => Role::PayrollOfficer->value, 'auth_version' => 2]);
        try {
            app(PayrollWorkflowService::class)->transition($request, $batch, 'submit', $data);
            $this->fail('Expired session submitted payroll.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get(route('payroll-reviews.show', $batch))->assertRedirect('/login');
        $this->act($batch, 'submit', $data)->assertRedirect('/login');
        $this->actor($this->officer->fresh());
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->act($batch, 'submit', $data)->assertStatus(419);
    }
}
