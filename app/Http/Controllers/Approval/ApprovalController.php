<?php

namespace App\Http\Controllers\Approval;

use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\StagePeriodUpdate;
use App\Notifications\StageDecisionMade;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Проверка и утверждение этапов — рабочее место проректора,
 * [[Функциональные требования#4.13 Модуль «Проверка и утверждение этапов» (проректор)]].
 * Утверждение записывает `%` этапа в мероприятие. Последний этап — 100%
 * (исполнитель на этапе максимум 95%). «Принять работу» в плане остаётся
 * запасным путём. Массового утверждения нет — каждый этап отдельным действием.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StagePeriodUpdate::class);

        $updates = StagePeriodUpdate::query()
            ->whereIn('review_state', [ReviewState::Submitted, ReviewState::Rework])
            ->whereHas('stage.measure', fn ($q) => $q->visibleTo($request->user()))
            ->with(['stage.measure.direction', 'stage.measure.stages', 'period'])
            ->get()
            ->sortBy(fn (StagePeriodUpdate $u) => $this->priority($u))
            ->values();

        return Inertia::render('approval/index', [
            'updates' => $updates->map(function (StagePeriodUpdate $u) {
                $periodState = MeasurePeriodState::where('measure_id', $u->stage->measure_id)
                    ->where('period_id', $u->period_id)
                    ->first();

                return [
                    'id' => $u->id,
                    'review_state' => $u->review_state->value,
                    'done_text' => $u->done_text,
                    'submitted_by_name' => $u->submitted_by_name,
                    'submitted_at' => $u->submitted_at?->format('Y-m-d H:i'),
                    'period' => $u->period->month->format('Y-m'),
                    'stage' => [
                        'id' => $u->stage->id,
                        'title' => $u->stage->title,
                        'planned_date' => $u->stage->planned_date?->format('Y-m-d'),
                        'weight' => $u->stage->weight,
                        'is_last_stage' => $this->isLastUnapprovedStage($u->stage),
                    ],
                    'measure' => [
                        'number' => $u->stage->measure->number,
                        'title' => $u->stage->measure->title,
                        'direction' => $u->stage->measure->direction?->name,
                        'deadline' => $u->stage->measure->deadline?->format('Y-m-d'),
                        'risk_level' => $u->stage->measure->currentRiskLevel()?->value,
                    ],
                    // Текст риска/проблемы, который исполнитель вписал вместе с этим отчётом
                    // за этот же период ([[Функциональные требования#4.7 Рабочее место мероприятия]])
                    // — без него администратор видел только цвет светофора, но не причину.
                    'risk_text' => $periodState?->risk_text,
                    'needs_decision' => (bool) $periodState?->needs_decision,
                    'evidences' => Evidence::where('measure_stage_id', $u->stage->id)
                        ->where('period_id', $u->period_id)
                        ->get()
                        ->map(fn (Evidence $e) => [
                            'id' => $e->id,
                            'title' => $e->title,
                            'path_or_url' => $e->publicUrl(),
                            'type' => $e->type->value,
                        ]),
                ];
            }),
        ]);
    }

    public function approve(Request $request, StagePeriodUpdate $update): RedirectResponse
    {
        Gate::authorize('approve', $update);
        $this->ensureReviewable($update);

        $update->loadMissing('stage.measure.stages');

        $update->update([
            'review_state' => ReviewState::Approved,
            'approved_percent' => $this->isLastUnapprovedStage($update->stage) ? 100 : $update->stage->weight,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $update->stage->measure->recalculatePercent();

        return back()->with('status', 'Этап утверждён.');
    }

    public function reject(Request $request, StagePeriodUpdate $update): RedirectResponse
    {
        Gate::authorize('approve', $update);
        $this->ensureReviewable($update);

        $data = $request->validate(['review_comment' => ['required', 'string']]);

        $update->update([
            'review_state' => ReviewState::Rejected,
            'review_comment' => $data['review_comment'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $update->stage->measure->notify(new StageDecisionMade($update));

        return back()->with('status', 'Этап отклонён.');
    }

    public function rework(Request $request, StagePeriodUpdate $update): RedirectResponse
    {
        Gate::authorize('approve', $update);
        $this->ensureReviewable($update);

        $data = $request->validate(['review_comment' => ['required', 'string']]);

        $update->update([
            'review_state' => ReviewState::Rework,
            'review_comment' => $data['review_comment'],
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        $update->stage->measure->notify(new StageDecisionMade($update));

        return back()->with('status', 'Возвращено на доработку.');
    }

    private function ensureReviewable(StagePeriodUpdate $update): void
    {
        if (! in_array($update->review_state, [ReviewState::Submitted, ReviewState::Rework], true)) {
            throw ValidationException::withMessages([
                'stage' => 'Этап уже не находится на проверке.',
            ]);
        }
    }

    /**
     * Последний неутверждённый этап: у всех остальных уже есть утверждение.
     * Его приёмка ставит 100%, даже если на этапе написано 95%.
     */
    private function isLastUnapprovedStage(MeasureStage $stage): bool
    {
        $measure = $stage->measure;
        $measure->loadMissing('stages');

        return $measure->stages
            ->reject(fn (MeasureStage $other) => $other->id === $stage->id)
            ->every(fn (MeasureStage $other) => $other->periodUpdates()
                ->where('review_state', ReviewState::Approved)
                ->exists());
    }

    /**
     * Приоритет очереди — [[Заполнение и утверждение#Что видит проректор]]: просрочена
     * плановая дата этапа → высокий риск мероприятия → срок мероприятия ≤ 30 дней →
     * прочие.
     */
    private function priority(StagePeriodUpdate $update): int
    {
        $stage = $update->stage;
        $measure = $stage->measure;

        if ($stage->planned_date?->isPast()) {
            return 0;
        }

        if ($measure->currentRiskLevel() === RiskLevel::High) {
            return 1;
        }

        if ($measure->deadline?->isFuture() && $measure->deadline->diffInDays(now()) <= 30) {
            return 2;
        }

        return 3;
    }
}
