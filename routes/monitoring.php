<?php

use App\Http\Controllers\Monitoring\MonitoringController;
use Illuminate\Support\Facades\Route;

/**
 * Помесячный мониторинг — [[Функциональные требования#4.3 Модуль «Помесячный мониторинг»]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('monitoring', [MonitoringController::class, 'index'])->name('monitoring.index');
});
