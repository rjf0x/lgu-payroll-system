<?php

use App\Enums\Role;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\UserController;
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

    foreach (Role::cases() as $role) {
        Route::get('/'.$role->workspace(), fn () => view('workspace', ['role' => $role]))
            ->middleware('can:access-'.$role->workspace())
            ->name($role->workspace());
    }
});
