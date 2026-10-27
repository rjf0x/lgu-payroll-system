<?php

use App\Enums\Role;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeSalaryController;
use App\Http\Controllers\PayrollAnchoringController;
use App\Http\Controllers\PayrollBatchController;
use App\Http\Controllers\PayrollCalculationController;
use App\Http\Controllers\PayrollDocumentController;
use App\Http\Controllers\PayrollInputController;
use App\Http\Controllers\PayrollReviewController;
use App\Http\Controllers\PayrollRuleVersionController;
use App\Http\Controllers\PayrollVerificationController;
use App\Http\Controllers\SalaryScheduleController;
use App\Http\Controllers\StatutoryPreviewController;
use App\Http\Controllers\UserController;
use App\Http\Middleware\EnsureSalaryTextEncoding;
use App\Http\Middleware\PreventConfigurationInputFlash;
use App\Http\Middleware\PreventStatutoryInputFlash;
use App\Http\Middleware\ScopePayrollInputErrors;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:20,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::middleware([PreventConfigurationInputFlash::class, EnsureSalaryTextEncoding::class])->group(function () {
        Route::resource('payroll-rule-versions', PayrollRuleVersionController::class)->parameters(['payroll-rule-versions' => 'version'])->except('destroy');
        Route::post('payroll-rule-versions/{version}/activate', [PayrollRuleVersionController::class, 'activate'])->name('payroll-rule-versions.activate');
    });
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::resource('users', UserController::class)->except(['show', 'destroy'])->middleware('can:manage-users');
    Route::resource('employees', EmployeeController::class)->except('destroy');
    Route::get('payroll-batches/{batch}/integrity/{finalization}', [PayrollVerificationController::class, 'show'])->name('payroll-verification.show');
    Route::post('payroll-batches/{batch}/integrity/{finalization}', [PayrollVerificationController::class, 'store'])->middleware('throttle:10,1')->name('payroll-verification.store');
    Route::get('payroll-batches/{batch}/anchoring/{finalization}', [PayrollAnchoringController::class, 'show'])->name('payroll-anchoring.show');
    Route::post('payroll-batches/{batch}/anchoring/{finalization}/{action}', [PayrollAnchoringController::class, 'act'])
        ->whereIn('action', ['prepare', 'submit'])->middleware('throttle:10,1')->name('payroll-anchoring.act');
    Route::get('payroll-batches/{batch}/documents', [PayrollDocumentController::class, 'index'])->name('payroll-documents.index');
    Route::prefix('payroll-batches/{batch}/documents/{finalization}')->group(function () {
        Route::get('register', [PayrollDocumentController::class, 'register'])->name('payroll-documents.register');
        Route::get('register.pdf', [PayrollDocumentController::class, 'registerPdf'])->name('payroll-documents.register-pdf');
        Route::get('register.csv', [PayrollDocumentController::class, 'registerCsv'])->name('payroll-documents.register-csv');
        Route::get('payslips/{employee}', [PayrollDocumentController::class, 'payslip'])->where('employee', '[1-9][0-9]{0,18}')->name('payroll-documents.payslip');
        Route::get('payslips/{employee}.pdf', [PayrollDocumentController::class, 'payslipPdf'])->where('employee', '[1-9][0-9]{0,18}')->name('payroll-documents.payslip-pdf');
    });
    Route::get('payroll-batches/{batch}/review', [PayrollReviewController::class, 'show'])->name('payroll-reviews.show');
    Route::post('payroll-batches/{batch}/workflow/{action}', [PayrollReviewController::class, 'transition'])
        ->whereIn('action', ['submit', 'return', 'approve', 'finalize'])
        ->middleware([PreventStatutoryInputFlash::class, EnsureSalaryTextEncoding::class])->name('payroll-reviews.transition');
    Route::get('payroll-batches/{batch}/employees/{entry}/inputs', [PayrollInputController::class, 'show'])->name('payroll-inputs.show');
    Route::put('payroll-batches/{batch}/employees/{entry}/inputs', [PayrollInputController::class, 'update'])
        ->middleware([ScopePayrollInputErrors::class, EnsureSalaryTextEncoding::class])->name('payroll-inputs.update');
    Route::post('payroll-batches/{batch}/calculations/{run}/employees/{item}/statutory', [StatutoryPreviewController::class, 'store'])
        ->middleware([PreventStatutoryInputFlash::class, EnsureSalaryTextEncoding::class])->name('statutory-previews.store');
    Route::middleware(EnsureSalaryTextEncoding::class)->group(function () {
        Route::get('payroll-batches/{batch}/calculations/{run}/employees/{item}/statutory', [StatutoryPreviewController::class, 'show'])->name('statutory-previews.show');
        Route::get('payroll-batches/{batch}/calculations', [PayrollCalculationController::class, 'index'])->name('payroll-calculations.index');
        Route::post('payroll-batches/{batch}/calculations', [PayrollCalculationController::class, 'store'])->name('payroll-calculations.store');
        Route::get('payroll-batches/{batch}/calculations/{run}', [PayrollCalculationController::class, 'show'])->name('payroll-calculations.show');
        Route::get('payroll-batches', [PayrollBatchController::class, 'index'])->name('payroll-batches.index');
        Route::get('payroll-batches/create', [PayrollBatchController::class, 'create'])->name('payroll-batches.create');
        Route::post('payroll-batches', [PayrollBatchController::class, 'store'])->name('payroll-batches.store');
        Route::get('payroll-batches/{batch}', [PayrollBatchController::class, 'show'])->name('payroll-batches.show');
        Route::get('payroll-batches/{batch}/employees/create', [PayrollBatchController::class, 'select'])->name('payroll-employees.create');
        Route::post('payroll-batches/{batch}/employees', [PayrollBatchController::class, 'add'])->name('payroll-employees.store');
        Route::delete('payroll-batches/{batch}/employees/{entry}', [PayrollBatchController::class, 'remove'])->name('payroll-employees.destroy');
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
