<?php

use App\Http\Controllers\AttendanceAdjustmentController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CompensationProfileController;
use App\Http\Controllers\ContributionRateController;
use App\Http\Controllers\DeductionTypeController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EarningTypeController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeDeductionAssignmentController;
use App\Http\Controllers\EmployeeEarningAssignmentController;
use App\Http\Controllers\EmploymentRecordController;
use App\Http\Controllers\LeaveApplicationController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveTransactionController;
use App\Http\Controllers\LeaveTypeController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\SalaryGradeStepController;
use App\Http\Controllers\SalaryScheduleController;
use App\Http\Controllers\TaxBracketController;
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

    // Earnings, deductions, and statutory configuration (no show screens).
    $configResources = [
        ['earning-types', EarningTypeController::class],
        ['employee-earning-assignments', EmployeeEarningAssignmentController::class],
        ['deduction-types', DeductionTypeController::class],
        ['employee-deduction-assignments', EmployeeDeductionAssignmentController::class],
        ['tax-brackets', TaxBracketController::class],
        ['contribution-rates', ContributionRateController::class],
    ];

    foreach ($configResources as [$uri, $controller]) {
        Route::resource($uri, $controller)
            ->except('show')
            ->middlewareFor('index', 'can:employees.view')
            ->middlewareFor(['create', 'store', 'edit', 'update', 'destroy'], 'can:compensation.manage');
    }

    Route::resource('users', UserController::class)->middleware('can:users.manage');

    // Attendance management.
    Route::middleware('can:attendance.manage')->group(function () {
        Route::resource('attendance-records', AttendanceRecordController::class)->except('show');

        Route::resource('attendance-adjustments', AttendanceAdjustmentController::class)
            ->except(['edit', 'update', 'destroy']);
        Route::post('attendance-adjustments/{attendance_adjustment}/approve', [
            AttendanceAdjustmentController::class, 'approve',
        ])->name('attendance-adjustments.approve');
        Route::post('attendance-adjustments/{attendance_adjustment}/reject', [
            AttendanceAdjustmentController::class, 'reject',
        ])->name('attendance-adjustments.reject');
    });

    // Leave management.
    Route::middleware('can:leave.manage')->group(function () {
        Route::resource('leave-types', LeaveTypeController::class)->except('show');
        Route::resource('leave-balances', LeaveBalanceController::class)->except(['show', 'destroy']);

        Route::resource('leave-applications', LeaveApplicationController::class)->except(['edit', 'update']);
        Route::post('leave-applications/{leave_application}/approve', [
            LeaveApplicationController::class, 'approve',
        ])->name('leave-applications.approve');
        Route::post('leave-applications/{leave_application}/reject', [
            LeaveApplicationController::class, 'reject',
        ])->name('leave-applications.reject');

        Route::resource('leave-transactions', LeaveTransactionController::class)->only('index');
    });

});
