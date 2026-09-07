<?php

namespace App\Http\Controllers\Approval;

use App\Enums\ReviewState;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Models\Evidence;
use App\Models\StagePeriodUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Проверка и утверждение этапов — рабочее место проректора,
 * [[Функциональные требования#4.13 Модуль «Проверка и утверждение этапов» (проректор)]].
 * Единственная точка входа `%` в систему — [[Бизнес-правила#Правило 2а]]. Массового
 * утверждения нет — каждый этап отдельным действием.
 */
class ApprovalController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', StagePeriodUpdate::class);

        $updates = StagePeriodUpdate::query()
            ->whereIn('review_state', [ReviewState::Submitted, ReviewState::Rework])
            ->with(['stage.measure.direction', 'period'])
            ->get()
            ->sortBy(fn (StagePeriodUpdate $u) => $this->priority($u))
            ->values();

        return Inertia::render('approval/index', [
            'updates' => $updates->map(fn (StagePeriodUpdate $u) => [
                'id' => $u->id,
                'review_state' => $u->review_state->value,
                'done_text' => $u->done_text,
                'next_step' => $u->next_step,
                'next_step_date' => $u->next_step_date?->format('Y-m-d'),
                'submitted_by_name' => $u->submitted_by_name,
                'submitted_at' => $u->submitted_at?->format('Y-m-d H:i'),
                'period' => $u->period->month->format('Y-m'),
                'stage' => [
                    'id' => $u->stage->id,
                    'title' => $u->stage->title,
                    'planned_date' => $u->stage->planned_date?->format('Y-m-d'),
                    'weight' => $u->stage->weight,
                ],
                'measure' => [
                    'number' => $u->stage->measure->number,
                    'title' => $u->stage->measure->title,
                    'direction' => $u->stage->measure->direction?->name,
                    'deadline' => $u->stage->measure->deadline?->format('Y-m-d'),
                    'risk_level' => $u->stage->measure->risk_level?->value,
                ],
                'evidences' => Evidence::where('measure_stage_id', $u->stage->id)
                    ->where('period_id', $u->period_id)
                    ->get()
                    ->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'path_or_url' => $e->path_or_url, 'type' => $e->type->value]),
            ]),
        ]);
    }

    public function approve(Request $request, StagePeriodUpdate $update): RedirectResponse
    {
        Gate::authorize('approve', $update);
        $this->ensureReviewable($update);

        $data = $request->validate([
            'approved_percent' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        if ((int) $data['approved_percent'] === 100 && ! $this->hasEvidence($update)) {
            throw ValidationException::withMessages([
                'approved_percent' => 'Нельзя проставить 100% без подтверждающего документа за этот период.',
            ]);
        }

        $update->update([
            'review_state' => ReviewState::Approved,
            'approved_percent' => $data['approved_percent'],
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

        return back()->with('status', 'Возвращено на доработку.');
    }

    private function ensureReviewable(StagePeriodUpdate $update): void
    {
        if (! in_array($update->review_state, [ReviewState::Submitted, ReviewState::Rework], true)) {
            throw ValidationException::withMessages([
                'approved_percent' => 'Этап уже не находится на проверке.',
            ]);
        }
    }

    private function hasEvidence(StagePeriodUpdate $update): bool
    {
        return Evidence::where('measure_stage_id', $update->measure_stage_id)
            ->where('period_id', $update->period_id)
            ->exists();
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

        if ($measure->risk_level === RiskLevel::High) {
            return 1;
        }

        if ($measure->deadline?->isFuture() && $measure->deadline->diffInDays(now()) <= 30) {
            return 2;
        }

        return 3;
    }
}
