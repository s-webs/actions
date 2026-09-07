<?php

namespace App\Http\Controllers\Plan;

use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Services\MeasureImportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Разовый и повторный импорт листа «План» из xlsx — координатор/проректор,
 * [[Функциональные требования#4.12 Импорт из Excel]].
 */
class MeasureImportController extends Controller
{
    public function create(Request $request): Response
    {
        Gate::authorize('import', Measure::class);

        return Inertia::render('plan/import', [
            'report' => $request->session()->get('import_report'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('import', Measure::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls'],
        ]);

        $path = $request->file('file')->getRealPath();
        $report = app(MeasureImportService::class)->import($path);

        return redirect()->route('plan.import')->with('import_report', $report->toArray());
    }
}
