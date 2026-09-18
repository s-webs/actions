<?php

use App\Http\Controllers\Responsibles\ResponsibleAccountController;
use App\Http\Controllers\Responsibles\ResponsiblePositionController;
use App\Http\Controllers\Responsibles\ResponsibleSummaryController;
use Illuminate\Support\Facades\Route;

/**
 * Свод по должностям, справочник должностей и именные учётки.
 */
Route::middleware('auth:web')->group(function () {
    Route::get('responsibles', [ResponsibleSummaryController::class, 'index'])->name('responsibles.index');

    Route::get('responsibles/accounts', [ResponsibleAccountController::class, 'index'])->name('responsibles.accounts.index');
    Route::get('responsibles/accounts/create', [ResponsibleAccountController::class, 'create'])->name('responsibles.accounts.create');
    Route::post('responsibles/accounts', [ResponsibleAccountController::class, 'store'])->name('responsibles.accounts.store');
    Route::get('responsibles/accounts/{responsible}/edit', [ResponsibleAccountController::class, 'edit'])->name('responsibles.accounts.edit');
    Route::put('responsibles/accounts/{responsible}', [ResponsibleAccountController::class, 'update'])->name('responsibles.accounts.update');

    Route::get('responsibles/positions/create', [ResponsiblePositionController::class, 'create'])->name('responsibles.positions.create');
    Route::post('responsibles/positions', [ResponsiblePositionController::class, 'store'])->name('responsibles.positions.store');
    Route::get('responsibles/positions/{responsible}/edit', [ResponsiblePositionController::class, 'edit'])->name('responsibles.positions.edit');
    Route::put('responsibles/positions/{responsible}', [ResponsiblePositionController::class, 'update'])->name('responsibles.positions.update');
});
