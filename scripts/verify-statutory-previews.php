<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollInputRevision;
use App\Models\SalarySchedule;
use App\Models\StatutoryPreview;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use App\Services\StatutoryPreviewService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Statutory persistence verification requires MySQL.');
}
$nonce = strtoupper(bin2hex(random_bytes(12)));
$connection->beginTransaction();
try {
    $actor = User::factory()->create(['role' => Role::PayrollOfficer, 'username' => 'statutory-check-'.$nonce]);
    $request = Request::create('/', 'POST');
    $request->setUserResolver(fn () => $actor);
    $session = new Store('statutory-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    // Isolate fictional sources from operator data, including any existing 2026 schedules.
    $date = CarbonImmutable::create(random_int(8000, 8998), 12, 1);
    while (PayrollBatch::whereYear('period_start', $date->year)->exists() || SalarySchedule::whereYear('effective_from', $date->year)->exists()) {
        $date = $date->addYear();
        if ($date->year > 9990) {
            throw new RuntimeException('No isolated statutory-check year is available.');
        }
    }
    // In-memory fixture override only; no rule file, real settings or activation is changed.
    config(['payroll_statutory.review_year' => $date->year]);
    $preparation = app(PayrollPreparationService::class);
    $inputs = app(PayrollInputService::class);
    $core = app(PayrollCalculationService::class);
    $service = app(StatutoryPreviewService::class);
    $batch = $preparation->create($request, ['period' => $date->format('Y-m'), 'is_demonstration' => 1]);
    foreach (PersonnelClassification::cases() as $category) {
        $employee = Employee::factory()->create(['employee_number' => 'STAT-'.$nonce.'-'.$category->value, 'personnel_classification' => $category]);
        $schedule = SalarySchedule::create(['name' => 'FICTIONAL STATUTORY CHECK '.$nonce, 'personnel_classification' => $category, 'is_demonstration' => 1, 'effective_from' => $date->format('Y-m-d'), 'created_by' => $actor->id]);
        $rate = $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '30000.00']);
        $schedule->authority_reference = 'FICTIONAL AUTHORITY';
        $schedule->published_at = now();
        $schedule->save();
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => $date->format('Y-m-d'),
            'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Statutory Clerk',
            'monthly_salary' => '30000.00', 'is_demonstration' => 1, 'step_basis_date' => $date->format('Y-m-d'),
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Rollback-only statutory check', 'created_by' => $actor->id,
        ]);
        $preparation->add($request, $batch, ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]]);
        $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
        $inputs->save($request, $batch, $entry, [
            'record_version' => $batch->fresh()->record_version, 'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUTS', 'change_reason' => 'Fictional zero attendance',
        ]);
    }
    $batch->refresh();
    $run = $core->calculate($request, $batch, $batch->record_version)->refresh();
    $beforeCore = $run->getAttributes();
    $beforeInputs = PayrollInputRevision::where('payroll_batch_id', $batch->id)->orderBy('id')->get()->map->getAttributes()->all();
    foreach ($run->items()->get() as $item) {
        $data = [
            'record_version' => $batch->record_version, 'base_revision' => 0,
            'gsis_coverage' => 'applicable', 'gsis_basis' => '30000.00',
            'pagibig_coverage' => 'applicable', 'pagibig_basis' => '30000.00',
            'philhealth_coverage' => 'applicable', 'philhealth_basis' => '30000.00',
            'tax_method' => 'monthly_regular', 'taxable_regular' => '26350.00',
            'taxable_other' => '0.00', 'qualifying_bonuses' => '0.00', 'prior_withheld' => '0.00',
            'reference' => 'FICTIONAL AUTHORITY', 'reason' => 'Fictional standard member scenario', 'demonstration_confirmed' => 1,
        ];
        $first = $service->save($request, $batch, $run, $item, $data)->refresh();
        $before = $first->getAttributes();
        if ($first->result['net_before_tax_refund'] !== '25522.45' || $first->result['production_ready'] !== false
            || $service->save($request, $batch, $run, $item, $data)->id !== $first->id) {
            throw new RuntimeException('MySQL prepared statutory totals, readiness or retry failed.');
        }
        $data = array_replace($data, ['base_revision' => 1, 'tax_method' => 'annual', 'taxable_regular' => '500000.00', 'qualifying_bonuses' => '135000.00', 'prior_withheld' => '50000.00']);
        $second = $service->save($request, $batch, $run, $item, $data)->refresh();
        if ($second->result['tax']['taxable_compensation'] !== '545000.00' || $second->result['tax']['withholding'] !== '1500.00'
            || $first->fresh()->getAttributes() !== $before) {
            throw new RuntimeException('MySQL bonus excess, prior withholding or history failed.');
        }
        $data = array_replace($data, ['base_revision' => 2, 'gsis_coverage' => 'unknown']);
        $third = $service->save($request, $batch, $run, $item, $data)->refresh();
        if ($third->result['net_before_tax_refund'] !== null || $third->result['contributions']['gsis']['amount'] !== null) {
            throw new RuntimeException('Missing applicability silently produced a total.');
        }
        $data = array_replace($data, [
            'base_revision' => 3, 'gsis_coverage' => 'applicable', 'tax_method' => 'cumulative',
            'taxable_regular' => '300000.02', 'qualifying_bonuses' => '0.00', 'prior_withheld' => '0.00', 'elapsed_periods' => 3,
        ]);
        $fourth = $service->save($request, $batch, $run, $item, $data)->refresh();
        if ($fourth->result['tax']['liability'] !== '50625.16'
            || $fourth->result['net_before_tax_refund'] !== '-24275.16'
            || $service->save($request, $batch, $run, $item, $data)->id !== $fourth->id
            || $first->fresh()->getAttributes() !== $before) {
            throw new RuntimeException('Exact cumulative half-cent calculation, retry or history preservation failed.');
        }
        $profile = config('payroll_statutory');
        config(['payroll_statutory.contributions.pagibig.ceiling' => '0.00']);
        $data['base_revision'] = 4;
        $fifth = $service->save($request, $batch, $run, $item, $data)->refresh();
        if ($fifth->result['status'] !== 'incomplete' || $fifth->result['contributions']['pagibig']['amount'] !== null) {
            throw new RuntimeException('An invalid contribution ceiling produced a deduction.');
        }
        config(['payroll_statutory' => $profile]);
        config(['payroll_statutory.tax.monthly_brackets.1' => ['lower' => '20833.00', 'fixed' => '0.00', 'rate' => '0.15']]);
        $data['base_revision'] = 5;
        $sixth = $service->save($request, $batch, $run, $item, $data)->refresh();
        if ($sixth->result['status'] !== 'incomplete' || $sixth->result['tax']['withholding'] !== null) {
            throw new RuntimeException('Malformed tax brackets did not produce an incomplete review.');
        }
        config(['payroll_statutory' => $profile]);
    }
    if ($run->fresh()->getAttributes() !== $beforeCore
        || PayrollInputRevision::where('payroll_batch_id', $batch->id)->orderBy('id')->get()->map->getAttributes()->all() !== $beforeInputs) {
        throw new RuntimeException('Statutory review changed core payroll or manual inputs.');
    }
    $real = $preparation->create($request, ['period' => $date->format('Y-m'), 'is_demonstration' => 0]);
    // A genuine real batch cannot acquire a core run through either engine.
    try {
        $core->calculate($request, $real, $real->record_version);
        throw new RuntimeException('Real payroll was not blocked.');
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['batch'])) {
            throw $exception;
        }
    }
} finally {
    $connection->rollBack();
}
if (User::where('username', 'statutory-check-'.$nonce)->exists()
    || Employee::where('employee_number', 'like', 'STAT-'.$nonce.'%')->exists()
    || StatutoryPreview::where('created_by', $actor->id)->exists()) {
    throw new RuntimeException('Statutory verification retained fictional fixtures.');
}
echo "MySQL statutory previews passed: three explicit fictional categories, prepared contributions/tax/net, exact cumulative half-cent rounding, malformed rule handling, bonus excess, retries, preserved rules/history/core inputs, unavailable amounts, real blocking and rollback.\n";
