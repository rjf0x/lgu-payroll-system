<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Requests\AddPayrollEmployeesRequest;
use App\Http\Requests\CreatePayrollBatchRequest;
use App\Http\Requests\SavePayrollInputsRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Payroll input verification requires MySQL.');
}

function inputCheckRequest(User $actor, string $class, array $data, ?PayrollBatch $batch = null): FormRequest
{
    $request = $class::create('/', 'PUT', $data);
    $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $actor);
    $session = new Store('input-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    $route = new Route('PUT', '/', fn () => null);
    $route->bind($request);
    if ($batch) {
        $route->setParameter('batch', $batch);
    }
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();

    return $request;
}

$nonce = strtoupper(bin2hex(random_bytes(12)));
$connection->beginTransaction();
try {
    $actor = User::factory()->create(['role' => Role::PayrollOfficer, 'email' => 'input-check-'.$nonce.'@example.test']);
    $preparation = app(PayrollPreparationService::class);
    $inputs = app(PayrollInputService::class);
    $date = CarbonImmutable::create(random_int(8000, 8998), random_int(1, 12), 1);
    while (PayrollBatch::whereDate('period_start', $date->format('Y-m-d'))->exists() || SalarySchedule::whereDate('effective_from', $date->format('Y-m-d'))->exists()) {
        $date = $date->addYear();
        if ($date->year > 9990) {
            throw new RuntimeException('No isolated date available for input check.');
        }
    }
    foreach ([true, false] as $demo) {
        $data = ['period' => $date->format('Y-m'), 'is_demonstration' => (int) $demo];
        $request = inputCheckRequest($actor, CreatePayrollBatchRequest::class, $data);
        $batch = $preparation->create($request, $request->validated());
        foreach (PersonnelClassification::cases() as $index => $category) {
            $employee = Employee::factory()->create(['employee_number' => 'INPUT-'.$nonce.'-'.(int) $demo.'-'.$category->value, 'personnel_classification' => $category]);
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL INPUT CHECK '.$nonce, 'personnel_classification' => $category, 'is_demonstration' => $demo, 'effective_from' => $date->format('Y-m-d'), 'created_by' => $actor->id]);
            $rate = $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '12345.67']);
            $schedule->authority_reference = 'FICTIONAL AUTHORITY ONLY';
            $schedule->published_at = now();
            $schedule->save();
            EmployeeSalaryRecord::forceCreate([
                'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => $date->format('Y-m-d'),
                'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Input Clerk',
                'monthly_salary' => '12345.67', 'is_demonstration' => $demo, 'step_basis_date' => $date->format('Y-m-d'),
                'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Rollback-only input test', 'created_by' => $actor->id,
            ]);
            $add = ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]];
            $request = inputCheckRequest($actor, AddPayrollEmployeesRequest::class, $add, $batch);
            $preparation->add($request, $batch, $request->validated());
            $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
            $expected = (100 + $index).'.07';
            $loan = (200 + $index).'.02';
            $data = [
                'record_version' => $batch->fresh()->record_version,
                'allowances' => [['label' => 'Fictional PERA', 'amount' => $expected, 'is_applicable' => 1], ['label' => 'Fictional excluded hazard', 'amount' => '999.99', 'is_applicable' => 0]],
                'bonuses' => [['label' => 'Fictional bonus', 'amount' => '10.03']],
                'loans' => [['label' => 'Fictional GSIS loan', 'amount' => $loan]],
                'adjustments' => [['label' => 'Fictional correction', 'direction' => 'deduction', 'original_amount' => '3.04', 'adjusted_amount' => '1.05', 'reason' => 'Fictional external authority']],
                'attendance' => ['absence_days' => '1.25', 'undertime_minutes' => 15, 'notes' => 'Fictional attendance'],
                'authority_reference' => 'FICTIONAL INPUT AUTHORITY', 'change_reason' => 'Fictional original', 'authorization_confirmed' => 1,
            ];
            $request = inputCheckRequest($actor, SavePayrollInputsRequest::class, $data, $batch);
            $first = $inputs->save($request, $batch, $entry, $request->validated())->refresh();
            $before = $first->getAttributes();
            $data['record_version'] = $batch->fresh()->record_version;
            $data['change_reason'] = 'Fictional revision';
            $data['adjustments'][0]['adjusted_amount'] = '2.06';
            $request = inputCheckRequest($actor, SavePayrollInputsRequest::class, $data, $batch);
            $second = $inputs->save($request, $batch, $entry, $request->validated())->refresh();
            if ($first->fresh()->getAttributes() !== $before || $second->revision !== 2 || $second->allowanceTotal() !== $expected
                || count($second->applicableAllowances()) !== 1 || $second->payload['loans'][0]['amount'] !== $loan
                || $second->payload['adjustments'][0]['original_amount'] !== '3.04'
                || $second->payload['adjustments'][0]['adjusted_amount'] !== '2.06'
                || $inputs->contextChanged($second, $entry)) {
                throw new RuntimeException('MySQL input persistence, exclusions, decimals or revision preservation failed.');
            }
            $preparation->remove($request, $batch, $entry, $batch->fresh()->record_version);
            $add = ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]];
            $request = inputCheckRequest($actor, AddPayrollEmployeesRequest::class, $add, $batch);
            $preparation->add($request, $batch, $request->validated());
            $replacement = $batch->entries()->where('employee_id', $employee->id)->sole();
            if ($inputs->revisions($batch, $replacement)->count() !== 2 || $inputs->contextChanged($second, $replacement)) {
                throw new RuntimeException('Input history was lost or mismatched after coverage refresh.');
            }
        }
    }
} finally {
    $connection->rollBack();
}
if (User::where('email', 'input-check-'.$nonce.'@example.test')->exists()
    || Employee::where('employee_number', 'like', 'INPUT-'.$nonce.'%')->exists()
    || SalarySchedule::where('name', 'FICTIONAL INPUT CHECK '.$nonce)->exists()) {
    throw new RuntimeException('Input check retained fictional fixtures.');
}
echo "MySQL inputs passed: all categories/modes, per-employee values, excluded allowances, exact amounts, edited revisions, original values, coverage refresh and rollback.\n";
