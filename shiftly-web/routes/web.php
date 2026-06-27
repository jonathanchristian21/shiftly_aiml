<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeeScheduleController;
use App\Http\Controllers\ManagerAccountController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftRequirementController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (Auth::check()) {
        return Auth::user()->role === 'manager' 
            ? redirect()->route('manager.dashboard') 
            : redirect()->route('employee.schedule');
    }
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

Route::middleware(['auth', 'role:manager'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/profile', [DashboardController::class, 'profile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateManagerProfile'])->name('profile.update');
    
    Route::get('/accounts', [ManagerAccountController::class, 'index'])->name('accounts.index');
    Route::get('/accounts/create', [ManagerAccountController::class, 'create'])->name('accounts.create');
    Route::post('/accounts', [ManagerAccountController::class, 'store'])->name('accounts.store');
    Route::delete('/accounts/{account}', [ManagerAccountController::class, 'destroy'])->name('accounts.destroy');
    
    Route::resource('employees', EmployeeController::class);
    Route::get('/employees-import', [EmployeeController::class, 'showImport'])->name('employees.import');
    Route::post('/employees-import', [EmployeeController::class, 'import'])->name('employees.import.process');
    
    Route::resource('departments', DepartmentController::class);
    Route::resource('shift-requirements', ShiftRequirementController::class);
    Route::post('/shift-requirements-bulk', [ShiftRequirementController::class, 'bulkCreate'])->name('shift-requirements.bulk');
    
    Route::get('/cluster', [ClusterController::class, 'show'])->name('cluster.show');
    Route::post('/cluster/start', [ClusterController::class, 'startClustering'])->name('cluster.start');
    
    Route::get('/schedules', [ScheduleController::class, 'index'])->name('schedules.index');
    Route::get('/schedules/create', [ScheduleController::class, 'create'])->name('schedules.create');
    Route::post('/schedules/generate', [ScheduleController::class, 'generate'])->name('schedules.generate');
    Route::get('/schedules/compare', [ScheduleController::class, 'compare'])->name('schedules.compare');
    Route::get('/schedules/compare/{candidateId}', [ScheduleController::class, 'showCandidate'])->name('schedules.candidate.show');
    Route::post('/schedules/publish', [ScheduleController::class, 'publish'])->name('schedules.publish');
    Route::get('/schedules/{schedule}', [ScheduleController::class, 'show'])->name('schedules.show');
    Route::delete('/schedules/{schedule}', [ScheduleController::class, 'destroy'])->name('schedules.destroy');
});

Route::middleware(['auth', 'role:employee'])->prefix('employee')->name('employee.')->group(function () {
    Route::get('/schedule', [EmployeeScheduleController::class, 'schedule'])->name('schedule');
    Route::get('/profile', [EmployeeScheduleController::class, 'profile'])->name('profile');
    Route::put('/profile', [AuthController::class, 'updateEmployeeProfile'])->name('profile.update');
});
