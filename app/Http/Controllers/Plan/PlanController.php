<?php

namespace App\Http\Controllers\Plan;

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Models\Direction;
use App\Models\Measure;
use App\Models\Responsible;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Модуль «План» — реестр мероприятий, единственный источник истины для KPI и сводов —
 * [[Функциональные требования#4.1 Модуль «План» — реестр мероприятий]]. Доступен только
 * guard'у `web` (все три роли — проректор/координатор на равных правах чтения,
 * наблюдатель тоже, редактирование сюда не входит).
 */
class PlanController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Measure::query()
            ->with(['direction', 'responsible', 'latestPeriodState', 'stages.periodUpdates']);

        $this->applyFilters($query, $request);
        $this->applySort($query, $request);

        $measures = $query->paginate(20)->withQueryString();

        $measures->getCollection()->transform(function (Measure $measure) {
            return [
                'id' => $measure->id,
                'number' => $measure->number,
                'title' => $measure->title,
                'direction' => $measure->direction?->name,
                'responsible' => $measure->responsible?->name,
                'deadline' => $measure->deadline?->format('Y-m-d'),
                'status' => $measure->currentStatus()->value,
                'percent' => $measure->percent,
                'risk_level' => $measure->risk_level?->value,
                'needs_decision' => (bool) $measure->latestPeriodState?->needs_decision,
                'stages' => $measure->stages->map(fn ($stage) => [
                    'id' => $stage->id,
                    'order' => $stage->order,
                    'title' => $stage->title,
                    'planned_date' => $stage->planned_date?->format('Y-m-d'),
                    'weight' => $stage->weight,
                    'review_state' => $stage->periodUpdates->sortByDesc('period_id')->first()?->review_state?->value,
                ])->values(),
            ];
        });

        return Inertia::render('plan/index', [
            'measures' => $measures,
            'filters' => $request->only([
                'search', 'direction_id', 'responsible_id', 'status', 'risk_level',
                'needs_decision', 'has_stages_to_review', 'deadline_from', 'deadline_to',
                'sort', 'direction',
            ]),
            'directions' => Direction::orderBy('number')->get(['id', 'number', 'name']),
            'responsibles' => Responsible::orderBy('name')->get(['id', 'name']),
            'statuses' => collect(MeasureStatus::cases())->map->value,
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $query
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($q) => $q->where('title', 'like', "%{$search}%")
                    ->orWhere('number', 'like', "%{$search}%"));
            })
            ->when($request->filled('direction_id'), fn ($q) => $q->where('direction_id', $request->integer('direction_id')))
            ->when($request->filled('responsible_id'), fn ($q) => $q->where('responsible_id', $request->integer('responsible_id')))
            ->when($request->filled('risk_level'), fn ($q) => $q->where('risk_level', $request->string('risk_level')))
            ->when($request->filled('deadline_from'), fn ($q) => $q->whereDate('deadline', '>=', $request->string('deadline_from')))
            ->when($request->filled('deadline_to'), fn ($q) => $q->whereDate('deadline', '<=', $request->string('deadline_to')))
            ->when($request->boolean('needs_decision'), function ($q) {
                $q->whereHas('periodStates', fn ($q) => $q->where('needs_decision', true));
            })
            ->when($request->boolean('has_stages_to_review'), function ($q) {
                $q->whereHas('stages.periodUpdates', fn ($q) => $q->whereIn('review_state', [
                    ReviewState::Submitted->value, ReviewState::Rework->value,
                ]));
            });

        if ($request->filled('status')) {
            $status = $request->string('status')->toString();

            if ($status === MeasureStatus::NotStarted->value) {
                $query->where(fn ($q) => $q->doesntHave('latestPeriodState')
                    ->orWhereHas('latestPeriodState', fn ($q) => $q->where('status', $status)));
            } else {
                $query->whereHas('latestPeriodState', fn ($q) => $q->where('status', $status));
            }
        }
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sortable = ['number', 'deadline', 'percent', 'risk_level'];
        $sort = $request->string('sort', 'number')->toString();
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $column = ltrim($sort, '-');

        if (! in_array($column, $sortable, true)) {
            $column = 'number';
            $direction = 'asc';
        }

        $query->orderBy($column, $direction);
    }
}
