<?php

namespace App\Http\Controllers\Plan;

use App\Enums\EvidenceType;
use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Models\Direction;
use App\Models\Evidence;
use App\Models\Measure;
use App\Models\MeasureStage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
            ->with(['direction', 'responsible', 'latestPeriodState', 'stages.periodUpdates', 'stages.evidences']);

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
                'risk_level' => $measure->currentRiskLevel()?->value,
                'needs_decision' => (bool) $measure->latestPeriodState?->needs_decision,
                'stages_confirmed' => $measure->stagesConfirmed(),
                'stages' => $measure->stages->map(fn (MeasureStage $stage) => $this->stagePayload($stage))->values(),
            ];
        });

        return Inertia::render('plan/index', [
            'measures' => $measures,
            'filters' => $request->only([
                'search', 'direction_id', 'responsible', 'status', 'risk_level',
                'needs_decision', 'has_stages_to_review', 'deadline_from', 'deadline_to',
                'sort', 'direction',
            ]),
            'directions' => Direction::orderBy('number')->get(['id', 'number', 'name']),
            'statuses' => collect(MeasureStatus::cases())->map->value,
            'canManageStages' => Auth::user()->can('manage', Measure::class),
            'canCreate' => Auth::user()->can('create', Measure::class),
            'canImport' => Auth::user()->can('import', Measure::class),
            'isDeveloper' => Auth::user()->hasRole('developer'),
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
            ->when($request->filled('responsible'), function ($q) use ($request) {
                $name = $request->string('responsible')->toString();
                $q->whereHas('responsible', fn ($q) => $q->where('name', 'like', "%{$name}%"));
            })
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

        $this->applyComputedStatusAndRiskFilters($query, $request);
    }

    /**
     * Статус и риск считаются на лету — SQL по сохранённым полям отставал бы
     * от экрана до ежедневного джоба.
     */
    private function applyComputedStatusAndRiskFilters(Builder $query, Request $request): void
    {
        if (! $request->filled('status') && ! $request->filled('risk_level')) {
            return;
        }

        $matches = (clone $query)
            ->with(['latestPeriodState', 'stages'])
            ->get()
            ->filter(function (Measure $measure) use ($request) {
                if ($request->filled('status') && $measure->currentStatus()->value !== $request->string('status')->toString()) {
                    return false;
                }

                if ($request->filled('risk_level') && $measure->currentRiskLevel()?->value !== $request->string('risk_level')->toString()) {
                    return false;
                }

                return true;
            })
            ->pluck('id');

        $query->whereIn('id', $matches->isEmpty() ? [0] : $matches->all());
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

    /**
     * @return array<string, mixed>
     */
    private function stagePayload(MeasureStage $stage): array
    {
        $update = $stage->periodUpdates->sortByDesc('period_id')->first();
        $evidences = $this->evidencePayload($stage->evidences);
        $hasDetails = $evidences->isNotEmpty()
            || ($update && filled($update->done_text))
            || ($update && $update->review_state !== ReviewState::Draft);

        return [
            'id' => $stage->id,
            'order' => $stage->order,
            'title' => $stage->title,
            'planned_date' => $stage->planned_date?->format('Y-m-d'),
            'weight' => $stage->weight,
            'review_state' => $update?->review_state?->value,
            'update' => $update ? [
                'id' => $update->id,
                'done_text' => $update->done_text,
                'review_state' => $update->review_state->value,
                'review_comment' => $update->review_comment,
                'approved_percent' => $update->approved_percent,
                'submitted_by_name' => $update->submitted_by_name,
                'submitted_at' => $update->submitted_at?->format('Y-m-d H:i'),
            ] : null,
            'evidences' => $evidences->values(),
            'has_details' => $hasDetails,
        ];
    }

    /**
     * @param  Collection<int, Evidence>  $evidences
     * @return Collection<int, array<string, mixed>>
     */
    private function evidencePayload(Collection $evidences): Collection
    {
        return $evidences->map(fn (Evidence $e) => [
            'id' => $e->id,
            'title' => $e->title,
            'type' => $e->type->value,
            'path_or_url' => $e->type === EvidenceType::Link ? $e->path_or_url : Storage::disk('public')->url($e->path_or_url),
        ]);
    }
}
