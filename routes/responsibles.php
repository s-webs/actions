<?php

use App\Http\Controllers\Responsibles\ResponsibleSummaryController;
use Illuminate\Support\Facades\Route;

/**
 * Свод по ответственным — [[Функциональные требования#4.5 Модуль «Свод по ответственным»]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('responsibles', [ResponsibleSummaryController::class, 'index'])->name('responsibles.index');
});
