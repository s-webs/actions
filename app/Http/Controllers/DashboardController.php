<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Кабинет проректора» — стартовый экран, показывает не все 51 мероприятие, а
 * исключения — [[Функциональные требования#4.2 Модуль «Кабинет проректора» — дашборд]].
 * Расчёт — в {@see DashboardSummaryService}, общий с PDF-экспортом (task-017).
 */
class DashboardController extends Controller
{
    public function index(DashboardSummaryService $summary): Response
    {
        return Inertia::render('dashboard', $summary->build());
    }
}
