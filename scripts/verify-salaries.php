<?php

use App\Enums\PersonnelClassification;
use App\Enums\Role;
use App\Http\Requests\SaveSalaryRecordRequest;
use App\Http\Requests\SaveSalaryScheduleRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryRecord;
use App\Models\SalarySchedule;
use App\Models\User;
use App\Services\SalaryService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../application/vendor/autoload.php';
$app = require __DIR__.'/../application/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$connection = DB::connection();
if ($connection->getDriverName() !== 'mysql') {
    throw new RuntimeException('This salary check requires the configured MySQL database.');
}

function salaryCheckRequest(User $actor, string $class, array $data, ?Employee $employee = null): Request
{
    $request = $class::create('/', 'POST', $data);
    $request->setUserResolver(fn () => $actor);
    $session = new Store('salary-check', new ArraySessionHandler(120));
    $session->start();
    $session->put('auth_version', $actor->auth_version);
    $request->setLaravelSession($session);
    if ($request instanceof FormRequest) {
        $request->setContainer(app())->setRedirector(app('redirect'));
        $route = new Route('POST', '/', fn () => null);
        $route->bind($request);
        if ($employee) {
            $route->setParameter('employee', $employee);
        }
        $request->setRouteResolver(fn () => $route);
        $request->validateResolved();
    }

    return $request;
}

$nonce = strtoupper(bin2hex(random_bytes(12)));
$connection->beginTransaction();
try {
    $admin = User::factory()->create(['username' => 'salary-admin-'.$nonce, 'role' => Role::Administrator]);
    $officer = User::factory()->create(['username' => 'salary-officer-'.$nonce, 'role' => Role::PayrollOfficer]);
    $service = app(SalaryService::class);
    $base = CarbonImmutable::create(random_int(8000, 8998), random_int(1, 12), 1);
    while (SalarySchedule::whereIn('effective_from', [$base->format('Y-m-d'), $base->addYear()->format('Y-m-d')])->exists()) {
        $base = $base->addYears(2);
        if ($base->year > 9990) {
            throw new RuntimeException('No isolated future dates available for salary smoke check.');
        }
    }
    foreach (PersonnelClassification::cases() as $classification) {
        $employee = Employee::factory()->create(['employee_number' => 'SAL-'.$nonce.'-'.$classification->value, 'personnel_classification' => $classification]);
        $schedules = [];
        foreach ([$base, $base->addYear()] as $index => $date) {
            $data = ['name' => 'FICTIONAL SALARY CHECK '.$nonce, 'personnel_classification' => $classification->value, 'effective_from' => $date->format('Y-m-d'), 'is_demonstration' => 1];
            $request = salaryCheckRequest($admin, SaveSalaryScheduleRequest::class, $data);
            $schedule = $service->saveSchedule($request, $request->validated());
            foreach ([[1, 1, '12345.67'], [1, 2, $index === 0 ? '12400.08' : '15000.31'], [2, 1, '16000.09']] as [$grade, $step, $amount]) {
                $service->saveRate(salaryCheckRequest($admin, Request::class, []), $schedule, ['grade' => $grade, 'step' => $step, 'monthly_salary' => $amount]);
            }
            $service->publish(salaryCheckRequest($admin, Request::class, []), $schedule, 'FICTIONAL AUTHORITY - TEST ONLY');
            $schedules[] = $schedule->refresh();
        }
        $records = [];
        foreach ([
            [$base, 'initial', $schedules[0], 1, 1, '12345.67'],
            [$base->addDay(), 'step_increment', $schedules[0], 1, 2, '12400.08'],
            [$base->addYear(), 'schedule_update', $schedules[1], 1, 2, '15000.31'],
            [$base->addYear()->addDay(), 'promotion', $schedules[1], 2, 1, '16000.09'],
        ] as [$date, $type, $schedule, $grade, $step, $expected]) {
            $data = [
                'salary_rate_id' => $schedule->rates()->where('grade', $grade)->where('step', $step)->value('id'),
                'effective_from' => $date->format('Y-m-d'), 'change_type' => $type, 'position' => 'Fictional Salary Check Position',
                'step_basis_date' => $date->format('Y-m-d'), 'authority_reference' => 'FICTIONAL APPOINTMENT - TEST ONLY',
                'reason' => 'Automated rollback-only salary test', 'authorization_confirmed' => 1,
            ];
            $request = salaryCheckRequest($officer, SaveSalaryRecordRequest::class, $data, $employee);
            $record = $service->recordSalary($request, $employee, $request->validated())->refresh();
            if ($service->salaryAsOf($employee, $date->format('Y-m-d'))?->monthly_salary !== $expected || ! $record->is_demonstration) {
                throw new RuntimeException('MySQL salary lookup or demonstration status failed.');
            }
            $records[$record->id] = $record->getAttributes();
        }
        foreach ($records as $id => $original) {
            if (EmployeeSalaryRecord::findOrFail($id)->getAttributes() !== $original) {
                throw new RuntimeException('MySQL historical salary record changed.');
            }
        }
        if ($service->applicableSchedule($classification, $base->subDay()->format('Y-m-d'), true)?->id === $schedules[0]->id
            || $service->applicableSchedule($classification, $base->addYear()->format('Y-m-d'), true)?->id !== $schedules[1]->id) {
            throw new RuntimeException('MySQL effective schedule resolution failed.');
        }
        try {
            $schedules[0]->name = 'Tamper';
            $schedules[0]->save();
            throw new RuntimeException('Published MySQL schedule was editable.');
        } catch (LogicException) {
            // Expected protection; no write was issued.
        }
    }
} finally {
    $connection->rollBack();
}
if (SalarySchedule::where('name', 'like', '%'.$nonce.'%')->exists() || Employee::where('employee_number', 'like', 'SAL-'.$nonce.'%')->exists()
    || User::whereIn('username', ['salary-admin-'.$nonce, 'salary-officer-'.$nonce])->exists()) {
    throw new RuntimeException('Salary smoke check rollback failed.');
}
echo "MySQL effective schedules, exact decimal salaries, all three categories, initial/step/promotion/schedule changes, immutable history and rollback passed; no fictional records retained.\n";
