<?php

use App\Http\Controllers\Periods\PeriodController;
use Illuminate\Support\Facades\Route;

/**
 * Закрытие периода — только координатор,
 * [[Заполнение и утверждение#Закрытие периода]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('periods', [PeriodController::class, 'index'])->name('periods.index');
    Route::post('periods/close', [PeriodController::class, 'close'])->name('periods.close');
});
