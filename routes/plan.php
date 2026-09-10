<?php

use App\Http\Controllers\Plan\MeasureController;
use App\Http\Controllers\Plan\MeasureImportController;
use App\Http\Controllers\Plan\MeasureStageController;
use App\Http\Controllers\Plan\PlanController;
use Illuminate\Support\Facades\Route;

/**
 * Модуль «План» — [[Функциональные требования#4.1 Модуль «План» — реестр мероприятий]].
 * Доступен только guard'у `web` (все три роли на равных правах чтения).
 */
Route::middleware('auth:web')->group(function () {
    Route::get('plan', [PlanController::class, 'index'])->name('plan.index');

    Route::get('plan/create', [MeasureController::class, 'create'])->name('plan.create');
    Route::post('plan/measures', [MeasureController::class, 'store'])->name('plan.measures.store');
    Route::post('plan/measures/{measure}/accept', [MeasureController::class, 'accept'])->name('plan.measures.accept');

    Route::get('plan/import', [MeasureImportController::class, 'create'])->name('plan.import');
    Route::post('plan/import', [MeasureImportController::class, 'store'])->name('plan.import.store');

    Route::post('plan/measures/{measure}/stages', [MeasureStageController::class, 'store'])->name('plan.stages.store');
    Route::patch('plan/stages/{stage}', [MeasureStageController::class, 'update'])->name('plan.stages.update');
    Route::delete('plan/stages/{stage}', [MeasureStageController::class, 'destroy'])->name('plan.stages.destroy');
});
