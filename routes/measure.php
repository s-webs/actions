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

/**
 * Прямая ссылка входа — вне guest:measure намеренно: если в браузере уже открыта
 * сессия другого мероприятия, ссылка должна перелогинить на своё, а не быть
 * заблокирована guest-мидлваром.
 */
Route::get('measure/link-login/{token}', [MeasureAuthenticatedSessionController::class, 'loginViaLink'])
    ->middleware('throttle:30,1')
    ->name('measure.link-login');

Route::middleware('auth:measure')->group(function () {
    Route::get('measure/workspace', [WorkspaceController::class, 'show'])->name('measure.workspace');
    Route::patch('measure/workspace', [WorkspaceController::class, 'update'])->name('measure.workspace.update');
    Route::post('measure/workspace/stages/{stage}/evidence', [WorkspaceController::class, 'storeEvidence'])
        ->name('measure.workspace.evidence');

    Route::post('measure/stages', [WorkspaceController::class, 'storeStage'])->name('measure.stages.store');
    Route::patch('measure/stages/{stage}', [WorkspaceController::class, 'updateStage'])->name('measure.stages.update');
    Route::delete('measure/stages/{stage}', [WorkspaceController::class, 'destroyStage'])->name('measure.stages.destroy');
    Route::post('measure/stages/confirm', [WorkspaceController::class, 'confirmStages'])->name('measure.stages.confirm');

    Route::post('measure/logout', [MeasureAuthenticatedSessionController::class, 'destroy'])
        ->name('measure.logout');
});
