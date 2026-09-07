<?php

use App\Http\Controllers\Credentials\CredentialController;
use Illuminate\Support\Facades\Route;

/**
 * Учётные данные мероприятий — [[Функциональные требования#4.14 Модуль «Учётные данные мероприятий»]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('credentials', [CredentialController::class, 'index'])->name('credentials.index');
    Route::post('credentials/rotate-all', [CredentialController::class, 'rotateAll'])->name('credentials.rotate-all');
    Route::post('credentials/{measure}/rotate', [CredentialController::class, 'rotate'])->name('credentials.rotate');
    Route::post('credentials/{measure}/terminate-sessions', [CredentialController::class, 'terminateSessions'])->name('credentials.terminate-sessions');
    Route::patch('credentials/{measure}/expiry', [CredentialController::class, 'setExpiry'])->name('credentials.expiry');
});
