<?php

namespace App\Http\Controllers\Reports;

use App\Exports\PlanExport;
use App\Http\Controllers\Controller;
use App\Services\DashboardSummaryService;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Экспорт «Кабинета проректора» в PDF и плана в xlsx —
 * [[Функциональные требования#4.9 Отчёты, экспорт и архив срезов]].
 */
class ReportController extends Controller
{
    public function dashboardPdf(DashboardSummaryService $summary): Response
    {
        $pdf = Pdf::loadView('exports.dashboard', $summary->build());

        return $pdf->stream('kabinet-prorektora.pdf');
    }

    public function planXlsx(): BinaryFileResponse
    {
        return Excel::download(new PlanExport, 'plan-'.now()->format('Y-m-d').'.xlsx');
    }
}
