<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Requests\AddPayrollEmployeesRequest;
use App\Http\Requests\CreatePayrollBatchRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\PayrollBatch;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\PayrollPreparationService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
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
    throw new RuntimeException('Payroll draft verification requires the configured MySQL database.');
}

function draftCheckRequest(User $actor, string $class, array $data, ?PayrollBatch $batch = null): FormRequest
{
    $request = $class::create('/', 'POST', $data);
    $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $actor);
    $session = new Store('draft-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    $route = new Route('POST', '/', fn () => null);
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
    $actor = User::factory()->create(['role' => Role::PayrollOfficer, 'email' => 'draft-check-'.$nonce.'@example.test']);
    $service = app(PayrollPreparationService::class);
    $date = CarbonImmutable::create(random_int(8000, 8998), random_int(1, 12), 1);
    while (PayrollBatch::whereDate('period_start', $date->format('Y-m-d'))->exists() || SalarySchedule::whereDate('effective_from', $date->format('Y-m-d'))->exists()) {
        $date = $date->addYear();
        if ($date->year > 9990) {
            throw new RuntimeException('No isolated date available for payroll verification.');
        }
    }
    foreach ([true, false] as $demo) {
        $data = ['period' => $date->format('Y-m'), 'is_demonstration' => (int) $demo];
        $request = draftCheckRequest($actor, CreatePayrollBatchRequest::class, $data);
        $batch = $service->create($request, $request->validated());
        $employeeIds = [];
        foreach (PersonnelClassification::cases() as $category) {
            $employee = Employee::factory()->create(['employee_number' => 'DRAFT-'.$nonce.'-'.(int) $demo.'-'.$category->value, 'personnel_classification' => $category]);
            $schedule = SalarySchedule::create(['name' => 'FICTIONAL DRAFT CHECK '.$nonce, 'personnel_classification' => $category, 'is_demonstration' => $demo, 'effective_from' => $date->format('Y-m-d'), 'created_by' => $actor->id]);
            $rate = $schedule->rates()->create(['grade' => 1, 'step' => 1, 'monthly_salary' => '12345.67']);
            $schedule->authority_reference = 'FICTIONAL AUTHORITY - TEST ONLY';
            $schedule->published_at = now();
            $schedule->save();
            EmployeeSalaryRecord::forceCreate([
                'employee_id' => $employee->id, 'salary_rate_id' => $rate->id, 'effective_from' => $date->format('Y-m-d'),
                'change_type' => 'initial', 'personnel_classification' => $category->value, 'position' => 'Fictional Draft Clerk',
                'monthly_salary' => '12345.67', 'is_demonstration' => $demo, 'step_basis_date' => $date->format('Y-m-d'),
                'authority_reference' => 'FICTIONAL APPOINTMENT - TEST ONLY', 'reason' => 'Rollback-only payroll verification', 'created_by' => $actor->id,
            ]);
            $employeeIds[] = $employee->id;
        }
        $request = draftCheckRequest($actor, AddPayrollEmployeesRequest::class, ['record_version' => 1, 'employee_ids' => $employeeIds], $batch);
        $service->add($request, $batch, $request->validated());
        $reopened = PayrollBatch::with('entries.employee')->findOrFail($batch->id);
        if ($reopened->status !== 'draft' || $reopened->entries->count() !== 3 || $reopened->record_version !== 2) {
            throw new RuntimeException('Saved draft could not be reopened with expected coverage/version.');
        }
        foreach ($reopened->entries as $entry) {
            if ($entry->monthly_salary !== '12345.67' || $service->entryIssue($entry, $reopened) !== null) {
                throw new RuntimeException('MySQL salary snapshot/source validation failed.');
            }
        }
        try {
            $service->create(draftCheckRequest($actor, CreatePayrollBatchRequest::class, $data), $data);
            throw new RuntimeException('Duplicate monthly batch was accepted.');
        } catch (ValidationException) {
            // Expected unique month/mode protection.
        }
        try {
            $service->add($request, $reopened, ['record_version' => 2, 'employee_ids' => $employeeIds]);
            throw new RuntimeException('Duplicate employee coverage was accepted.');
        } catch (ValidationException) {
            if ($reopened->entries()->count() !== 3) {
                throw new RuntimeException('Failed submission changed existing coverage.');
            }
        }
    }
} finally {
    $connection->rollBack();
}
if (User::where('email', 'draft-check-'.$nonce.'@example.test')->exists()
    || Employee::where('employee_number', 'like', 'DRAFT-'.$nonce.'%')->exists()
    || SalarySchedule::where('name', 'FICTIONAL DRAFT CHECK '.$nonce)->exists()) {
    throw new RuntimeException('Payroll verification fixtures were retained.');
}
echo "MySQL draft create/reopen, all categories, both data-use modes, exact salary snapshots, duplicate protection and rollback passed.\n";
