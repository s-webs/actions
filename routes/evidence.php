<?php

use App\Http\Controllers\Evidence\EvidenceController;
use Illuminate\Support\Facades\Route;

/**
 * Доказательная база — [[Функциональные требования#4.6 Модуль «Доказательная база»]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('evidence', [EvidenceController::class, 'index'])->name('evidence.index');
    Route::delete('evidence/{evidence}', [EvidenceController::class, 'destroy'])->name('evidence.destroy');
});
