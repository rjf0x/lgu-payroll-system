<?php

use App\Enums\Role;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\SalaryScheduleController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureSalaryTextEncoding;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:manage-users');
    Route::resource('employees', EmployeeController::class)->except('destroy');
    Route::middleware(EnsureSalaryTextEncoding::class)->group(function () {
        Route::resource('salary-schedules', SalaryScheduleController::class)->parameters(['salary-schedules' => 'schedule'])->except('destroy');
        Route::post('salary-schedules/{schedule}/rates', [SalaryScheduleController::class, 'storeRate'])->name('salary-rates.store');
        Route::put('salary-schedules/{schedule}/rates/{rate}', [SalaryScheduleController::class, 'updateRate'])->name('salary-rates.update');
        Route::post('salary-schedules/{schedule}/publish', [SalaryScheduleController::class, 'publish'])->name('salary-schedules.publish');
        Route::get('employees/{employee}/salary-records', [EmployeeSalaryController::class, 'index'])->name('employee-salaries.index');
        Route::get('employees/{employee}/salary-records/create', [EmployeeSalaryController::class, 'create'])->name('employee-salaries.create');
        Route::post('employees/{employee}/salary-records', [EmployeeSalaryController::class, 'store'])->name('employee-salaries.store');
    });

    foreach (Role::cases() as $role) {
        Route::get('/'.$role->workspace(), fn () => view('workspace', ['role' => $role]))
            ->middleware('can:access-'.$role->workspace())
            ->name($role->workspace());
    }
});
