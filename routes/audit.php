<?php

use App\Http\Controllers\Audit\AuditController;
use Illuminate\Support\Facades\Route;

/**
 * История изменений — [[Функциональные требования#4.11 Аудит и история изменений]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
});
