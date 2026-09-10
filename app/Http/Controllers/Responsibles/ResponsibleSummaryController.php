<?php

namespace App\Http\Controllers\Responsibles;

use App\Enums\MeasureStatus;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Models\Responsible;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Свод по ответственным — контроль нагрузки,
 * [[Функциональные требования#4.5 Модуль «Свод по ответственным»]]. Порог подсветки
 * перегрузки — методология не определена заказчиком (см. [[Справочники#Ещё нужно от заказчика]]);
 * временная эвристика — число мероприятий выше среднего по всем ответственным.
 */
class ResponsibleSummaryController extends Controller
{
    public function index(): Response
    {
        $measures = Measure::with(['latestPeriodState', 'stages'])->get();
        $responsibles = Responsible::orderBy('name')->get();

        $rows = $responsibles->map(function (Responsible $responsible) use ($measures) {
            $own = $measures->where('responsible_id', $responsible->id);

            return $this->summarize($responsible->name, $own);
        });

        $unassigned = $measures->whereNull('responsible_id');
        if ($unassigned->isNotEmpty()) {
            $rows->push($this->summarize('Без ответственного', $unassigned));
        }

        $rows = $rows->filter(fn ($row) => $row['count'] > 0)->values();
        $averageCount = $rows->isEmpty() ? 0 : $rows->avg('count');

        $rows = $rows->map(function ($row) use ($averageCount) {
            $row['overloaded'] = $row['count'] > $averageCount;

            return $row;
        });

        return Inertia::render('responsibles/index', ['rows' => $rows->values()]);
    }

    private function summarize(string $name, $measures): array
    {
        $statuses = $measures->map(fn (Measure $m) => $m->currentStatus());

        return [
            'name' => $name,
            'count' => $measures->count(),
            'status_breakdown' => collect(MeasureStatus::cases())
                ->mapWithKeys(fn ($s) => [$s->value => $statuses->filter(fn ($x) => $x === $s)->count()]),
            'avg_percent' => $measures->isEmpty() ? 0 : (int) round($measures->avg('percent')),
            'risks' => $measures->filter(fn (Measure $m) => $m->currentRiskLevel() === RiskLevel::High)->count(),
            'overdue' => $statuses->filter(fn ($s) => $s === MeasureStatus::Overdue)->count(),
            'nearest_deadline' => $measures->pluck('deadline')->filter()->sort()->first()?->format('Y-m-d'),
        ];
    }
}
