<?php

use App\Http\Controllers\AiScheduleController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Endpoint sementara untuk integrasi UI manager dengan FastAPI.
// Nanti group ini bisa diberi middleware auth + role manager.
Route::prefix('manager')->name('manager.')->group(function () {
    Route::get('/ai/health', [AiScheduleController::class, 'health'])->name('ai.health');
    Route::post('/employees/cluster', [AiScheduleController::class, 'cluster'])->name('employees.cluster');
    Route::post('/schedules/generate', [AiScheduleController::class, 'generate'])->name('schedules.generate');
    Route::post('/schedule-runs/{scheduleRun}/candidates/{scheduleCandidate}/publish', [AiScheduleController::class, 'publish'])
        ->name('schedule-runs.publish');
});
