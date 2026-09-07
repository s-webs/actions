<?php

use App\Http\Controllers\Reports\ReportController;
use Illuminate\Support\Facades\Route;

/**
 * Экспорт — [[Функциональные требования#4.9 Отчёты, экспорт и архив срезов]].
 */
Route::middleware('auth:web')->group(function () {
    Route::get('reports/dashboard.pdf', [ReportController::class, 'dashboardPdf'])->name('reports.dashboard-pdf');
    Route::get('reports/plan.xlsx', [ReportController::class, 'planXlsx'])->name('reports.plan-xlsx');
});
