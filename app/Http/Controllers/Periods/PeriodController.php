<?php

namespace App\Http\Controllers\Periods;

use App\Enums\PeriodState;
use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Snapshot;
use App\Models\StagePeriodUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Закрытие отчётного периода и архив срезов —
 * [[Заполнение и утверждение#Закрытие периода]], [[Бизнес-правила#Правило 5 · Блокировка закрытого периода]].
 * Только координатор — единственное действие, где у проректора в
 * [[Роли и права#Матрица]] явно «—» ([[PeriodPolicy]]).
 */
class PeriodController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('close', Period::class);

        $period = Period::current();
        $unreviewed = $this->unreviewedStages($period);

        return Inertia::render('periods/index', [
            'period' => ['id' => $period->id, 'month' => $period->month->format('Y-m'), 'state' => $period->state->value],
            'measureCount' => Measure::count(),
            'unreviewedCount' => $unreviewed->count(),
            'unreviewed' => $unreviewed->map(fn (StagePeriodUpdate $u) => [
                'measure_number' => $u->stage->measure->number,
                'measure_title' => $u->stage->measure->title,
                'stage_title' => $u->stage->title,
                'review_state' => $u->review_state->value,
            ])->values(),
            'recentSnapshots' => Period::whereHas('snapshots')
                ->orderByDesc('month')
                ->get()
                ->map(fn (Period $p) => $p->month->format('Y-m'))
                ->values(),
        ]);
    }

    public function close(Request $request): RedirectResponse
    {
        Gate::authorize('close', Period::class);

        $period = Period::current();

        if ($period->state === PeriodState::Closed) {
            return back()->withErrors(['period' => 'Период уже закрыт.']);
        }

        Measure::with(['stages.periodUpdates', 'latestPeriodState'])->get()->each(function (Measure $measure) use ($period) {
            Snapshot::updateOrCreate(
                ['measure_id' => $measure->id, 'period_id' => $period->id],
                [
                    'percent' => $measure->percent,
                    'status' => $measure->currentStatus(),
                    'risk_level' => $measure->currentRiskLevel(),
                    'data' => [
                        'stages' => $measure->stages->map(fn ($stage) => [
                            'title' => $stage->title,
                            'weight' => $stage->weight,
                            'review_state' => $stage->periodUpdates->firstWhere('period_id', $period->id)?->review_state?->value,
                            'approved_percent' => $stage->periodUpdates->firstWhere('period_id', $period->id)?->approved_percent,
                        ])->values(),
                    ],
                ],
            );
        });

        $period->update([
            'state' => PeriodState::Closed,
            'closed_by' => $request->user()->id,
            'closed_at' => now(),
        ]);

        return back()->with('status', "Период {$period->month->format('Y-m')} закрыт, срез сохранён.");
    }

    private function unreviewedStages(Period $period)
    {
        return StagePeriodUpdate::query()
            ->where('period_id', $period->id)
            ->whereIn('review_state', [ReviewState::Submitted, ReviewState::Rework])
            ->with('stage.measure')
            ->get();
    }

    /**
     * Сравнение двух закрытых периодов — [[Функциональные требования#4.9 Отчёты, экспорт и архив срезов]].
     * Без явных `from`/`to` берутся два последних закрытых периода.
     */
    public function compare(Request $request): Response
    {
        Gate::authorize('close', Period::class);

        $closed = Period::whereHas('snapshots')->orderByDesc('month')->get();

        $toId = $request->integer('to') ?: $closed->first()?->id;
        $fromId = $request->integer('from') ?: $closed->skip(1)->first()?->id;

        $comparison = [];
        if ($fromId && $toId) {
            $fromSnapshots = Snapshot::where('period_id', $fromId)->get()->keyBy('measure_id');
            $toSnapshots = Snapshot::where('period_id', $toId)->get()->keyBy('measure_id');

            $comparison = Measure::orderBy('number')->get()->map(function (Measure $m) use ($fromSnapshots, $toSnapshots) {
                $from = $fromSnapshots->get($m->id);
                $to = $toSnapshots->get($m->id);

                return [
                    'number' => $m->number,
                    'title' => $m->title,
                    'from_percent' => $from?->percent,
                    'to_percent' => $to?->percent,
                    'from_status' => $from?->status?->value,
                    'to_status' => $to?->status?->value,
                    'changed' => $from && $to && ($from->percent !== $to->percent || $from->status !== $to->status),
                ];
            })->values();
        }

        return Inertia::render('periods/compare', [
            'availablePeriods' => $closed->map(fn (Period $p) => ['id' => $p->id, 'month' => $p->month->format('Y-m')])->values(),
            'fromId' => $fromId,
            'toId' => $toId,
            'comparison' => $comparison,
        ]);
    }
}
