<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\PayrollBatch;
use App\Models\PayrollRuleEvent;
use App\Models\PayrollRuleVersion;
use App\Models\User;
use App\Services\PayrollRuleVersionService;
use App\Services\StatutoryCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Configuration verification requires MySQL.');
}

function configurationCheckRequest(User $actor): Request
{
    $request = Request::create('/', 'POST');
    $request->setUserResolver(fn () => $actor);
    $session = new Store('configuration-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);

    return $request;
}

$nonce = strtolower(bin2hex(random_bytes(12)));
$connection->beginTransaction();
try {
    $actor = User::factory()->create(['username' => 'configuration-check-'.$nonce, 'role' => Role::Administrator]);
    $request = configurationCheckRequest($actor);
    $service = app(PayrollRuleVersionService::class);
    $date = CarbonImmutable::create(random_int(8000, 8998), 1, 1);
    while (PayrollRuleVersion::whereYear('effective_from', $date->year)->exists() || PayrollBatch::whereYear('period_start', $date->year)->exists()) {
        $date = $date->addYear();
        if ($date->year > 9990) {
            throw new RuntimeException('No isolated configuration verification year is available.');
        }
    }
    $template = config('payroll_statutory');
    foreach ($template['contributions'] as &$rule) {
        unset($rule['method']);
        $rule['source'] = 'FICTIONAL CONFIGURATION SOURCE';
        $rule['scope'] = 'Fictional arithmetic verification only';
    }
    unset($rule);
    $template['tax']['source'] = 'FICTIONAL ANNUAL SOURCE';
    $template['tax']['monthly_source'] = 'FICTIONAL MONTHLY SOURCE';
    foreach (PersonnelClassification::cases() as $category) {
        foreach ([true, false] as $demo) {
            $data = [
                'submission_key' => (string) Str::uuid(), 'record_version' => 0,
                'name' => 'FICTIONAL CONFIGURATION CHECK '.$nonce, 'personnel_classification' => $category->value,
                'is_demonstration' => (int) $demo, 'effective_from' => $date->format('Y-m-d'),
                'change_reason' => 'Fictional initial settings',
                'parameters' => ['contributions' => $template['contributions'], 'tax' => $template['tax']],
            ];
            $draft = $service->save($request, $data)->refresh();
            if ($service->save($request, $data)->id !== $draft->id || $service->applicable($category->value, $date->format('Y-m-d'), $demo) !== null) {
                throw new RuntimeException('Creation retry duplicated a version or selected an inactive draft.');
            }
            $original = $data;
            $retry = $data;
            foreach ($retry['parameters']['contributions'] as &$rule) {
                $rule = array_reverse($rule, true);
            }
            unset($rule);
            $retry['parameters']['contributions'] = array_reverse($retry['parameters']['contributions'], true);
            $retry['parameters']['tax'] = array_reverse($retry['parameters']['tax'], true);
            if ($service->save($request, $retry)->id !== $draft->id) {
                throw new RuntimeException('Equivalent creation retry depended on associative key order.');
            }
            $data['record_version'] = 1;
            $data['parameters']['contributions']['gsis']['rate'] = '0.10';
            $data['change_reason'] = 'Fictional revised rate';
            $draft = $service->save($request, $data, $draft)->refresh();
            $events = PayrollRuleEvent::where('payroll_rule_version_id', $draft->id)->orderBy('id')->get();
            if ($events->count() !== 2 || $events[0]->snapshot['parameters']['contributions']['gsis']['rate'] !== '0.09') {
                throw new RuntimeException('Initial configuration snapshot was overwritten.');
            }
            foreach ($events as $event) {
                if ($event->snapshot['effective_from'] !== $date->format('Y-m-d')) {
                    throw new RuntimeException('A calendar effective date was stored as an instant.');
                }
            }
            // Reproduce the original fingerprint/UTC snapshot in our own fixture
            // only. MySQL JSON may reorder object keys; immutable history must
            // still identify the original creation payload after draft edits.
            $attributes = [
                'name' => $original['name'], 'personnel_classification' => $original['personnel_classification'],
                'is_demonstration' => $demo, 'effective_from' => $original['effective_from'],
                'parameters' => $service->parameters($original),
            ];
            $legacyHash = hash('sha256', json_encode([$attributes, $original['change_reason']], JSON_THROW_ON_ERROR));
            $legacySnapshot = $events[0]->snapshot;
            $legacySnapshot['effective_from'] = $date->toJSON();
            DB::table('payroll_rule_versions')->where('id', $draft->id)->update(['creation_hash' => $legacyHash]);
            DB::table('payroll_rule_events')->where('id', $events[0]->id)->update(['snapshot' => json_encode($legacySnapshot, JSON_THROW_ON_ERROR)]);
            $eventBefore = $events[0]->fresh()->getAttributes();
            $draftBefore = $draft->fresh()->getAttributes();
            if ($service->save($request, $retry)->id !== $draft->id
                || $events[0]->fresh()->getAttributes() !== $eventBefore
                || $events[0]->fresh()->effectiveDate() !== $date->format('Y-m-d')
                || $draft->fresh()->getAttributes() !== $draftBefore) {
                throw new RuntimeException('Legacy creation retry changed the edited draft or immutable history.');
            }
            $changedRetry = $retry;
            $changedRetry['parameters']['contributions']['gsis']['rate'] = '0.10';
            try {
                $service->save($request, $changedRetry);
                throw new RuntimeException('A changed payload reused the original creation key.');
            } catch (ValidationException $exception) {
                if (! isset($exception->errors()['submission_key'])) {
                    throw $exception;
                }
            }
            $approval = [
                'record_version' => 2, 'approval_reference' => 'FICTIONAL APPROVAL',
                'validation_reference' => 'FICTIONAL EXPECTED VECTORS', 'activation_reason' => 'Fictional activation',
                'authorization_confirmed' => 1,
            ];
            if (! $demo) {
                try {
                    $service->activate($request, $draft, $approval);
                    throw new RuntimeException('External configuration activated without client validation confirmation.');
                } catch (ValidationException $exception) {
                    if (! isset($exception->errors()['client_validation_confirmed'])) {
                        throw $exception;
                    }
                }
                $approval['client_validation_confirmed'] = 1;
            }
            $active = $service->activate($request, $draft, $approval)->refresh();
            if ($service->activate($request, $draft, $approval)->id !== $active->id
                || PayrollRuleEvent::where('payroll_rule_version_id', $active->id)->count() !== 3
                || PayrollRuleEvent::where('payroll_rule_version_id', $active->id)->where('action', 'activated')->sole()->snapshot['effective_from'] !== $date->format('Y-m-d')
                || $service->applicable($category->value, $date->subDay()->format('Y-m-d'), $demo) !== null) {
                throw new RuntimeException('Activation retry, event count or effective boundary failed.');
            }
            $profile = $service->profile($category->value, $date->format('Y-m-d'), $demo);
            if ($profile['_configuration']['version_id'] !== $active->id || $profile['contributions']['gsis']['rate'] !== '0.10') {
                throw new RuntimeException('MySQL selected the wrong activated category/data-use configuration.');
            }
            $calculator = app(StatutoryCalculator::class);
            $input = $calculator->normalize([
                'record_version' => 1, 'base_revision' => 0, 'reference' => 'FICTIONAL BASIS', 'reason' => 'Fictional reviewed scenario', 'demonstration_confirmed' => 1,
                'gsis_coverage' => 'applicable', 'gsis_basis' => '30000.00',
                'pagibig_coverage' => 'applicable', 'pagibig_basis' => '30000.00',
                'philhealth_coverage' => 'applicable', 'philhealth_basis' => '30000.00',
                'tax_method' => 'monthly_regular', 'taxable_regular' => '26350.00', 'taxable_other' => '0.00', 'qualifying_bonuses' => '0.00', 'prior_withheld' => '0.00',
            ]);
            $result = $calculator->calculate(['totals' => ['total_earnings' => '30000.00', 'total_deductions' => '0.00', 'bonuses' => '0.00', 'earning_adjustments' => '0.00']], $input, $profile, $category->value, $date->format('Y-m'));
            if ($result['net_before_tax_refund'] !== '25222.45' || $result['production_ready'] !== false) {
                throw new RuntimeException('Selected parameters did not produce the prepared fictional amount or retained production readiness.');
            }
        }
    }
    $old = PayrollRuleVersion::where('created_by', $actor->id)->where('personnel_classification', 'permanent')->where('is_demonstration', true)->firstOrFail();
    $before = $old->getAttributes();
    $data['submission_key'] = (string) Str::uuid();
    $data['record_version'] = 0;
    $data['personnel_classification'] = 'permanent';
    $data['is_demonstration'] = 1;
    $data['effective_from'] = $date->addMonth()->format('Y-m-d');
    $later = $service->save($request, $data);
    $approval['record_version'] = 1;
    $service->activate($request, $later, $approval);
    if ($service->applicable('permanent', $date->format('Y-m-d'), true)->id !== $old->id
        || $service->applicable('permanent', $date->addMonth()->format('Y-m-d'), true)->id !== $later->id
        || $old->fresh()->getAttributes() !== $before) {
        throw new RuntimeException('Later activation changed the old version or historical selection.');
    }
    $data['submission_key'] = (string) Str::uuid();
    $duplicate = $service->save($request, $data);
    try {
        $service->activate($request, $duplicate, $approval);
        throw new RuntimeException('A second version activated on the same scope/date.');
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['effective_from'])) {
            throw $exception;
        }
    }
    $viewer = User::factory()->create(['username' => 'configuration-viewer-'.$nonce, 'role' => Role::Viewer]);
    try {
        $service->save(configurationCheckRequest($viewer), $data);
        throw new RuntimeException('Viewer modified configuration.');
    } catch (AuthorizationException) {
        // Expected current-actor authorization failure.
    }
} finally {
    $connection->rollBack();
}
if (User::where('username', 'configuration-check-'.$nonce)->exists()
    || User::where('username', 'configuration-viewer-'.$nonce)->exists()
    || PayrollRuleVersion::where('created_by', $actor->id)->exists()
    || PayrollRuleEvent::where('actor_id', $actor->id)->exists()) {
    throw new RuntimeException('Configuration smoke fixtures were retained.');
}
echo "MySQL payroll configuration passed: six explicit category/data-use scopes, draft revisions and snapshots, canonical and legacy creation retries, unchanged legacy history, activation retries, authorization, external validation confirmation, calendar effective dates, preserved versions, duplicate prevention, prepared preview arithmetic, production blocking and rollback.\n";
