<?php

namespace App\Services;

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Models\Direction;
use App\Models\Measure;
use App\Models\StagePeriodUpdate;
use Illuminate\Support\Carbon;

/**
 * Расчёт «Кабинета проректора» — вынесено из `DashboardController` (task-009), чтобы
 * не дублировать логику для PDF-экспорта (task-017,
 * [[Функциональные требования#4.9 Отчёты, экспорт и архив срезов]]). Один источник
 * правды для обоих представлений одних и тех же данных.
 */
class DashboardSummaryService
{
    private const RISK_WEIGHT = ['high' => 3, 'medium' => 2, 'low' => 1];

    public function build(): array
    {
        $measures = Measure::with(['direction', 'responsible', 'latestPeriodState', 'stages'])->get();

        return [
            'kpis' => $this->kpis($measures),
            'statusBreakdown' => $this->statusBreakdown($measures),
            'directionSummary' => $this->directionSummary($measures),
            'upcomingDeadlines' => $this->upcomingDeadlines($measures),
            'needsDecision' => $this->needsDecision($measures),
            'topRisks' => $this->topRisks($measures),
            'generatedAt' => now()->format('Y-m-d H:i'),
        ];
    }

    private function kpis($measures): array
    {
        $statuses = $measures->map(fn (Measure $m) => $m->currentStatus());

        return [
            'total' => $measures->count(),
            'done' => $statuses->filter(fn ($s) => $s === MeasureStatus::Done)->count(),
            'in_progress' => $statuses->filter(fn ($s) => $s === MeasureStatus::InProgress)->count(),
            'at_risk' => $statuses->filter(fn ($s) => $s === MeasureStatus::AtRisk)->count(),
            'overdue' => $statuses->filter(fn ($s) => $s === MeasureStatus::Overdue)->count(),
            'high_risk' => $measures->filter(fn (Measure $m) => $m->currentRiskLevel() === RiskLevel::High)->count(),
            'avg_percent' => $measures->isEmpty() ? 0 : (int) round($measures->avg('percent')),
            'stages_to_review' => StagePeriodUpdate::whereIn('review_state', [ReviewState::Submitted, ReviewState::Rework])->count(),
        ];
    }

    private function statusBreakdown($measures): array
    {
        $counts = collect(MeasureStatus::cases())->mapWithKeys(fn ($s) => [$s->value => 0]);

        foreach ($measures as $measure) {
            $status = $measure->currentStatus()->value;
            $counts[$status] = $counts[$status] + 1;
        }

        return $counts->map(fn ($count, $status) => ['status' => $status, 'count' => $count])->values()->all();
    }

    private function directionSummary($measures): array
    {
        return Direction::where('in_summary', true)->orderBy('number')->get()
            ->map(function (Direction $direction) use ($measures) {
                $directionMeasures = $measures->where('direction_id', $direction->id);

                return [
                    'name' => $direction->name,
                    'count' => $directionMeasures->count(),
                    'avg_percent' => $directionMeasures->isEmpty() ? 0 : (int) round($directionMeasures->avg('percent')),
                ];
            })
            ->values()->all();
    }

    private function upcomingDeadlines($measures): array
    {
        $today = Carbon::today();
        $limit = $today->copy()->addDays(30);

        return $measures
            ->filter(fn (Measure $m) => $m->deadline && $m->deadline->betweenIncluded($today, $limit))
            ->sortBy('deadline')
            ->take(10)
            ->map(fn (Measure $m) => $this->measureSummary($m))
            ->values()->all();
    }

    private function needsDecision($measures): array
    {
        $today = Carbon::today();
        $soon = $today->copy()->addDays(30);

        return $measures
            ->filter(function (Measure $m) use ($today, $soon) {
                $status = $m->currentStatus();

                return in_array($status, [MeasureStatus::AtRisk, MeasureStatus::Overdue], true)
                    || (bool) $m->latestPeriodState?->needs_decision
                    || ($m->deadline && $m->deadline->betweenIncluded($today, $soon))
                    || $m->currentRiskLevel() === RiskLevel::High;
            })
            ->map(fn (Measure $m) => $this->measureSummary($m))
            ->values()->all();
    }

    private function topRisks($measures): array
    {
        $items = $measures->all();

        usort($items, function (Measure $a, Measure $b) {
            $riskA = self::RISK_WEIGHT[$a->currentRiskLevel()?->value] ?? 0;
            $riskB = self::RISK_WEIGHT[$b->currentRiskLevel()?->value] ?? 0;
            if ($riskA !== $riskB) {
                return $riskB <=> $riskA;
            }

            $daysA = $a->deadline ? now()->diffInDays($a->deadline, false) : PHP_INT_MAX;
            $daysB = $b->deadline ? now()->diffInDays($b->deadline, false) : PHP_INT_MAX;
            if ($daysA !== $daysB) {
                return $daysA <=> $daysB;
            }

            return $a->percent <=> $b->percent;
        });

        return collect(array_slice($items, 0, 5))->map(fn (Measure $m) => $this->measureSummary($m))->values()->all();
    }

    private function measureSummary(Measure $measure): array
    {
        return [
            'id' => $measure->id,
            'number' => $measure->number,
            'title' => $measure->title,
            'responsible' => $measure->responsible?->name,
            'deadline' => $measure->deadline?->format('Y-m-d'),
            'percent' => $measure->percent,
            'risk_level' => $measure->currentRiskLevel()?->value,
            'status' => $measure->currentStatus()->value,
            'problem' => $measure->latestPeriodState?->risk_text,
        ];
    }
}
