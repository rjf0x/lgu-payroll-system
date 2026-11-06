<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CompensationProfileController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmploymentRecordController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SalaryGradeStepController;
use App\Http\Controllers\SalaryScheduleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {

    Route::get('/login', [
        AuthenticatedSessionController::class,
        'create',
    ])->name('login');

    Route::post('/login', [
        AuthenticatedSessionController::class,
        'store',
    ])->middleware('throttle:login')->name('login.store');

});

Route::middleware('auth')->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::post('/logout', [
        AuthenticatedSessionController::class,
        'destroy',
    ])->name('logout');

    // Master data: reads require employees.view, writes require the listed permission.
    $masterData = [
        ['departments', DepartmentController::class, 'employees.manage'],
        ['positions', PositionController::class, 'employees.manage'],
        ['employees', EmployeeController::class, 'employees.manage'],
        ['employment-records', EmploymentRecordController::class, 'employees.manage'],
        ['salary-schedules', SalaryScheduleController::class, 'compensation.manage'],
        ['salary-grade-steps', SalaryGradeStepController::class, 'compensation.manage'],
        ['compensation-profiles', CompensationProfileController::class, 'compensation.manage'],
    ];

    foreach ($masterData as [$uri, $controller, $writePermission]) {
        Route::resource($uri, $controller)
            ->middlewareFor(['index', 'show'], 'can:employees.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], "can:{$writePermission}");
    }

    Route::resource('users', UserController::class)->middleware('can:users.manage');

});
