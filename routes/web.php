<?php

use App\Http\Controllers\AttendanceAdjustmentController;
use App\Http\Controllers\AttendanceRecordController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\BlockchainAnchorController;
use App\Http\Controllers\CompensationProfileController;
use App\Http\Controllers\ContributionRateController;
use App\Http\Controllers\DashboardController;
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
use App\Http\Controllers\PayrollApprovalController;
use App\Http\Controllers\PayrollDetailController;
use App\Http\Controllers\PayrollPeriodController;
use App\Http\Controllers\PayrollRegisterController;
use App\Http\Controllers\PayrollRunController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SalaryGradeStepController;
use App\Http\Controllers\SalaryScheduleController;
use App\Http\Controllers\TaxBracketController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureAccountIsActive;
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

Route::middleware(['auth', EnsureAccountIsActive::class])->group(function () {

    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::middleware('can:audit.view')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
        Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');
    });

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

    // Payroll processing.
    Route::middleware('can:payroll.view')->group(function () {
        Route::get('payroll-periods', [PayrollPeriodController::class, 'index'])->name('payroll-periods.index');
        Route::get('payroll-runs', [PayrollRunController::class, 'index'])->name('payroll-runs.index');
        Route::get('payroll-runs/{payroll_run}', [PayrollRunController::class, 'show'])
            ->whereNumber('payroll_run')->name('payroll-runs.show');
    });

    Route::middleware('can:payroll.prepare')->group(function () {
        Route::get('payroll-periods/create', [PayrollPeriodController::class, 'create'])->name('payroll-periods.create');
        Route::post('payroll-periods', [PayrollPeriodController::class, 'store'])->name('payroll-periods.store');
        Route::get('payroll-periods/{payroll_period}/edit', [PayrollPeriodController::class, 'edit'])->name('payroll-periods.edit');
        Route::put('payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'update'])->name('payroll-periods.update');
        Route::delete('payroll-periods/{payroll_period}', [PayrollPeriodController::class, 'destroy'])->name('payroll-periods.destroy');

        Route::get('payroll-runs/create', [PayrollRunController::class, 'create'])->name('payroll-runs.create');
        Route::post('payroll-runs', [PayrollRunController::class, 'store'])->name('payroll-runs.store');
        Route::delete('payroll-runs/{payroll_run}', [PayrollRunController::class, 'destroy'])
            ->whereNumber('payroll_run')->name('payroll-runs.destroy');
        Route::post('payroll-runs/{payroll_run}/compute', [PayrollRunController::class, 'compute'])
            ->whereNumber('payroll_run')->name('payroll-runs.compute');

        Route::get('payroll-runs/{payroll_run}/details/{payroll_detail}/edit', [PayrollDetailController::class, 'edit'])
            ->scopeBindings()->name('payroll-details.edit');
        Route::put('payroll-runs/{payroll_run}/details/{payroll_detail}', [PayrollDetailController::class, 'update'])
            ->scopeBindings()->name('payroll-details.update');
    });

    // Payroll approval workflow (permission + separation of duties enforced in the service).
    Route::post('payroll-runs/{payroll_run}/submit', [PayrollApprovalController::class, 'submit'])
        ->whereNumber('payroll_run')->middleware('can:payroll.submit')->name('payroll-runs.submit');
    Route::post('payroll-runs/{payroll_run}/approve', [PayrollApprovalController::class, 'approve'])
        ->whereNumber('payroll_run')->middleware('can:payroll.approve')->name('payroll-runs.approve');
    Route::post('payroll-runs/{payroll_run}/reject', [PayrollApprovalController::class, 'reject'])
        ->whereNumber('payroll_run')->middleware('can:payroll.reject')->name('payroll-runs.reject');
    Route::post('payroll-runs/{payroll_run}/return', [PayrollApprovalController::class, 'returnForCorrection'])
        ->whereNumber('payroll_run')->middleware('can:payroll.review')->name('payroll-runs.return');
    Route::post('payroll-runs/{payroll_run}/finalize', [PayrollApprovalController::class, 'finalize'])
        ->whereNumber('payroll_run')->middleware('can:payroll.finalize')->name('payroll-runs.finalize');

    // Register, payslips, and reports.
    Route::middleware('can:payroll.view')->group(function () {
        Route::get('payroll-runs/{payroll_run}/register', [PayrollRegisterController::class, 'show'])
            ->whereNumber('payroll_run')->name('payroll-runs.register');
        Route::get('payroll-runs/{payroll_run}/payslips', [PayslipController::class, 'index'])
            ->whereNumber('payroll_run')->name('payroll-runs.payslips');
        Route::get('payslips/{payslip}', [PayslipController::class, 'show'])->name('payslips.show');
        Route::get('payroll-runs/{payroll_run}/summary', [ReportController::class, 'summary'])
            ->whereNumber('payroll_run')->name('reports.payroll-summary');
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
    });

    Route::middleware('can:reports.generate')->group(function () {
        Route::get('payroll-runs/{payroll_run}/register/export', [PayrollRegisterController::class, 'export'])
            ->whereNumber('payroll_run')->name('payroll-runs.register.export');
        Route::post('payroll-runs/{payroll_run}/payslips/generate', [PayslipController::class, 'generate'])
            ->whereNumber('payroll_run')->name('payroll-runs.payslips.generate');
        Route::post('payslips/{payslip}/publish', [PayslipController::class, 'publish'])->name('payslips.publish');
    });

    // Blockchain anchoring & verification (only a hash and metadata leave the app).
    Route::middleware('can:blockchain.verify')->group(function () {
        Route::get('blockchain', [BlockchainAnchorController::class, 'index'])->name('blockchain.index');
        Route::post('payroll-runs/{payroll_run}/anchor', [BlockchainAnchorController::class, 'anchor'])
            ->whereNumber('payroll_run')->name('payroll-runs.anchor');
        Route::get('blockchain-anchors/{blockchain_anchor}', [BlockchainAnchorController::class, 'show'])
            ->name('blockchain-anchors.show');
        Route::post('blockchain-anchors/{blockchain_anchor}/verify', [BlockchainAnchorController::class, 'verify'])
            ->name('blockchain-anchors.verify');
    });

});
