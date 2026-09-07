<?php

namespace App\Http\Controllers\Monitoring;

use App\Enums\MeasureStatus;
use App\Http\Controllers\Controller;
use App\Models\CalendarFocus;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Snapshot;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Помесячный мониторинг — матрица 51 мероприятие × 10 месяцев,
 * [[Функциональные требования#4.3 Модуль «Помесячный мониторинг»]]. Столбцы берутся из
 * [[Справочники#Календарь контрольного мониторинга]] (уже единственный источник истины
 * для 10 месяцев цикла — не дублируем список дат). Закрытые периоды читаются из
 * `snapshots` (неизменяемый архив, task-016); полные данные появятся только после
 * первого закрытия периода — до этого таблица честно пустая (кроме текущего месяца,
 * который считается вживую и помечается как незакрытый).
 */
class MonitoringController extends Controller
{
    public function index(): Response
    {
        $months = CalendarFocus::orderBy('month')->pluck('month')->map(fn ($m) => $m->format('Y-m'));
        $currentMonth = Period::current()->month->format('Y-m');

        $measures = Measure::with('latestPeriodState')->orderBy('number')->get();

        $snapshotsByMeasure = Snapshot::with('period')
            ->get()
            ->groupBy('measure_id')
            ->map(fn ($snapshots) => $snapshots->keyBy(fn (Snapshot $s) => $s->period->month->format('Y-m')));

        $rows = $measures->map(function (Measure $measure) use ($months, $currentMonth, $snapshotsByMeasure) {
            $snapshotsForMeasure = $snapshotsByMeasure->get($measure->id, collect());

            $cells = $months->map(function (string $month) use ($measure, $currentMonth, $snapshotsForMeasure) {
                if ($month === $currentMonth) {
                    return ['month' => $month, 'live' => true, 'symbol' => $this->symbol($measure->currentStatus()), 'status' => $measure->currentStatus()->value, 'percent' => $measure->percent];
                }

                /** @var Snapshot|null $snapshot */
                $snapshot = $snapshotsForMeasure->get($month);

                if (! $snapshot) {
                    return ['month' => $month, 'live' => false, 'symbol' => '—', 'status' => null, 'percent' => null];
                }

                return [
                    'month' => $month,
                    'live' => false,
                    'symbol' => $this->symbol($snapshot->status),
                    'status' => $snapshot->status->value,
                    'percent' => $snapshot->percent,
                    'risk_level' => $snapshot->risk_level?->value,
                ];
            });

            return [
                'id' => $measure->id,
                'number' => $measure->number,
                'title' => $measure->title,
                'cells' => $cells->values(),
            ];
        });

        return Inertia::render('monitoring/index', [
            'months' => $months->values(),
            'currentMonth' => $currentMonth,
            'rows' => $rows->values(),
        ]);
    }

    private function symbol(MeasureStatus $status): string
    {
        return match ($status) {
            MeasureStatus::Done => '✓',
            MeasureStatus::InProgress => '↗',
            MeasureStatus::AtRisk => '⚠',
            MeasureStatus::Overdue => '!',
            MeasureStatus::NotStarted => '—',
        };
    }
}
