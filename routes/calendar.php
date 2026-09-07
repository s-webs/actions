<?php

use App\Http\Controllers\Calendar\CalendarController;
use Illuminate\Support\Facades\Route;

/**
 * Календарь контроля — [[Функциональные требования#4.4 Модуль «Календарь контроля»]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
});
