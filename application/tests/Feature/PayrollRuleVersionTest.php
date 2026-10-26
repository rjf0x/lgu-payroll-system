<?php

namespace Tests\Feature;

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\PayrollRuleEvent;
use App\Models\PayrollRuleVersion;
use App\Models\User;
use App\Services\PayrollRuleVersionService;
use App\Services\StatutoryCalculator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PayrollRuleVersionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->admin = User::factory()->create(['role' => Role::Administrator]);
        $this->actor($this->admin);
    }

    private function actor(User $user): void
    {
        $this->actingAs($user)->withSession(['auth_version' => $user->auth_version]);
    }

    public static function data(array $overrides = []): array
    {
        $profile = config('payroll_statutory');
        foreach ($profile['contributions'] as &$component) {
            unset($component['method']);
        }
        unset($component);

        return array_replace([
            'submission_key' => (string) Str::uuid(), 'record_version' => 0,
            'name' => 'FICTIONAL CONFIGURATION', 'personnel_classification' => 'permanent',
            'is_demonstration' => 1, 'effective_from' => '2026-10-01', 'change_reason' => 'Fictional initial settings',
            'parameters' => ['contributions' => $profile['contributions'], 'tax' => $profile['tax']],
        ], $overrides);
    }

    public static function activation(array $overrides = []): array
    {
        return array_replace([
            'record_version' => 1, 'approval_reference' => 'FICTIONAL APPROVAL',
            'validation_reference' => 'FICTIONAL REVIEWED EXAMPLES', 'activation_reason' => 'Fictional activation',
            'authorization_confirmed' => 1,
        ], $overrides);
    }

    private function create(array $overrides = []): PayrollRuleVersion
    {
        $this->post('/payroll-rule-versions', self::data($overrides))->assertSessionHasNoErrors();

        return PayrollRuleVersion::orderByDesc('id')->firstOrFail();
    }

    private function activate(PayrollRuleVersion $version, array $overrides = []): void
    {
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation(array_replace(['record_version' => $version->fresh()->record_version], $overrides)))->assertSessionHasNoErrors();
    }

    public function test_draft_creation_revision_activation_and_change_snapshots(): void
    {
        $data = self::data();
        $this->get('/payroll-rule-versions/create')->assertOk()->assertSee('Create configuration draft');
        $this->post('/payroll-rule-versions', $data)->assertRedirect()->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $this->assertNull($version->activated_at);
        $this->assertDatabaseCount('payroll_rule_events', 1);
        $this->get('/payroll-rule-versions/'.$version->id.'/edit')->assertOk()->assertSee('0.09');
        $data['record_version'] = 1;
        $data['name'] = 'FICTIONAL REVISED SETTINGS';
        $data['parameters']['contributions']['gsis']['rate'] = '0.10';
        $data['change_reason'] = 'Fictional revised authority';
        $this->put('/payroll-rule-versions/'.$version->id, $data)->assertSessionHasNoErrors();
        $this->assertSame(2, $version->fresh()->record_version);
        $this->assertSame('0.09', PayrollRuleEvent::orderBy('id')->first()->snapshot['parameters']['contributions']['gsis']['rate']);
        $this->activate($version);
        $version->refresh();
        $this->assertNotNull($version->activated_at);
        $this->assertSame(3, $version->record_version);
        $this->assertSame($this->admin->id, $version->activated_by);
        $this->assertDatabaseCount('payroll_rule_events', 3);
        $this->get('/payroll-rule-versions/'.$version->id)->assertOk()->assertSee('Activated / locked')->assertSee('FICTIONAL APPROVAL')->assertSee('Fictional revised authority');
        $this->get('/payroll-rule-versions/create?copy='.$version->id)->assertOk()->assertSee('0.10')->assertDontSee('FICTIONAL REVISED SETTINGS');
    }

    public function test_creation_and_activation_retries_do_not_duplicate_versions_or_events(): void
    {
        $data = self::data();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_rule_versions', 1);
        $this->assertDatabaseCount('payroll_rule_events', 1);
        $version = PayrollRuleVersion::sole();
        $this->activate($version);
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_rule_events', 2);
        $this->post('/payroll-rule-versions', array_replace($data, ['name' => 'Changed retry']))->assertSessionHasErrors('submission_key');
    }

    public function test_equivalent_creation_retries_ignore_associative_key_order(): void
    {
        $data = self::data();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $before = PayrollRuleVersion::sole()->getAttributes();
        foreach ($data['parameters']['contributions'] as &$rule) {
            $rule = array_reverse($rule, true);
        }
        unset($rule);
        $data['parameters']['contributions'] = array_reverse($data['parameters']['contributions'], true);
        $data['parameters']['tax'] = array_reverse($data['parameters']['tax'], true);
        $data['parameters'] = array_reverse($data['parameters'], true);
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('payroll_rule_versions', 1);
        $this->assertDatabaseCount('payroll_rule_events', 1);
        $this->assertSame($before, PayrollRuleVersion::sole()->getAttributes());
        $data['parameters']['contributions']['gsis']['rate'] = '0.10';
        $this->post('/payroll-rule-versions', $data)->assertSessionHasErrors('submission_key');
        $this->assertSame($before, PayrollRuleVersion::sole()->getAttributes());
    }

    public static function auditTimezones(): array
    {
        return [['Asia/Manila'], ['UTC'], ['Pacific/Auckland']];
    }

    #[DataProvider('auditTimezones')]
    public function test_change_snapshots_keep_calendar_dates_separate_from_timestamps(string $timezone): void
    {
        $originalTimezone = date_default_timezone_get();
        $originalConfig = config('app.timezone');
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
        try {
            $data = self::data();
            $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
            $version = PayrollRuleVersion::sole();
            $data['record_version'] = 1;
            $data['effective_from'] = '2026-11-01';
            $this->put('/payroll-rule-versions/'.$version->id, $data)->assertSessionHasNoErrors();
            $this->activate($version);
            $events = PayrollRuleEvent::orderBy('id')->get();
            $this->assertSame(['2026-10-01', '2026-11-01', '2026-11-01'], $events->map(fn ($event) => $event->snapshot['effective_from'])->all());
            $this->assertMatchesRegularExpression('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z\z/', $events->last()->snapshot['activated_at']);
            $this->get('/payroll-rule-versions/'.$version->id)->assertOk()->assertSee('Effective from: 2026-10-01')->assertSee('Effective from: 2026-11-01');
        } finally {
            config(['app.timezone' => $originalConfig]);
            date_default_timezone_set($originalTimezone);
        }
    }

    public function test_legacy_creation_fingerprints_and_history_survive_reordered_retries_after_an_edit(): void
    {
        $data = self::data();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $event = PayrollRuleEvent::sole();
        $attributes = [
            'name' => $data['name'], 'personnel_classification' => $data['personnel_classification'],
            'is_demonstration' => true, 'effective_from' => $data['effective_from'],
            'parameters' => app(PayrollRuleVersionService::class)->parameters($data),
        ];
        $legacyHash = hash('sha256', json_encode([$attributes, $data['change_reason']], JSON_THROW_ON_ERROR));
        $legacySnapshot = $event->snapshot;
        $legacySnapshot['effective_from'] = '2026-09-30T16:00:00.000000Z';
        // Simulate existing rows in the isolated test database, without rewriting production history.
        DB::table('payroll_rule_versions')->where('id', $version->id)->update(['creation_hash' => $legacyHash]);
        DB::table('payroll_rule_events')->where('id', $event->id)->update(['snapshot' => json_encode($legacySnapshot, JSON_THROW_ON_ERROR)]);
        $eventBefore = $event->fresh()->getAttributes();
        $edited = array_replace($data, ['record_version' => 1, 'name' => 'FICTIONAL EDITED DRAFT', 'effective_from' => '2026-11-01']);
        $this->put('/payroll-rule-versions/'.$version->id, $edited)->assertSessionHasNoErrors();
        $versionBefore = $version->fresh()->getAttributes();
        $retry = $data;
        $retry['parameters']['contributions'] = array_reverse($retry['parameters']['contributions'], true);
        $this->post('/payroll-rule-versions', $retry)->assertSessionHasNoErrors();
        $this->assertSame($versionBefore, $version->fresh()->getAttributes());
        $this->assertSame($eventBefore, $event->fresh()->getAttributes());
        $this->assertSame('2026-10-01', $event->fresh()->effectiveDate());
        $this->assertDatabaseCount('payroll_rule_versions', 1);
        $this->assertDatabaseCount('payroll_rule_events', 2);
        $this->get('/payroll-rule-versions/'.$version->id)->assertOk()->assertSee('Effective from: 2026-10-01')->assertSee('2026-09-30T16:00:00.000000Z');
        $this->post('/payroll-rule-versions', array_replace($edited, ['record_version' => 0]))->assertSessionHasErrors('submission_key');
    }

    public function test_stale_edits_and_activation_cannot_overwrite_new_draft_values(): void
    {
        $data = self::data();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $data['record_version'] = 1;
        $this->put('/payroll-rule-versions/'.$version->id, $data)->assertSessionHasNoErrors();
        $this->put('/payroll-rule-versions/'.$version->id, $data)->assertSessionHasErrors('record_version');
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())->assertSessionHasErrors('record_version');
        $this->assertNull($version->fresh()->activated_at);
        $this->assertDatabaseCount('payroll_rule_events', 2);
    }

    public static function scopes(): array
    {
        $cases = [];
        foreach (PersonnelClassification::cases() as $category) {
            foreach ([true, false] as $demo) {
                $cases[] = [$category, $demo];
            }
        }

        return $cases;
    }

    #[DataProvider('scopes')]
    public function test_selection_is_effective_dated_category_and_data_use_scoped(PersonnelClassification $category, bool $demo): void
    {
        $service = app(PayrollRuleVersionService::class);
        $version = $this->create(['personnel_classification' => $category->value, 'is_demonstration' => (int) $demo]);
        $this->assertNull($service->applicable($category->value, '2026-10-01', $demo));
        $this->activate($version, $demo ? [] : ['client_validation_confirmed' => 1]);
        $this->assertNull($service->applicable($category->value, '2026-09-30', $demo));
        $this->assertSame($version->id, $service->applicable($category->value, '2026-10-01', $demo)->id);
        $this->assertNull($service->applicable($category->value, '2026-10-01', ! $demo));
        $other = $category === PersonnelClassification::Permanent ? 'part_time' : 'permanent';
        $this->assertNull($service->applicable($other, '2026-10-01', $demo));
        $later = $this->create(['personnel_classification' => $category->value, 'is_demonstration' => (int) $demo, 'effective_from' => '2027-01-01']);
        $this->activate($later, $demo ? [] : ['client_validation_confirmed' => 1]);
        $this->assertSame($version->id, $service->applicable($category->value, '2026-12-01', $demo)->id);
        $profile = $service->profile($category->value, '2027-01-01', $demo);
        $this->assertSame($later->id, $profile['_configuration']['version_id']);
        $this->assertSame(2027, $profile['review_year']);
        $this->assertSame([$category->value => 'unverified'], $profile['category_validation']);
    }

    public function test_same_scope_date_can_have_many_drafts_but_only_one_activation(): void
    {
        $first = $this->create();
        $second = $this->create();
        $this->activate($first);
        $this->post('/payroll-rule-versions/'.$second->id.'/activate', self::activation())->assertSessionHasErrors('effective_from');
        $this->assertNull($second->fresh()->activated_at);
        $this->assertDatabaseCount('payroll_rule_events', 3);
    }

    public function test_future_versions_do_not_replace_earlier_selection_and_expired_years_stay_incomplete(): void
    {
        $future = $this->create(['effective_from' => '2027-01-01']);
        $this->activate($future);
        $service = app(PayrollRuleVersionService::class);
        $this->assertSame(config('payroll_statutory'), $service->profile('permanent', '2026-10-01', true));
        $earlier = $this->create(['effective_from' => '2026-10-01']);
        $this->activate($earlier);
        $this->assertSame($earlier->id, $service->applicable('permanent', '2026-12-01', true)->id);
        $this->assertSame($future->id, $service->applicable('permanent', '2027-01-01', true)->id);
        $profile = $service->profile('permanent', '2028-01-01', true);
        $this->assertSame($future->id, $profile['_configuration']['version_id']);
        $calculator = app(StatutoryCalculator::class);
        $result = $calculator->calculate(StatutoryCalculatorTest::core(), $calculator->normalize(StatutoryCalculatorTest::data()), $profile, 'permanent', '2028-01');
        $this->assertSame('incomplete', $result['status']);
        $this->assertNull($result['net_before_tax_refund']);
        $this->assertFalse($result['production_ready']);
    }

    public function test_submitted_activation_fields_cannot_activate_a_draft(): void
    {
        $version = $this->create(['activated_at' => '2026-10-01 00:00:00', 'activation_slot' => 1, 'activated_by' => $this->admin->id, 'production_ready' => true]);
        $this->assertNull($version->activated_at);
        $this->assertNull($version->activation_slot);
        $this->assertNull($version->activated_by);
        $this->assertDatabaseCount('payroll_rule_events', 1);
    }

    public function test_external_configuration_requires_explicit_client_validation_confirmation(): void
    {
        $version = $this->create(['is_demonstration' => 0]);
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())->assertSessionHasErrors('client_validation_confirmed');
        $this->assertNull($version->fresh()->activated_at);
        $this->activate($version, ['client_validation_confirmed' => 1]);
        $this->assertNotNull($version->fresh()->activated_at);
        $confirmation = PayrollRuleEvent::where('action', 'activated')->sole()->snapshot['activation_confirmation'];
        $this->assertSame(['authorization_confirmed' => true, 'client_validation_confirmed' => true], $confirmation);
        $this->get('/payroll-rule-versions/'.$version->id)->assertOk()->assertSee('application does not independently verify external approval')->assertSee('real payroll');
    }

    public static function invalidInputs(): array
    {
        return [
            ['effective_from', '2026-10-15'], ['effective_from', '2026-02-30'],
            ['parameters.contributions.gsis.rate', '1.001'], ['parameters.contributions.gsis.rate', '0.00'],
            ['parameters.contributions.pagibig.ceiling', '0.00'], ['parameters.contributions.pagibig.threshold', '10000.00'],
            ['parameters.contributions.philhealth.floor', '100001.00'],
            ['parameters.tax.monthly_brackets.1', ['lower' => '20833.00', 'fixed' => '0.00', 'rate' => '0.15']],
            ['parameters.tax.annual_brackets.2.0', '1.00'], ['parameters.tax.monthly_brackets.1.2', '1.01'],
            ['parameters.tax.monthly_brackets', []], ['parameters.tax.source', ''],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_parameters_never_create_a_draft(string $path, mixed $value): void
    {
        $data = self::data();
        data_set($data, $path, $value);
        $this->post('/payroll-rule-versions', $data)->assertSessionHasErrors();
        $this->assertDatabaseCount('payroll_rule_versions', 0);
        $this->assertDatabaseCount('payroll_rule_events', 0);
    }

    public function test_activation_revalidates_parameters_and_requires_evidence_and_confirmation(): void
    {
        $version = $this->create();
        $data = self::activation();
        unset($data['approval_reference'], $data['validation_reference'], $data['authorization_confirmed']);
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', $data)->assertSessionHasErrors(['approval_reference', 'validation_reference', 'authorization_confirmed']);
        $parameters = $version->parameters;
        $parameters['contributions']['gsis']['rate'] = '0.00';
        $version->forceFill(['parameters' => $parameters])->save();
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())->assertSessionHasErrors('parameters.contributions.gsis');
        $this->assertNull($version->fresh()->activated_at);
    }

    public function test_activation_revalidates_required_sources_and_bonus_parameters(): void
    {
        $version = $this->create();
        $parameters = $version->parameters;
        unset($parameters['tax']['bonus_exemption']);
        $parameters['tax']['source'] = '';
        $version->forceFill(['parameters' => $parameters])->save();
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())
            ->assertSessionHasErrors(['parameters.tax.bonus_exemption', 'parameters.tax.source']);
        $this->assertNull($version->fresh()->activated_at);
        $this->assertDatabaseCount('payroll_rule_events', 1);
    }

    public static function roles(): array
    {
        return array_map(fn ($role) => [$role], Role::cases());
    }

    #[DataProvider('roles')]
    public function test_draft_visibility_and_mutation_permissions_are_enforced(Role $role): void
    {
        $draft = $this->create(['name' => 'PRIVATE DRAFT']);
        $active = $this->create(['name' => 'PUBLIC ACTIVATED']);
        $this->activate($active);
        $this->actor(User::factory()->create(['role' => $role]));
        $isAdmin = $role === Role::Administrator;
        $page = $this->get('/payroll-rule-versions')->assertOk()->assertSee('PUBLIC ACTIVATED');
        if ($isAdmin) {
            $page->assertSee('PRIVATE DRAFT');
        } else {
            $page->assertDontSee('PRIVATE DRAFT')->assertDontSee('Create configuration draft');
        }
        $this->get('/payroll-rule-versions/'.$draft->id)->assertStatus($isAdmin ? 200 : 403);
        $this->get('/payroll-rule-versions/'.$active->id)->assertOk();
        $this->get('/payroll-rule-versions/create')->assertStatus($isAdmin ? 200 : 403);
        if (! $isAdmin) {
            $this->post('/payroll-rule-versions', self::data())->assertForbidden();
            $this->put('/payroll-rule-versions/'.$draft->id, self::data())->assertForbidden();
            $this->post('/payroll-rule-versions/'.$draft->id.'/activate', self::activation())->assertForbidden();
            $this->get('/payroll-rule-versions/create?copy='.$draft->id)->assertForbidden();
        }
        $this->get('/dashboard')->assertOk()->assertSee(route('payroll-rule-versions.index'));
    }

    public function test_activated_versions_and_events_are_immutable_through_http_and_models(): void
    {
        $data = self::data();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        $this->activate($version);
        $this->get('/payroll-rule-versions/'.$version->id.'/edit')->assertStatus(409);
        $this->put('/payroll-rule-versions/'.$version->id, array_replace($data, ['record_version' => 2]))->assertSessionHasErrors('version');
        $this->delete('/payroll-rule-versions/'.$version->id)->assertStatus(405);
        foreach ([$version->fresh(), PayrollRuleEvent::first()] as $model) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    if ($operation === 'update') {
                        $model->setAttribute($model instanceof PayrollRuleVersion ? 'name' : 'reason', 'Forbidden change')->save();
                    } else {
                        $model->delete();
                    }
                    $this->fail('Configuration history was mutated.');
                } catch (LogicException) {
                    $this->assertDatabaseCount('payroll_rule_events', 2);
                }
            }
        }
    }

    public static function persistenceActions(): array
    {
        return [['create'], ['revise'], ['activate']];
    }

    #[DataProvider('persistenceActions')]
    public function test_event_persistence_failure_rolls_back_the_entire_change(string $action): void
    {
        $data = self::data();
        $version = null;
        if ($action !== 'create') {
            $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
            $version = PayrollRuleVersion::sole();
        }
        PayrollRuleEvent::creating(fn () => throw new RuntimeException('Fictional audit persistence failure'));
        if ($action === 'create') {
            $this->post('/payroll-rule-versions', $data)->assertStatus(500);
            $this->assertDatabaseCount('payroll_rule_versions', 0);
            $this->assertDatabaseCount('payroll_rule_events', 0);
        } else {
            if ($action === 'revise') {
                $this->put('/payroll-rule-versions/'.$version->id, array_replace($data, ['record_version' => 1, 'name' => 'Must roll back']))->assertStatus(500);
                $this->assertSame($data['name'], $version->fresh()->name);
            } else {
                $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())->assertStatus(500);
            }
            $this->assertNull($version->fresh()->activated_at);
            $this->assertSame(1, $version->fresh()->record_version);
            $this->assertDatabaseCount('payroll_rule_events', 1);
        }
    }

    public function test_rejected_configuration_values_do_not_prefill_user_or_other_forms(): void
    {
        $data = self::data(['name' => 'REJECTED-CONFIG-NAME']);
        $data['parameters']['contributions']['gsis']['rate'] = '0.00';
        $this->from('/payroll-rule-versions/create')->post('/payroll-rule-versions', $data)->assertSessionHasErrors()->assertSessionMissing('_old_input');
        $this->withCookie(config('session.cookie'), session()->getId())->get('/users/create')->assertOk()->assertDontSee('REJECTED-CONFIG-NAME');
        $data = self::data(['name' => "\xB1\x31"]);
        $this->postJson('/payroll-rule-versions', $data)->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('payroll_rule_versions', 0);
    }

    public function test_change_history_paginates_and_preserves_each_edit(): void
    {
        $data = self::data();
        $this->post('/payroll-rule-versions', $data)->assertSessionHasNoErrors();
        $version = PayrollRuleVersion::sole();
        for ($revision = 1; $revision <= 10; $revision++) {
            $data['record_version'] = $revision;
            $data['change_reason'] = 'FICTIONAL EDIT '.$revision;
            $this->put('/payroll-rule-versions/'.$version->id, $data)->assertSessionHasNoErrors();
        }
        $this->get('/payroll-rule-versions/'.$version->id)->assertOk()->assertSee('FICTIONAL EDIT 10')->assertDontSee('Fictional initial settings');
        $this->get('/payroll-rule-versions/'.$version->id.'?page=2')->assertOk()->assertSee('Fictional initial settings');
        $this->assertDatabaseCount('payroll_rule_events', 11);
    }

    public function test_guest_csrf_and_current_actor_rechecks(): void
    {
        $version = $this->create();
        $this->app['auth']->forgetGuards();
        $this->flushSession();
        $this->get('/payroll-rule-versions')->assertRedirect('/login');
        $this->post('/payroll-rule-versions', [])->assertRedirect('/login');
        $request = Request::create('/', 'POST');
        $request->setUserResolver(fn () => $this->admin);
        $session = new Store('configuration-inflight', new ArraySessionHandler(120));
        $session->start();
        $session->put('auth_version', $this->admin->auth_version);
        $request->setLaravelSession($session);
        User::whereKey($this->admin->id)->update(['role' => Role::Viewer->value]);
        try {
            app(PayrollRuleVersionService::class)->activate($request, $version, self::activation());
            $this->fail('Revoked administrator activated settings.');
        } catch (AuthorizationException) {
            $this->assertNull($version->fresh()->activated_at);
        }
        User::whereKey($this->admin->id)->update(['role' => Role::Administrator->value, 'auth_version' => 2]);
        try {
            app(PayrollRuleVersionService::class)->activate($request, $version, self::activation());
            $this->fail('Expired administrator session activated settings.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        User::whereKey($this->admin->id)->update(['is_active' => false]);
        $session->put('auth_version', 2);
        try {
            app(PayrollRuleVersionService::class)->activate($request, $version, self::activation());
            $this->fail('Disabled administrator activated settings.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        User::whereKey($this->admin->id)->update(['is_active' => true]);
        $this->actor($this->admin->fresh());
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->post('/payroll-rule-versions/'.$version->id.'/activate', self::activation())->assertStatus(419);
    }
}
