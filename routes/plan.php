<?php

use App\Http\Controllers\Plan\MeasureImportController;
use Illuminate\Support\Facades\Route;

/**
 * Модуль «План» — реестр мероприятий строится в task-006; здесь пока только импорт
 * (task-005), доступный координатору/проректору — [[Функциональные требования#4.12 Импорт из Excel]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('plan/import', [MeasureImportController::class, 'create'])->name('plan.import');
    Route::post('plan/import', [MeasureImportController::class, 'store'])->name('plan.import.store');
});
