<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Controllers\EmployeeController;
use App\Http\Requests\SaveEmployeeRequest;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('This employee check requires the configured MySQL database.');
}

function employeeRequest(User $actor, array $data, ?Employee $employee = null): SaveEmployeeRequest
{
    $request = SaveEmployeeRequest::create($employee ? '/employees/'.$employee->id : '/employees', $employee ? 'PUT' : 'POST', $data);
    $request->setContainer(app())->setRedirector(app('redirect'))->setUserResolver(fn () => $actor);
    $session = new Store('employee-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    $route = new Route($request->method(), $employee ? '/employees/{employee}' : '/employees', fn () => null);
    $route->bind($request);
    if ($employee) {
        $route->setParameter('employee', $employee);
    }
    $request->setRouteResolver(fn () => $route);
    $request->validateResolved();

    return $request;
}

$numbers = [];
$username = 'employee-check-'.bin2hex(random_bytes(12));
$connection->beginTransaction();
try {
    $officer = User::factory()->create(['username' => $username, 'role' => Role::PayrollOfficer]);
    Auth::setUser($officer);
    $controller = app(EmployeeController::class);
    foreach (PersonnelClassification::cases() as $classification) {
        $number = 'CHECK-'.strtoupper(bin2hex(random_bytes(12)));
        $numbers[] = $number;
        $data = [
            'employee_number' => strtolower($number), 'first_name' => 'Fictional Maria',
            'middle_name' => null, 'last_name' => 'Employee Check', 'suffix' => null,
            'position' => 'Demonstration Clerk', 'office' => 'Fictional %_! Office',
            'personnel_classification' => $classification->value, 'employment_start_date' => '2024-02-29', 'is_active' => 1,
        ];
        $controller->store(employeeRequest($officer, $data));
        $employee = Employee::where('employee_number', $number)->firstOrFail();
        if ($employee->personnel_classification !== $classification || $employee->record_version !== 1
            || $controller->show($employee)->getData()['employee']->id !== $employee->id) {
            throw new RuntimeException('MySQL employee creation/retrieval failed.');
        }
        $edit = employeeRequest($officer, array_replace($data, ['position' => 'Updated Demonstration Clerk', 'record_version' => 1, 'is_active' => 0]), $employee);
        $controller->update($edit, $employee);
        $employee->refresh();
        if ($employee->position !== 'Updated Demonstration Clerk' || $employee->record_version !== 2 || $employee->is_active) {
            throw new RuntimeException('MySQL employee edit failed.');
        }
        try {
            $controller->update($edit, $employee);
            throw new RuntimeException('A stale MySQL employee edit was accepted.');
        } catch (ValidationException $exception) {
            if (! isset($exception->errors()['record_version'])) {
                throw $exception;
            }
        }
        $results = $controller->index(Request::create('/employees', 'GET', ['q' => strtolower($number).' %_!']))->getData()['employees'];
        if ($results->pluck('id')->all() !== [$employee->id]) {
            throw new RuntimeException('MySQL literal employee search failed.');
        }
        foreach (Role::cases() as $role) {
            $roleUser = (new User)->forceFill(['role' => $role, 'is_active' => true]);
            if (Gate::forUser($roleUser)->allows('view', $employee) !== ($role !== Role::Administrator)
                || Gate::forUser($roleUser)->allows('update', $employee) !== ($role === Role::PayrollOfficer)) {
                throw new RuntimeException('MySQL employee authorization matrix failed.');
            }
        }
    }
} finally {
    Auth::forgetGuards();
    $connection->rollBack();
}
if (Employee::whereIn('employee_number', $numbers)->exists() || User::where('username', $username)->exists()) {
    throw new RuntimeException('Employee smoke check rollback failed.');
}
echo "MySQL employee create/read/edit, three classifications, literal search, role policy, stale-edit rejection and rollback passed; no fictional records retained.\n";
