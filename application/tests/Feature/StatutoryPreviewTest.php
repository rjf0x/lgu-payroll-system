<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollInputRevision;
use App\Models\PayrollRuleVersion;
use App\Models\SalarySchedule;
use App\Models\StatutoryPreview;
use App\Models\User;
use App\Services\PayrollRuleVersionService;
use App\Services\StatutoryCalculator;
use App\Services\StatutoryPreviewService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StatutoryPreviewTest extends TestCase
{
    use RefreshDatabase;

    private User $officer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->officer = User::factory()->create(['role' => Role::PayrollOfficer]);
        $this->actor($this->officer);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function fixture(PersonnelClassification $category = PersonnelClassification::Permanent, string $period = '2026-10'): array
    {
        $employee = Employee::factory()->create(['personnel_classification' => $category, 'employment_start_date' => '2020-01-01']);
        $schedule = SalarySchedule::where('personnel_classification', $category)->first();
        if (! $schedule) {
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL STATUTORY SOURCE', 'personnel_classification' => $category, 'is_demonstration' => true, 'effective_from' => '2020-01-01', 'created_by' => $this->officer->id]);
            $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
            $schedule->authority_reference = 'FICTIONAL AUTHORITY';
            $schedule->published_at = now();
            $schedule->save();
        }
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $schedule->rates()->sole()->id,
            'effective_from' => '2020-01-01', 'change_type' => 'initial', 'personnel_classification' => $category->value,
            'position' => 'Fictional Clerk', 'monthly_salary' => '30000.00', 'is_demonstration' => true,
            'step_basis_date' => '2020-01-01', 'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Fictional fixture', 'created_by' => $this->officer->id,
        ]);
        $batch = PayrollBatch::whereDate('period_start', $period.'-01')->first();
        if (! $batch) {
            $this->post('/payroll-batches', ['period' => $period, 'is_demonstration' => 1])->assertSessionHasNoErrors();
            $batch = PayrollBatch::orderByDesc('id')->firstOrFail();
        }
        $this->post('/payroll-batches/'.$batch->id.'/employees', ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]])->assertSessionHasNoErrors();
        $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
        $this->put('/payroll-batches/'.$batch->id.'/employees/'.$entry->id.'/inputs', [
            'record_version' => $batch->fresh()->record_version, 'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional zero attendance', 'authorization_confirmed' => 1,
        ])->assertSessionHasNoErrors();
        $this->post('/payroll-batches/'.$batch->id.'/calculations', ['record_version' => $batch->fresh()->record_version])->assertSessionHasNoErrors();
        $run = PayrollCalculationRun::orderByDesc('id')->firstOrFail();

        return [$batch->fresh(), $run, $run->items()->where('employee_id', $employee->id)->sole()];
    }

    private function url(array $fixture): string
    {
        return route('statutory-previews.show', $fixture, false);
    }

    private function data(array $fixture, array $overrides = []): array
    {
        $profile = app(PayrollRuleVersionService::class)->profile(
            $fixture[2]->entry_snapshot['personnel_classification'], $fixture[0]->period_start->format('Y-m-d'), $fixture[0]->fresh()->is_demonstration,
        );

        return StatutoryCalculatorTest::data(array_replace([
            'record_version' => $fixture[0]->fresh()->record_version,
            'configuration_version_id' => $profile['_configuration']['version_id'] ?? null,
        ], $overrides));
    }

    public static function categories(): array
    {
        return array_map(fn ($category) => [$category], PersonnelClassification::cases());
    }

    #[DataProvider('categories')]
    public function test_explicit_scenarios_for_each_category_preserve_core_inputs_and_results(PersonnelClassification $category): void
    {
        $fixture = $this->fixture($category);
        $core = $fixture[2]->getAttributes();
        $inputs = PayrollInputRevision::sole()->getAttributes();
        $this->get($this->url($fixture))->assertOk()->assertSee('Unknown — leave amount unavailable')->assertSee('No statutory reference reviews saved');
        $this->post($this->url($fixture), $this->data($fixture))->assertRedirect()->assertSessionHasNoErrors();
        $review = StatutoryPreview::sole();
        $this->assertSame('25522.45', $review->result['net_before_tax_refund']);
        $this->assertFalse($review->result['production_ready']);
        $this->assertSame('unverified', $review->parameters['category_validation'][$category->value]);
        $this->assertSame($inputs, PayrollInputRevision::sole()->getAttributes());
        $this->assertSame($core, $fixture[2]->fresh()->getAttributes());
        $this->get($this->url($fixture))->assertOk()->assertSee('25522.45')->assertSee('827.55')->assertSee('not for real payroll');
        $this->get(route('payroll-calculations.show', [$fixture[0], $fixture[1]]))->assertOk()->assertSee('Contribution and withholding reference review');
    }

    public function test_retries_are_idempotent_new_revisions_preserve_rules_and_old_results(): void
    {
        $fixture = $this->fixture();
        $data = $this->data($fixture);
        $this->post($this->url($fixture), $data)->assertSessionHasNoErrors();
        $first = StatutoryPreview::sole();
        $before = $first->getAttributes();
        $this->post($this->url($fixture), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('statutory_previews', 1);
        $this->post($this->url($fixture), $this->data($fixture, ['base_revision' => 1, 'gsis_coverage' => 'not_applicable']))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('statutory_previews', 2);
        $this->assertSame($before, $first->fresh()->getAttributes());
        config(['payroll_statutory.contributions.gsis.rate' => '0.10']);
        $this->post($this->url($fixture), $this->data($fixture, ['base_revision' => 2]))->assertSessionHasNoErrors();
        $this->assertSame('0.09', $first->fresh()->parameters['contributions']['gsis']['rate']);
        $this->assertSame('0.10', StatutoryPreview::orderByDesc('id')->first()->parameters['contributions']['gsis']['rate']);
    }

    public function test_stale_review_and_stale_core_version_are_rejected(): void
    {
        $fixture = $this->fixture();
        $this->post($this->url($fixture), $this->data($fixture))->assertSessionHasNoErrors();
        $this->post($this->url($fixture), $this->data($fixture, ['gsis_basis' => '1000.00']))->assertSessionHasErrors('base_revision');
        PayrollBatch::whereKey($fixture[0]->id)->increment('record_version');
        $this->post($this->url($fixture), $this->data($fixture, ['base_revision' => 1]))->assertSessionHasErrors('record_version');
        $this->assertDatabaseCount('statutory_previews', 1);
        $this->get($this->url($fixture))->assertOk()->assertSee('Needs review:')->assertDontSee('Save reference preview');
    }

    public function test_json_object_key_order_does_not_break_retries_but_parameter_types_are_preserved(): void
    {
        $fixture = $this->fixture();
        $data = $this->data($fixture);
        $this->post($this->url($fixture), $data)->assertSessionHasNoErrors();
        $review = StatutoryPreview::sole();
        $parameters = array_reverse($review->parameters, true);
        $parameters['contributions'] = array_reverse($parameters['contributions'], true);
        DB::table('statutory_previews')->where('id', $review->id)->update([
            'inputs' => json_encode(array_reverse($review->inputs, true)),
            'parameters' => json_encode($parameters),
        ]);
        $this->post($this->url($fixture), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('statutory_previews', 1);
        config(['payroll_statutory.contributions.gsis.rate' => 0.09]);
        $this->post($this->url($fixture), $this->data($fixture, ['base_revision' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('statutory_previews', 2);
        $this->assertNull(StatutoryPreview::orderByDesc('id')->first()->result['contributions']['gsis']['amount']);
    }

    public function test_source_drift_blocks_even_an_identical_retry_and_preserves_history(): void
    {
        $fixture = $this->fixture();
        $this->post($this->url($fixture), $this->data($fixture))->assertSessionHasNoErrors();
        Employee::whereKey($fixture[2]->employee_id)->update(['office' => 'Changed fictional office']);
        $this->post($this->url($fixture), $this->data($fixture))->assertSessionHasErrors('employees');
        $this->assertDatabaseCount('statutory_previews', 1);
        $this->get($this->url($fixture))->assertOk()->assertSee('Needs review:')->assertSee('25522.45');
    }

    public function test_incomplete_review_persists_nulls_and_real_mode_is_rejected(): void
    {
        $fixture = $this->fixture();
        $this->post($this->url($fixture), $this->data($fixture, ['gsis_coverage' => 'unknown']))->assertSessionHasNoErrors();
        $this->assertNull(StatutoryPreview::sole()->result['net_before_tax_refund']);
        $this->get($this->url($fixture))->assertOk()->assertSee('Unavailable — incomplete review')->assertSee('applicability is unverified');
        PayrollBatch::whereKey($fixture[0]->id)->update(['is_demonstration' => false]);
        $this->post($this->url($fixture), $this->data($fixture))->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('statutory_previews', 1);
    }

    public function test_annual_bonus_reconciliation_is_available_in_december(): void
    {
        $fixture = $this->fixture(period: '2026-12');
        $this->post($this->url($fixture), $this->data($fixture, ['tax_method' => 'annual', 'taxable_regular' => '500000.00', 'qualifying_bonuses' => '135000.00']))->assertSessionHasNoErrors();
        $review = StatutoryPreview::sole();
        $this->assertSame('545000.00', $review->result['tax']['taxable_compensation']);
        $this->assertSame('51500.00', $review->result['tax']['liability']);
        $this->assertSame('-25150.00', $review->result['net_before_tax_refund']);
        $this->get($this->url($fixture))->assertOk()->assertSee('Negative reference net');
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_read_write_roles_and_cross_batch_scope(Role $role): void
    {
        $fixture = $this->fixture();
        $other = $this->fixture(period: '2026-11');
        $this->actor(User::factory()->create(['role' => $role]));
        $this->get($this->url($fixture))->assertStatus($role === Role::Administrator ? 403 : 200);
        $this->post($this->url($fixture), $this->data($fixture))->assertStatus($role === Role::PayrollOfficer ? 302 : 403);
        if ($role !== Role::Administrator) {
            $this->get($this->url([$other[0], $fixture[1], $fixture[2]]))->assertNotFound();
            $this->get($this->url([$fixture[0], $fixture[1], $other[2]]))->assertNotFound();
            if ($role === Role::PayrollOfficer) {
                $this->post($this->url([$other[0], $fixture[1], $fixture[2]]), $this->data($other))->assertNotFound();
            } else {
                $this->get($this->url($fixture))->assertDontSee('Save reference preview');
            }
        }
    }

    public function test_validation_never_restores_rejected_values_into_another_employee_form(): void
    {
        $fixture = $this->fixture();
        $other = $this->fixture(period: '2026-11');
        $this->post($this->url($other), $this->data($other, ['reference' => 'OTHER-SAVED-REFERENCE']))->assertSessionHasNoErrors();
        $this->from($this->url($fixture))->post($this->url($fixture), $this->data($fixture, ['gsis_basis' => '1.001', 'reference' => 'REJECTED-SECRET-REFERENCE']))->assertSessionHasErrors('gsis_basis');
        $this->withCookie(config('session.cookie'), session()->getId())->get($this->url($other))->assertOk()->assertDontSee('REJECTED-SECRET-REFERENCE')->assertSee('OTHER-SAVED-REFERENCE');
        $this->postJson($this->url($fixture), $this->data($fixture, ['reference' => "\xB1\x31"]))->assertUnprocessable()->assertJsonValidationErrors('reference');
        $this->assertDatabaseCount('statutory_previews', 1);
    }

    public static function validationPaths(): array
    {
        return [['request', 'gsis_basis'], ['encoding', 'reference'], ['service', 'record_version']];
    }

    #[DataProvider('validationPaths')]
    public function test_statutory_validation_does_not_flash_into_another_employees_salary_form(string $path, string $error): void
    {
        $fixture = $this->fixture();
        $other = $this->fixture(period: '2026-11');
        $data = $this->data($fixture, ['reason' => 'REJECTED-STATUTORY-REASON', 'position' => 'UNVALIDATED-POSITION']);
        $data = array_replace($data, match ($path) {
            'request' => ['gsis_basis' => '1.001'],
            'encoding' => ['reference' => "\xB1\x31"],
            'service' => ['record_version' => $fixture[0]->record_version + 1],
        });
        $this->from($this->url($fixture))->post($this->url($fixture), $data)
            ->assertSessionHasErrors($error)->assertSessionMissing('_old_input');
        $this->withCookie(config('session.cookie'), session()->getId())
            ->get('/employees/'.$other[2]->employee_id.'/salary-records/create?effective_from=2026-10-01&is_demonstration=1')
            ->assertOk()->assertDontSee('REJECTED-STATUTORY-REASON')->assertDontSee('UNVALIDATED-POSITION');
        $this->assertDatabaseCount('statutory_previews', 0);
    }

    public function test_cumulative_half_cent_correction_is_persisted_and_shown(): void
    {
        $fixture = $this->fixture();
        $this->post($this->url($fixture), $this->data($fixture, [
            'tax_method' => 'cumulative', 'taxable_regular' => '300000.02', 'elapsed_periods' => 3,
        ]))->assertSessionHasNoErrors();
        $review = StatutoryPreview::sole();
        $this->assertSame('50625.16', $review->result['tax']['liability']);
        $this->assertSame('-24275.16', $review->result['net_before_tax_refund']);
        $this->assertSame('50625.15500000', $review->result['tax']['bracket']['unrounded_liability']);
        $this->assertFalse($review->result['production_ready']);
        $this->get($this->url($fixture))->assertOk()->assertSee('50625.16');
    }

    public function test_malformed_brackets_save_an_explained_incomplete_review(): void
    {
        $fixture = $this->fixture();
        config(['payroll_statutory.tax.monthly_brackets.1' => ['lower' => '20833.00', 'fixed' => '0.00', 'rate' => '0.15']]);
        $this->post($this->url($fixture), $this->data($fixture))->assertRedirect()->assertSessionHasNoErrors();
        $review = StatutoryPreview::sole();
        $this->assertSame('incomplete', $review->result['status']);
        $this->assertNull($review->result['tax']['withholding']);
        $this->assertNull($review->result['net_before_tax_refund']);
        $this->get($this->url($fixture))->assertOk()->assertSee('Withholding reference table is missing or invalid.');
    }

    public function test_corrected_engine_preserves_old_reviews_and_does_not_reuse_old_results(): void
    {
        $fixture = $this->fixture();
        $data = $this->data($fixture, ['tax_method' => 'cumulative', 'taxable_regular' => '300000.02', 'elapsed_periods' => 3]);
        $calculator = app(StatutoryCalculator::class);
        $input = $calculator->normalize($data);
        $result = $calculator->calculate($fixture[2]->breakdown, $input, config('payroll_statutory'), 'permanent', '2026-10');
        $result['engine'] = 'statutory-reference-v1';
        $result['tax']['liability'] = '50625.15';
        $result['tax']['withholding'] = '50625.15';
        $result['net_before_tax_refund'] = '-24275.15';
        unset($result['tax']['bracket']['unrounded_liability']);
        $legacy = StatutoryPreview::forceCreate([
            'payroll_calculation_item_id' => $fixture[2]->id, 'revision' => 1, 'inputs' => $input,
            'parameters' => config('payroll_statutory'), 'result' => $result, 'created_by' => $this->officer->id,
        ])->refresh();
        $before = $legacy->getAttributes();
        $this->get($this->url($fixture))->assertOk()->assertSee('earlier calculation engine');
        $this->post($this->url($fixture), array_replace($data, ['base_revision' => 1]))->assertSessionHasNoErrors();
        $latest = StatutoryPreview::orderByDesc('revision')->first();
        $this->assertSame(2, $latest->revision);
        $this->assertSame(StatutoryCalculator::VERSION, $latest->result['engine']);
        $this->assertNotSame('statutory-reference-v1', $latest->result['engine']);
        $this->assertSame('50625.16', $latest->result['tax']['liability']);
        $this->assertSame($before, $legacy->fresh()->getAttributes());
        $this->post($this->url($fixture), array_replace($data, ['base_revision' => 1]))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('statutory_previews', 2);
    }

    public function test_preserved_review_history_is_paginated_and_models_reject_mutation(): void
    {
        $fixture = $this->fixture();
        for ($index = 0; $index < 6; $index++) {
            $this->post($this->url($fixture), $this->data($fixture, ['base_revision' => $index, 'reference' => 'FICTIONAL-'.$index]))->assertSessionHasNoErrors();
        }
        $this->get($this->url($fixture))->assertOk()->assertSee('FICTIONAL-5')->assertDontSee('FICTIONAL-0');
        $this->get($this->url($fixture).'?page=2')->assertOk()->assertSee('FICTIONAL-0');
        $review = StatutoryPreview::first();
        foreach (['update', 'delete'] as $operation) {
            try {
                if ($operation === 'update') {
                    $review->forceFill(['result' => []])->save();
                } else {
                    $review->delete();
                }
                $this->fail('Historical review was mutated.');
            } catch (LogicException) {
                $this->assertDatabaseCount('statutory_previews', 6);
            }
        }
    }

    public function test_persistence_failure_rolls_back_without_changing_core(): void
    {
        $fixture = $this->fixture();
        $before = $fixture[2]->getAttributes();
        StatutoryPreview::creating(fn () => throw new RuntimeException('Fictional persistence failure'));
        $this->post($this->url($fixture), $this->data($fixture))->assertStatus(500);
        $this->assertDatabaseCount('statutory_previews', 0);
        $this->assertSame($before, $fixture[2]->fresh()->getAttributes());
    }

    public function test_activated_configuration_only_changes_a_new_explicit_statutory_revision(): void
    {
        $fixture = $this->fixture();
        $this->post($this->url($fixture), $this->data($fixture))->assertSessionHasNoErrors();
        $old = StatutoryPreview::sole();
        $before = $old->getAttributes();
        $coreBefore = $fixture[2]->getAttributes();
        $inputBefore = PayrollInputRevision::sole()->getAttributes();
        $staleForm = $this->data($fixture, ['base_revision' => 1]);
        $this->actor(User::factory()->create(['role' => Role::Administrator]));
        $data = PayrollRuleVersionTest::data();
        $data['parameters']['contributions']['gsis']['rate'] = '0.10';
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', PayrollRuleVersionTest::activation())->assertSessionHasNoErrors();
        $this->assertSame($before, $old->fresh()->getAttributes());
        $this->actor($this->officer);
        $this->post($this->url($fixture), $staleForm)->assertSessionHasErrors('configuration_version_id');
        $this->assertDatabaseCount('statutory_previews', 1);
        $this->get($this->url($fixture))->assertOk()->assertSee('applicable configuration changed')->assertSee('25522.45');
        $this->post($this->url($fixture), $this->data($fixture, ['base_revision' => 1]))->assertSessionHasNoErrors();
        $latest = StatutoryPreview::orderByDesc('revision')->first();
        $this->assertSame('3000.00', $latest->result['contributions']['gsis']['amount']);
        $this->assertSame($version->id, $latest->parameters['_configuration']['version_id']);
        $this->assertSame('0.09', $old->fresh()->parameters['contributions']['gsis']['rate']);
        $this->assertSame($coreBefore, $fixture[2]->fresh()->getAttributes());
        $this->assertSame($inputBefore, PayrollInputRevision::sole()->getAttributes());
        $this->assertFalse($latest->result['production_ready']);
    }

    public static function configurationSourceReferences(): array
    {
        return [
            ['javascript:alert(document.domain)', false, Role::PayrollOfficer],
            ['JaVaScRiPt:alert(1)', false, Role::PayrollOfficer],
            ["java\tscript:alert(1)", false, Role::PayrollOfficer],
            ['data:text/html,<script>alert(1)</script>', false, Role::PayrollOfficer],
            ['file:///C:/private.txt', false, Role::PayrollOfficer],
            ['//example.test/reference', false, Role::PayrollOfficer],
            ['CLIENT-APPROVAL-2026-001', false, Role::PayrollOfficer],
            ['<img src=x onerror=alert(1)>', false, Role::PayrollOfficer],
            ['https://example.test/reference?a=1&b=2', true, Role::PayrollOfficer],
            ['http://example.test/reference', true, Role::PayrollOfficer],
            ['javascript:alert(1)', false, Role::Approver],
            ['javascript:alert(1)', false, Role::Viewer],
        ];
    }

    #[DataProvider('configurationSourceReferences')]
    public function test_stored_configuration_sources_only_link_safe_web_urls(string $reference, bool $shouldLink, Role $role): void
    {
        $fixture = $this->fixture();
        $this->actor(User::factory()->create(['role' => Role::Administrator]));
        $data = PayrollRuleVersionTest::data();
        $data['parameters']['contributions']['gsis']['source'] = $reference;
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', PayrollRuleVersionTest::activation())->assertSessionHasNoErrors();
        $before = $version->fresh()->getAttributes();
        $this->actor(User::factory()->create(['role' => $role]));
        $response = $this->get($this->url($fixture))->assertOk()->assertSee($reference);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $links = [];
        foreach ($document->getElementsByTagName('a') as $link) {
            $links[] = $link->getAttribute('href');
        }
        if ($shouldLink) {
            $this->assertContains($reference, $links);
        } else {
            $this->assertNotContains($reference, $links);
        }
        $this->assertSame($before, $version->fresh()->getAttributes());
    }

    public function test_older_maintained_configuration_identity_cannot_be_used_after_new_activation(): void
    {
        $fixture = $this->fixture();
        $this->actor(User::factory()->create(['role' => Role::Administrator]));
        $this->post('/payroll-rule-versions', PayrollRuleVersionTest::data(['effective_from' => '2026-09-01']))->assertSessionHasNoErrors();
        $first = PayrollRuleVersion::sole();
        $this->post('/payroll-rule-versions/'.$first->id.'/activate', PayrollRuleVersionTest::activation())->assertSessionHasNoErrors();
        $staleForm = $this->data($fixture);
        $this->post('/payroll-rule-versions', PayrollRuleVersionTest::data())->assertSessionHasNoErrors();
        $second = PayrollRuleVersion::orderByDesc('id')->first();
        $this->post('/payroll-rule-versions/'.$second->id.'/activate', PayrollRuleVersionTest::activation())->assertSessionHasNoErrors();
        $this->actor($this->officer);
        $this->post($this->url($fixture), $staleForm)->assertSessionHasErrors('configuration_version_id');
        $this->assertDatabaseCount('statutory_previews', 0);
        $this->post($this->url($fixture), $this->data($fixture))->assertSessionHasNoErrors();
        $this->assertSame($second->id, StatutoryPreview::sole()->parameters['_configuration']['version_id']);
    }

    public function test_activated_external_configuration_cannot_enable_real_payroll(): void
    {
        $fixture = $this->fixture();
        $this->actor(User::factory()->create(['role' => Role::Administrator]));
        $this->post('/payroll-rule-versions', PayrollRuleVersionTest::data(['is_demonstration' => 0]))->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', PayrollRuleVersionTest::activation(['client_validation_confirmed' => 1]))->assertSessionHasNoErrors();
        $this->actor($this->officer);
        PayrollBatch::whereKey($fixture[0]->id)->update(['is_demonstration' => false]);
        $this->post($this->url($fixture), $this->data($fixture, ['production_ready' => true]))->assertSessionHasErrors('batch');
        $this->post('/payroll-batches/'.$fixture[0]->id.'/calculations', ['record_version' => $fixture[0]->record_version])->assertSessionHasErrors('batch');
        $this->assertDatabaseCount('statutory_previews', 0);
        $this->assertDatabaseCount('payroll_calculation_runs', 1);
    }

    public function test_guest_csrf_and_current_actor_rechecks(): void
    {
        $fixture = $this->fixture();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get($this->url($fixture))->assertRedirect('/login');
        $this->post($this->url($fixture), [])->assertRedirect('/login');
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->officer);
        $session = new Store('statutory-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->officer->auth_version);
        $request->setLaravelSession($session);
        User::whereKey($this->officer->id)->update(['role' => Role::Viewer->value]);
        try {
            app(StatutoryPreviewService::class)->save($request, ...[...$fixture, $this->data($fixture)]);
            $this->fail('Revoked officer saved a review.');
        } catch (AuthorizationException) {
            $this->assertDatabaseCount('statutory_previews', 0);
        }
        User::whereKey($this->officer->id)->update(['role' => Role::PayrollOfficer->value, 'auth_version' => 2]);
        try {
            app(StatutoryPreviewService::class)->save($request, ...[...$fixture, $this->data($fixture)]);
            $this->fail('Expired session saved a review.');
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
        $this->post($this->url($fixture), $this->data($fixture))->assertStatus(419);
    }
}
