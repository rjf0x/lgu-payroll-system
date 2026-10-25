<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Requests\AddPayrollEmployeesRequest;
use App\Http\Requests\CreatePayrollBatchRequest;
use App\Http\Requests\SavePayrollInputsRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\PayrollCalculationRun;
use App\Models\PayrollInputRevision;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollCalculationService;
use App\Services\PayrollInputService;
use App\Services\PayrollPreparationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('Calculation verification requires MySQL.');
}

function calculationCheckRequest(User $actor, string $class = Request::class, array $data = [], ?PayrollBatch $batch = null): Request
{
    $request = $class::create('/', 'POST', $data);
    $request->setUserResolver(fn () => $actor);
    $session = new Store('calculation-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    if ($request instanceof FormRequest) {
        $request->setContainer(app())->setRedirector(app('redirect'));
        $route = new Route('POST', '/', fn () => null);
        $route->bind($request);
        if ($batch) {
            $route->setParameter('batch', $batch);
        }
        $request->setRouteResolver(fn () => $route);
        $request->validateResolved();
    }

    return $request;
}

$nonce = strtoupper(bin2hex(random_bytes(12)));
$connection->beginTransaction();
try {
    $actor = User::factory()->create(['role' => Role::PayrollOfficer, 'username' => 'calculation-check-'.$nonce]);
    $preparation = app(PayrollPreparationService::class);
    $inputs = app(PayrollInputService::class);
    $calculator = app(PayrollCalculationService::class);
    $date = CarbonImmutable::create(random_int(8000, 8998), random_int(1, 12), 1);
    while (PayrollBatch::whereDate('period_start', $date->format('Y-m-d'))->exists() || SalarySchedule::whereDate('effective_from', $date->format('Y-m-d'))->exists()) {
        $date = $date->addYear();
        if ($date->year > 9990) {
            throw new RuntimeException('No isolated period available for calculation check.');
        }
    }
    $data = ['period' => $date->format('Y-m'), 'is_demonstration' => 1];
    $request = calculationCheckRequest($actor, CreatePayrollBatchRequest::class, $data);
    $batch = $preparation->create($request, $request->validated());
    foreach (PersonnelClassification::cases() as $category) {
        $employee = Employee::factory()->create(['employee_number' => 'CALC-'.$nonce.'-'.$category->value, 'personnel_classification' => $category]);
        $schedule = SalarySchedule::create(['name' => 'FICTIONAL CALC CHECK '.$nonce, 'personnel_classification' => $category, 'is_demonstration' => 1, 'effective_from' => $date->format('Y-m-d'), 'created_by' => $actor->id]);
        $rate = $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '12345.67']);
        $schedule->authority_reference = 'FICTIONAL AUTHORITY';
        $schedule->published_at = now();
        $schedule->save();
        EmployeeSalaryRecord::forceCreate([
            'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => $date->format('Y-m-d'),
            'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Calculation Clerk',
            'monthly_salary' => '12345.67', 'is_demonstration' => 1, 'step_basis_date' => $date->format('Y-m-d'),
            'authority_reference' => 'FICTIONAL APPOINTMENT', 'reason' => 'Rollback-only calculation test', 'created_by' => $actor->id,
        ]);
        $data = ['record_version' => $batch->fresh()->record_version, 'employee_ids' => [$employee->id]];
        $request = calculationCheckRequest($actor, AddPayrollEmployeesRequest::class, $data, $batch);
        $preparation->add($request, $batch, $request->validated());
        $entry = $batch->entries()->where('employee_id', $employee->id)->sole();
        $data = [
            'record_version' => $batch->fresh()->record_version,
            'allowances' => [['label' => 'Fictional applicable', 'amount' => '100.07', 'is_applicable' => 1], ['label' => 'Fictional excluded', 'amount' => '999.99', 'is_applicable' => 0]],
            'bonuses' => [['label' => 'Fictional bonus', 'amount' => '500.01']],
            'loans' => [['label' => 'Fictional loan', 'amount' => '200.02']],
            'adjustments' => [
                ['label' => 'Fictional deduction', 'direction' => 'deduction', 'original_amount' => '300.03', 'adjusted_amount' => '111.11', 'reason' => 'Fictional authority'],
                ['label' => 'Fictional earning', 'direction' => 'earning', 'original_amount' => '999.99', 'adjusted_amount' => '50.09', 'reason' => 'Fictional authority'],
            ],
            'attendance' => ['absence_days' => '0.00', 'undertime_minutes' => 0],
            'authority_reference' => 'FICTIONAL INPUT AUTHORITY', 'change_reason' => 'Fictional core case', 'authorization_confirmed' => 1,
        ];
        $request = calculationCheckRequest($actor, SavePayrollInputsRequest::class, $data, $batch);
        $inputs->save($request, $batch, $entry, $request->validated());
    }
    $beforeInputs = PayrollInputRevision::where('payroll_batch_id', $batch->id)->orderBy('id')->get()->map->getAttributes()->all();
    $request = calculationCheckRequest($actor);
    $version = $batch->fresh()->record_version;
    $run = $calculator->calculate($request, $batch, $version)->refresh();
    $beforeRun = $run->getAttributes();
    if ($run->totals['net_pay'] !== '38054.13' || $run->totals['total_earnings'] !== '38987.52'
        || $run->totals['total_deductions'] !== '933.39' || $run->items()->count() !== 3 || $run->status !== 'provisional') {
        throw new RuntimeException('MySQL aggregate calculation did not match the prepared three-employee case.');
    }
    foreach ($run->items()->get() as $item) {
        if ($item->breakdown['totals']['net_pay'] !== '12684.71' || $calculator->itemIssue($item, $batch) !== null
            || $item->breakdown['statutory_deductions'] !== null) {
            throw new RuntimeException('MySQL item values/source/null statutory state failed.');
        }
    }
    if ($calculator->calculate($request, $batch, $version)->id !== $run->id
        || PayrollCalculationRun::where('payroll_batch_id', $batch->id)->count() !== 1
        || PayrollInputRevision::where('payroll_batch_id', $batch->id)->orderBy('id')->get()->map->getAttributes()->all() !== $beforeInputs) {
        throw new RuntimeException('Duplicate calculation or input preservation failed.');
    }
    $firstEntry = $batch->entries()->orderBy('id')->firstOrFail();
    $data['record_version'] = $version;
    $data['loans'][0]['amount'] = '300.02';
    $inputRequest = calculationCheckRequest($actor, SavePayrollInputsRequest::class, $data, $batch);
    $inputs->save($inputRequest, $batch, $firstEntry, $inputRequest->validated());
    $newRun = $calculator->calculate($request, $batch, $batch->fresh()->record_version)->refresh();
    if ($newRun->totals['net_pay'] !== '37954.13' || $run->fresh()->getAttributes() !== $beforeRun) {
        throw new RuntimeException('Recalculation changed the original run or used old manual inputs.');
    }
    $realData = ['period' => $date->format('Y-m'), 'is_demonstration' => 0];
    $realRequest = calculationCheckRequest($actor, CreatePayrollBatchRequest::class, $realData);
    $real = $preparation->create($realRequest, $realRequest->validated());
    try {
        $calculator->calculate($request, $real, $real->record_version);
        throw new RuntimeException('Real-payroll calculation was not blocked.');
    } catch (ValidationException $exception) {
        if (! isset($exception->errors()['batch'])) {
            throw $exception;
        }
    }
} finally {
    $connection->rollBack();
}
if (User::where('username', 'calculation-check-'.$nonce)->exists()
    || Employee::where('employee_number', 'like', 'CALC-'.$nonce.'%')->exists()
    || PayrollCalculationRun::where('created_by', $actor->id)->exists()) {
    throw new RuntimeException('Calculation smoke fixtures were retained.');
}
echo "MySQL core calculations passed: three categories, prepared item/aggregate values, exclusions, exact replacements, no input overwrite, idempotency, new-run history, real-mode blocking and rollback.\n";
