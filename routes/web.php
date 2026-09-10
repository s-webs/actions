<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::guard('web')->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
})->name('home');

Route::middleware(['auth:web'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
require __DIR__.'/measure.php';
require __DIR__.'/plan.php';
require __DIR__.'/approval.php';
require __DIR__.'/monitoring.php';
require __DIR__.'/calendar.php';
require __DIR__.'/responsibles.php';
require __DIR__.'/directions.php';
require __DIR__.'/evidence.php';
require __DIR__.'/credentials.php';
require __DIR__.'/periods.php';
require __DIR__.'/reports.php';
require __DIR__.'/audit.php';
