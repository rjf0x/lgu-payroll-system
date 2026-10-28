<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\Employee;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollFinalization;
use App\Models\PayrollRuleEvent;
use App\Models\PayrollRuleVersion;
use App\Models\StatutoryPreview;
use App\Models\User;
use App\Services\PayrollRuleVersionService;
use App\Services\PayrollWorkflowService;
use Database\Seeders\BrowserDemoSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PayrollConfigurationCoverageTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh')->assertSuccessful();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => Role::Administrator]);
        Employee::factory()->create(['office' => 'Accounting']);
        $this->actor($this->admin);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    private function version(string $department = 'all', string $classification = 'all', string $date = '2026-01-01', bool $activate = true, bool $demo = true, string $gsisRate = '0.09'): PayrollRuleVersion
    {
        $this->actor($this->admin);
        $data = PayrollRuleVersionTest::data([
            'department_scope' => $department, 'personnel_classification' => $classification,
            'effective_from' => $date, 'is_demonstration' => $demo,
        ]);
        $data['parameters']['contributions']['gsis']['rate'] = $gsisRate;
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::orderByDesc('id')->firstOrFail();
        if ($activate) {
            $this->post(route('payroll-rule-versions.activate', $version), PayrollRuleVersionTest::activation(['client_validation_confirmed' => 1]))->assertSessionHasNoErrors();
        }

        return $version->fresh();
    }

    public static function scopes(): array
    {
        return [['all', 'all'], ['all', 'permanent'], ['department:Accounting', 'all'], ['department:Accounting', 'part_time']];
    }

    #[DataProvider('scopes')]
    public function test_independent_coverage_choices_are_saved_displayed_copied_and_audited(string $department, string $classification): void
    {
        $version = $this->version($department, $classification);
        $expectedDepartment = $department === 'all' ? null : 'Accounting';
        $expectedClassification = $classification === 'all' ? null : $classification;
        $this->assertSame($expectedDepartment, $version->department);
        $this->assertSame($expectedClassification, $version->personnel_classification?->value);
        $this->assertSame(PayrollRuleVersion::coverageKey($expectedDepartment, $expectedClassification), $version->coverage_key);
        $this->assertSame($classification === 'all' ? ['permanent', 'coterminous', 'part_time'] : [$classification], array_keys($version->parameters['category_validation']));
        $this->assertSame(['unverified'], array_values(array_unique($version->parameters['category_validation'])));
        foreach (PayrollRuleEvent::where('payroll_rule_version_id', $version->id)->get() as $event) {
            $this->assertSame($expectedDepartment, $event->snapshot['department']);
            $this->assertSame($expectedClassification, $event->snapshot['personnel_classification']);
            $this->assertSame($version->coverage_key, $event->snapshot['coverage_key']);
        }
        $this->get('/payroll-rule-versions')->assertOk()->assertSee($version->department_label)->assertSee($version->classification_label);
        $this->get(route('payroll-rule-versions.show', $version))->assertOk()->assertSee($version->department_label)->assertSee($version->classification_label);
        $this->get('/payroll-rule-versions/create?copy='.$version->id)->assertOk()->assertSee('value="'.$department.'" selected', false)->assertSee('value="'.$classification.'" selected', false);
        $before = $version->getAttributes();
        $this->put(route('payroll-rule-versions.update', $version), PayrollRuleVersionTest::data(['department_scope' => 'all', 'personnel_classification' => 'all']))->assertSessionHasErrors('version');
        $this->assertSame($before, $version->fresh()->getAttributes());
    }

    public function test_department_choices_escape_html_preserve_accents_and_include_retained_configuration_departments(): void
    {
        Employee::factory()->create(['office' => '<Fictional & Office>']);
        Employee::factory()->create(['office' => 'Niño Office']);
        Employee::factory()->create(['office' => 'Nino Office']);
        $this->get('/payroll-rule-versions/create')->assertOk()->assertSee('All departments')->assertSee('All classifications')
            ->assertSee('&lt;Fictional &amp; Office&gt;', false)->assertSee('Niño Office')->assertSee('Nino Office');
        $version = $this->version('department:Accounting', 'all', activate: false);
        Employee::where('office', 'Accounting')->update(['office' => 'Transferred Office']);
        $this->get(route('payroll-rule-versions.edit', $version))->assertOk()->assertSee('value="department:Accounting" selected', false);
    }

    public static function invalidScopes(): array
    {
        return [
            ['department_scope', 'Accounting'], ['department_scope', 'department:Unknown'], ['department_scope', 'department:'],
            ['department_scope', []], ['department_scope', null], ['department_scope', ''],
            ['personnel_classification', 'unknown'], ['personnel_classification', []], ['personnel_classification', null],
        ];
    }

    #[DataProvider('invalidScopes')]
    public function test_invalid_scopes_do_not_save_a_version(string $field, mixed $value): void
    {
        $this->post('/payroll-rule-versions', PayrollRuleVersionTest::data([$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('payroll_rule_versions', 0);
        $this->assertDatabaseCount('payroll_rule_events', 0);
    }

    public function test_priority_is_department_first_then_classification_regardless_of_newer_lower_priority_sources(): void
    {
        $service = app(PayrollRuleVersionService::class);
        $global = $this->version(date: '2026-05-01');
        $category = $this->version(classification: 'permanent', date: '2026-04-01');
        $department = $this->version('department:Accounting', date: '2026-03-01');
        $exact = $this->version('department:Accounting', 'permanent', '2026-02-01');
        $this->assertSame($exact->id, $service->applicable('permanent', '2026-10-01', true, false, ' ACCOUNTING ')->id);
        $this->assertSame($department->id, $service->applicable('part_time', '2026-10-01', true, false, 'Accounting')->id);
        $this->assertSame($category->id, $service->applicable('permanent', '2026-10-01', true, false, 'Other')->id);
        $this->assertSame($global->id, $service->applicable('coterminous', '2026-10-01', true, false, 'Other')->id);
        $this->assertSame($category->id, $service->applicable('permanent', '2026-10-01', true)->id);
        $this->assertNull($service->applicable('permanent', '2026-01-01', true, false, 'Accounting'));
        $this->assertSame(config('payroll_statutory'), $service->profile('permanent', '2026-01-01', true, false, 'Accounting'));
        $laterDepartment = $this->version('department:Accounting', date: '2026-06-01');
        $this->assertSame($laterDepartment->id, $service->applicable('part_time', '2026-06-01', true, false, 'Accounting')->id);
        $this->assertSame($exact->id, $service->applicable('permanent', '2026-06-01', true, false, 'Accounting')->id);
    }

    public function test_latest_effective_activated_version_within_scope_and_data_mode_is_selected(): void
    {
        $service = app(PayrollRuleVersionService::class);
        $globalClass = $this->version(classification: 'permanent', date: '2026-09-01');
        $first = $this->version('department:Accounting', date: '2026-02-01');
        $second = $this->version('department:Accounting', date: '2026-04-01');
        $this->version('department:Accounting', date: '2026-08-01', activate: false);
        $this->version('department:Accounting', date: '2027-01-01');
        $real = $this->version('department:Accounting', date: '2026-07-01', demo: false);
        $this->assertSame($first->id, $service->applicable('permanent', '2026-03-31', true, false, 'Accounting')->id);
        $this->assertSame($second->id, $service->applicable('permanent', '2026-10-01', true, true, 'Accounting')->id);
        $this->assertSame($real->id, $service->applicable('permanent', '2026-10-01', false, false, 'Accounting')->id);
        $this->assertSame($globalClass->id, $service->applicable('permanent', '2026-10-01', true, false, 'Other')->id);
    }

    public function test_all_scope_pairs_can_activate_on_same_date_but_normalized_duplicates_cannot(): void
    {
        foreach (self::scopes() as [$department, $classification]) {
            $this->version($department, $classification);
        }
        $duplicate = $this->version('department: accounting ', 'all', activate: false);
        $this->post(route('payroll-rule-versions.activate', $duplicate), PayrollRuleVersionTest::activation())->assertSessionHasErrors('effective_from');
        $this->assertNull($duplicate->fresh()->activated_at);
        $this->assertDatabaseCount('payroll_rule_events', 9);
        $this->version('department:Accounting', 'all', demo: false);
        $this->assertSame(5, PayrollRuleVersion::whereNotNull('activated_at')->count());
    }

    public function test_draft_scope_changes_and_creation_retries_preserve_original_history(): void
    {
        $data = PayrollRuleVersionTest::data(['department_scope' => 'department:Accounting', 'personnel_classification' => 'all']);
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $originalEvent = PayrollRuleEvent::sole()->getAttributes();
        $edit = array_replace($data, ['record_version' => 1, 'department_scope' => 'all', 'personnel_classification' => 'permanent']);
        $this->put(route('payroll-rule-versions.update', $version), $edit)->assertSessionHasNoErrors();
        $before = $version->fresh()->getAttributes();
        $this->assertSame(PayrollRuleVersion::coverageKey(null, 'permanent'), $version->fresh()->coverage_key);
        $this->post('/payroll-rule-versions', array_replace($data, ['department_scope' => 'department: ACCOUNTING ']))->assertSessionHasNoErrors();
        $this->post('/payroll-rule-versions', array_replace($data, ['department_scope' => 'all']))->assertSessionHasErrors('submission_key');
        $this->post('/payroll-rule-versions', array_replace($data, ['personnel_classification' => 'permanent']))->assertSessionHasErrors('submission_key');
        $this->assertSame($before, $version->fresh()->getAttributes());
        $this->assertSame($originalEvent, PayrollRuleEvent::orderBy('id')->first()->getAttributes());
        $this->assertDatabaseCount('payroll_rule_events', 2);
    }

    public function test_database_constraint_prevents_duplicate_activation_of_shared_scope(): void
    {
        $this->version('department:Accounting');
        $duplicate = $this->version('department: ACCOUNTING ', activate: false);
        $this->expectException(UniqueConstraintViolationException::class);
        DB::table('payroll_rule_versions')->where('id', $duplicate->id)->update(['activation_slot' => 1, 'activated_at' => now()]);
    }

    public function test_legacy_migration_preserves_every_original_column_and_event(): void
    {
        $version = $this->version(classification: 'permanent');
        $before = $version->getAttributes();
        unset($before['department'], $before['coverage_key']);
        $events = DB::table('payroll_rule_events')->get()->all();
        $migration = require database_path('migrations/2026_10_28_000002_add_payroll_configuration_coverage.php');
        $migration->down();
        $migration->up();
        $after = $version->fresh()->getAttributes();
        unset($after['department'], $after['coverage_key']);
        $this->assertSame($before, $after);
        $this->assertSame(PayrollRuleVersion::coverageKey(null, 'permanent'), $version->fresh()->coverage_key);
        $this->assertEquals($events, DB::table('payroll_rule_events')->get()->all());
    }

    public static function sharedScopes(): array
    {
        return [['all', 'all'], ['department:Accounting', 'all'], ['department:Accounting', 'permanent']];
    }

    #[DataProvider('sharedScopes')]
    public function test_rollback_refuses_loss_of_new_scope_data(string $department, string $classification): void
    {
        $version = $this->version($department, $classification);
        $before = $version->getAttributes();
        $migration = require database_path('migrations/2026_10_28_000002_add_payroll_configuration_coverage.php');
        try {
            $migration->down();
            $this->fail('New coverage was discarded.');
        } catch (LogicException $error) {
            $this->assertStringContainsString('prevent rollback', $error->getMessage());
        }
        $this->assertSame($before, $version->fresh()->getAttributes());
    }

    public function test_browser_workflow_requires_fresh_department_profile_reviews_and_preserves_finalized_history(): void
    {
        // Remove our unrelated option fixture so only the three demo employees are prepared.
        Employee::query()->delete();
        $global = $this->version();
        $this->seed(BrowserDemoSeeder::class);
        $batch = PayrollBatch::sole();
        $oldReviews = StatutoryPreview::orderBy('id')->get()->map->getAttributes()->all();
        foreach (StatutoryPreview::all() as $review) {
            $this->assertSame($global->id, $review->parameters['_configuration']['version_id']);
        }
        $globalClass = $this->version(classification: 'permanent', date: '2026-10-01');
        $department = $this->version('department:Fictional Accounting Office', date: '2026-02-01', gsisRate: '0.10');
        $service = app(PayrollWorkflowService::class);
        $officer = User::where('username', 'demo.payroll')->sole();
        $this->actor($officer);
        $this->post(route('payroll-reviews.transition', [$batch, 'submit']), [
            'record_version' => $batch->record_version, 'review_token' => str_repeat('0', 64),
            'reason' => 'FICTIONAL attempt with stale statutory reviews', 'review_confirmed' => 1,
        ])->assertSessionHasErrors('review');
        $this->assertDatabaseCount('payroll_submissions', 0);
        $run = PayrollCalculationRun::where('payroll_batch_id', $batch->id)->orderByDesc('id')->firstOrFail();
        foreach ($run->items as $item) {
            $url = route('statutory-previews.show', [$batch, $run, $item]);
            $this->get($url)->assertOk()->assertSee('value="'.$department->id.'"', false);
            $data = StatutoryCalculatorTest::data(['record_version' => $batch->record_version, 'base_revision' => 1,
                'configuration_version_id' => $globalClass->id, 'department_scope' => 'all']);
            $this->post($url, $data)->assertSessionHasErrors('configuration_version_id');
            $data['configuration_version_id'] = $department->id;
            $this->post($url, $data)->assertSessionHasNoErrors();
            $latest = StatutoryPreview::where('payroll_calculation_item_id', $item->id)->orderByDesc('revision')->firstOrFail();
            $this->assertSame($department->id, $latest->parameters['_configuration']['version_id']);
            $this->assertSame('3000.00', $latest->result['contributions']['gsis']['amount']);
            $this->assertFalse($latest->result['production_ready']);
        }
        $this->assertSame($oldReviews, StatutoryPreview::orderBy('id')->limit(3)->get()->map->getAttributes()->all());
        foreach (['submit', 'approve', 'finalize'] as $action) {
            $actor = $action === 'submit' ? $officer : User::where('username', 'demo.approver')->sole();
            $this->actor($actor);
            $this->post(route('payroll-reviews.transition', [$batch, $action]), [
                'record_version' => $batch->fresh()->record_version, 'submission_id' => $service->latestSubmission($batch)?->id,
                'review_token' => $action === 'submit' ? $service->reviewToken($service->currentSnapshot($batch->fresh())) : null,
                'reason' => 'FICTIONAL shared configuration '.$action, 'review_confirmed' => 1,
            ])->assertSessionHasNoErrors();
        }
        $final = PayrollFinalization::sole();
        $before = [$final->getAttributes(), StatutoryPreview::orderBy('id')->get()->map->getAttributes()->all()];
        $this->version('department:Fictional Accounting Office', date: '2026-11-01');
        $this->assertSame($before, [$final->fresh()->getAttributes(), StatutoryPreview::orderBy('id')->get()->map->getAttributes()->all()]);
        $this->actor($officer);
        $this->get(route('payroll-documents.index', $batch))->assertOk();
    }
}
