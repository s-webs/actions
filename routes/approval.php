<?php

use App\Http\Controllers\Approval\ApprovalController;
use Illuminate\Support\Facades\Route;

/**
 * Проверка и утверждение этапов — только проректор,
 * [[Функциональные требования#4.13 Модуль «Проверка и утверждение этапов» (проректор)]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('approval', [ApprovalController::class, 'index'])->name('approval.index');
    Route::post('approval/{update}/approve', [ApprovalController::class, 'approve'])->name('approval.approve');
    Route::post('approval/{update}/reject', [ApprovalController::class, 'reject'])->name('approval.reject');
    Route::post('approval/{update}/rework', [ApprovalController::class, 'rework'])->name('approval.rework');
});
