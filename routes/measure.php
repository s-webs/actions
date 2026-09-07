<?php

use App\Http\Controllers\Auth\MeasureAuthenticatedSessionController;
use App\Http\Controllers\Measure\WorkspaceController;
use Illuminate\Support\Facades\Route;

/**
 * Guard `measure` — вход по логину/паролю мероприятия
 * ([[Роли и права#Доступ к мероприятию (неименной)]]) и рабочее место мероприятия
 * ([[Функциональные требования#4.7 Рабочее место мероприятия]], task-007).
 */
Route::middleware('guest:measure')->group(function () {
    Route::get('measure/login', [MeasureAuthenticatedSessionController::class, 'create'])
        ->name('measure.login');

    Route::post('measure/login', [MeasureAuthenticatedSessionController::class, 'store'])
        ->name('measure.login.store');
});

Route::middleware('auth:measure')->group(function () {
    Route::get('measure/workspace', [WorkspaceController::class, 'show'])->name('measure.workspace');
    Route::patch('measure/workspace', [WorkspaceController::class, 'update'])->name('measure.workspace.update');
    Route::post('measure/workspace/stages/{stage}/evidence', [WorkspaceController::class, 'storeEvidence'])
        ->name('measure.workspace.evidence');

    Route::post('measure/logout', [MeasureAuthenticatedSessionController::class, 'destroy'])
        ->name('measure.logout');
});
