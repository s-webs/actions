<?php

use App\Http\Controllers\Auth\MeasureAuthenticatedSessionController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/**
 * Guard `measure` — вход по логину/паролю мероприятия
 * ([[Роли и права#Доступ к мероприятию (неименной)]]). Полное рабочее место мероприятия
 * (этапы, факты, документы, подача на проверку) — task-007; здесь только сама
 * аутентификация и заглушка целевой страницы.
 */
Route::middleware('guest:measure')->group(function () {
    Route::get('measure/login', [MeasureAuthenticatedSessionController::class, 'create'])
        ->name('measure.login');

    Route::post('measure/login', [MeasureAuthenticatedSessionController::class, 'store'])
        ->name('measure.login.store');
});

Route::middleware('auth:measure')->group(function () {
    Route::get('measure/workspace', function () {
        return Inertia::render('measure/workspace-placeholder');
    })->name('measure.workspace');

    Route::post('measure/logout', [MeasureAuthenticatedSessionController::class, 'destroy'])
        ->name('measure.logout');
});
