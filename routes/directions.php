<?php

use App\Http\Controllers\Directions\DirectionController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:web')->group(function () {
    Route::get('directions', [DirectionController::class, 'index'])->name('directions.index');
    Route::get('directions/create', [DirectionController::class, 'create'])->name('directions.create');
    Route::post('directions', [DirectionController::class, 'store'])->name('directions.store');
    Route::get('directions/{direction}/edit', [DirectionController::class, 'edit'])->name('directions.edit');
    Route::put('directions/{direction}', [DirectionController::class, 'update'])->name('directions.update');
    Route::delete('directions/{direction}', [DirectionController::class, 'destroy'])->name('directions.destroy');
});
