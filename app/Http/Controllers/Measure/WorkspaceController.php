<?php

namespace App\Http\Controllers\Measure;

use App\Enums\EvidenceType;
use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\SubmittedVia;
use App\Http\Controllers\Approval\ApprovalController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Plan\MeasureStageController;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use OwenIt\Auditing\Models\Audit;

/**
 * Рабочее место мероприятия (guard `measure`) —
 * [[Функциональные требования#4.7 Рабочее место мероприятия]]. Вся авторизация уже
 * закрыта самим guard'ом (task-003): один комплект учётных данных = один `measure_id`,
 * поэтому здесь нет отдельной Policy — только проверка, что этап/state, который правят,
 * ещё редактируемы (`draft`/`rework`), и что подача идёт с обязательным ФИО.
 *
 * task-020 — последовательное заполнение
 * ([[Заполнение и утверждение#Последовательное заполнение этапов]]): два режима,
 * переключаются один раз через {@see confirmStages()}. До фиксации — свободное
 * управление списком этапов ({@see storeStage()}/{@see updateStage()}/
 * {@see destroyStage()}). После — виден и редактируется только
 * {@see Measure::currentStage()}, следующий открывается исключительно
 * утверждением текущего администратором ({@see ApprovalController::approve()}).
 */
class WorkspaceController extends Controller
{
    public function show(Request $request): Response
    {
        $measure = $this->currentMeasure();
        $period = Period::current();

        $this->ensureDraftRows($measure, $period);

        $measure->load(['direction', 'stages' => fn ($q) => $q->orderBy('order')]);

        $measureState = MeasurePeriodState::where('measure_id', $measure->id)
            ->where('period_id', $period->id)
            ->first();

        $history = StagePeriodUpdate::query()
            ->whereIn('measure_stage_id', $measure->stages->pluck('id'))
            ->whereIn('review_state', [ReviewState::Approved, ReviewState::Rejected, ReviewState::Rework])
            ->with('stage:id,title')
            ->orderByDesc('approved_at')
            ->get();

        $confirmed = $measure->stagesConfirmed();
        $currentStage = $confirmed ? $measure->currentStage() : null;

        return Inertia::render('measure/workspace', [
            'measure' => [
                'number' => $measure->number,
                'title' => $measure->title,
                'direction' => $measure->direction?->name,
                'deadline' => $measure->deadline?->format('Y-m-d'),
                'status' => $measure->currentStatus()->value,
                'percent' => $measure->percent,
            ],
            'period' => ['id' => $period->id, 'month' => $period->month->format('Y-m')],
            'measureState' => [
                'status' => $measureState->status->value,
                'risk_text' => $measureState->risk_text,
                'needs_decision' => $measureState->needs_decision,
                'locked' => $this->isMeasureStateLocked($measure, $period),
            ],
            'stagesConfirmed' => $confirmed,
            'stageList' => $measure->stages->map(fn (MeasureStage $stage) => [
                'id' => $stage->id,
                'order' => $stage->order,
                'title' => $stage->title,
                'planned_date' => $stage->planned_date?->format('Y-m-d'),
                'weight' => $stage->weight,
                'status' => $this->stageStatus($stage, $currentStage, $confirmed),
            ]),
            'currentStage' => $currentStage ? $this->currentStagePayload($currentStage, $period) : null,
            'history' => $history->map(fn (StagePeriodUpdate $u) => [
                'id' => $u->id,
                'stage_title' => $u->stage->title,
                'review_state' => $u->review_state->value,
                'approved_percent' => $u->approved_percent,
                'review_comment' => $u->review_comment,
                'approved_at' => $u->approved_at?->format('Y-m-d H:i'),
            ]),
            'changeLog' => $this->changeLog($measure),
        ]);
    }

    /**
     * @return 'current'|'completed'|'locked'|null null — режим наполнения, статусы неприменимы.
     */
    private function stageStatus(MeasureStage $stage, ?MeasureStage $currentStage, bool $confirmed): ?string
    {
        if (! $confirmed) {
            return null;
        }

        if ($currentStage && $stage->id === $currentStage->id) {
            return 'current';
        }

        $hasApproved = $stage->periodUpdates()->where('review_state', ReviewState::Approved)->exists();

        return $hasApproved ? 'completed' : 'locked';
    }

    /**
     * @return array<string, mixed>
     */
    private function currentStagePayload(MeasureStage $stage, Period $period): array
    {
        $update = $stage->periodUpdates()->where('period_id', $period->id)->first();
        $evidences = $stage->evidences()->where('period_id', $period->id)->get();

        return [
            'id' => $stage->id,
            'order' => $stage->order,
            'title' => $stage->title,
            'planned_date' => $stage->planned_date?->format('Y-m-d'),
            'weight' => $stage->weight,
            'update' => optional($update, fn (StagePeriodUpdate $u) => [
                'id' => $u->id,
                'done_text' => $u->done_text,
                'review_state' => $u->review_state->value,
                'review_comment' => $u->review_comment,
                'approved_percent' => $u->approved_percent,
            ]),
            'evidences' => $evidences->map(fn ($e) => [
                'id' => $e->id,
                'title' => $e->title,
                'type' => $e->type->value,
                'path_or_url' => $e->type === EvidenceType::Link ? $e->path_or_url : Storage::disk('public')->url($e->path_or_url),
            ]),
        ];
    }

    /**
     * Все изменения по мероприятию, его этапам и отчётам по этапам — карточки
     * «что менялось, кто, когда» на экране рабочего места (в дополнение к ленте решений
     * проректора выше). Источник — тот же аудит, что и общий `/audit`
     * ([[Функциональные требования#4.11 Аудит и история изменений]]), но отфильтрован
     * только на это мероприятие.
     */
    private function changeLog(Measure $measure): Collection
    {
        $stageIds = $measure->stages->pluck('id');
        $updateIds = StagePeriodUpdate::whereIn('measure_stage_id', $stageIds)->pluck('id');

        $modelLabels = [
            Measure::class => 'Мероприятие',
            MeasureStage::class => 'Этап',
            StagePeriodUpdate::class => 'Отчёт по этапу',
        ];

        return Audit::query()
            ->where(fn ($q) => $q
                ->where(fn ($q2) => $q2->where('auditable_type', Measure::class)->where('auditable_id', $measure->id))
                ->orWhere(fn ($q2) => $q2->where('auditable_type', MeasureStage::class)->whereIn('auditable_id', $stageIds))
                ->orWhere(fn ($q2) => $q2->where('auditable_type', StagePeriodUpdate::class)->whereIn('auditable_id', $updateIds)))
            ->with('user')
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (Audit $audit) => [
                'id' => $audit->id,
                'event' => $audit->event,
                'model' => $modelLabels[$audit->auditable_type] ?? $audit->auditable_type,
                'user' => $audit->user instanceof User ? $audit->user->name : ($audit->user ? 'мероприятие (тот же вход)' : 'система'),
                'old_values' => $audit->old_values,
                'new_values' => $audit->new_values,
                'created_at' => $audit->created_at->format('Y-m-d H:i'),
            ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $measure = $this->currentMeasure();
        $period = Period::current();

        if (! $measure->stagesConfirmed()) {
            abort(403, 'Список этапов ещё не зафиксирован.');
        }

        $currentStage = $measure->currentStage();

        if (! $currentStage) {
            abort(403, 'Все этапы уже утверждены.');
        }

        $isSubmit = $request->string('action')->toString() === 'submit';

        $rules = [
            'measure_status' => ['required', 'string', 'in:'.implode(',', array_map(fn ($c) => $c->value, MeasureStatus::cases()))],
            'risk_text' => ['nullable', 'string'],
            'needs_decision' => ['boolean'],
            'done_text' => ['nullable', 'string'],
        ];

        if ($isSubmit) {
            $rules['submitted_by_name'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if ($this->isMeasureStateLocked($measure, $period)) {
            throw ValidationException::withMessages([
                'measure_status' => 'Этап уже подан на проверку — дождитесь решения администратора.',
            ]);
        }

        MeasurePeriodState::where('measure_id', $measure->id)->where('period_id', $period->id)->update([
            'status' => $data['measure_status'],
            'risk_text' => $data['risk_text'] ?? null,
            'needs_decision' => $data['needs_decision'] ?? false,
        ]);

        $update = StagePeriodUpdate::where('measure_stage_id', $currentStage->id)->where('period_id', $period->id)->first();

        if ($update && in_array($update->review_state, [ReviewState::Draft, ReviewState::Rework], true)) {
            $update->update([
                'done_text' => $data['done_text'] ?? null,
                ...($isSubmit ? [
                    'review_state' => ReviewState::Submitted,
                    'submitted_via' => SubmittedVia::MeasureSession,
                    'submitted_by_name' => $data['submitted_by_name'],
                    'submitted_session_id' => $request->session()->getId(),
                    'submitted_at' => now(),
                ] : []),
            ]);
        }

        return back()->with('status', $isSubmit ? 'Отправлено на проверку.' : 'Черновик сохранён.');
    }

    public function storeEvidence(Request $request, MeasureStage $stage): RedirectResponse
    {
        $measure = $this->currentMeasure();

        if ($stage->measure_id !== $measure->id) {
            abort(403);
        }

        if (! $measure->stagesConfirmed() || $measure->currentStage()?->id !== $stage->id) {
            abort(403, 'Документы можно прикреплять только к текущему этапу.');
        }

        $period = Period::current();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => [
                'required_without:url', 'nullable', 'file', 'max:25600',
                'mimes:doc,docx,pdf,xls,xlsx,jpg,jpeg,png,gif,bmp,webp,svg,tif,tiff,heic,heif',
            ],
            'url' => ['required_without:file', 'nullable', 'url'],
        ]);

        if ($request->hasFile('file')) {
            $path = $request->file('file')->store('evidence/'.$measure->id, 'public');
            $type = EvidenceType::File;
        } else {
            $path = $data['url'];
            $type = EvidenceType::Link;
        }

        $stage->evidences()->create([
            'measure_id' => $measure->id,
            'period_id' => $period->id,
            'type' => $type,
            'path_or_url' => $path,
            'title' => $data['title'] ?? null,
            'uploaded_via' => SubmittedVia::MeasureSession,
        ]);

        return back()->with('status', 'Документ добавлен.');
    }

    /**
     * Фиксация списка этапов — необратимый (кроме роли `developer`, веб-сторона)
     * переход из режима наполнения в последовательную работу. Осознанное действие
     * исполнителя, не путать с утверждением отчёта по этапу администратором — другое
     * действие, другой экран.
     */
    public function confirmStages(): RedirectResponse
    {
        $measure = $this->currentMeasure();

        if ($measure->stagesConfirmed()) {
            return back();
        }

        if ($measure->stages()->count() < 1) {
            throw ValidationException::withMessages([
                'stages' => 'Нужен хотя бы один этап, чтобы зафиксировать список.',
            ]);
        }

        $measure->update(['stages_confirmed_at' => now()]);

        return back()->with('status', 'Список этапов зафиксирован — теперь доступен только текущий этап.');
    }

    /**
     * Заведение этапов — раньше было исключительно веб-администраторским действием
     * (координатор наполняет план), но по решению заказчика координатор больше не
     * отдельная веб-учётка: структуру этапов и веса теперь заводит тот, у кого логин
     * и пароль мероприятия ([[Роли и права#Доступ к мероприятию (неименной)]]), и
     * только пока список не зафиксирован ({@see confirmStages()}) — после фиксации
     * это может только роль `developer` через {@see MeasureStageController}.
     */
    public function storeStage(Request $request): RedirectResponse
    {
        $measure = $this->currentMeasure();

        if ($measure->stagesConfirmed()) {
            abort(403, 'Список этапов уже зафиксирован.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'planned_date' => ['nullable', 'date'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $order = ((int) $measure->stages()->max('order')) + 1;

        $measure->stages()->create([...$data, 'order' => $order]);

        return back()->with('status', 'Этап добавлен.');
    }

    public function updateStage(Request $request, MeasureStage $stage): RedirectResponse
    {
        $measure = $this->currentMeasure();

        if ($stage->measure_id !== $measure->id) {
            abort(403);
        }

        if ($measure->stagesConfirmed()) {
            abort(403, 'Список этапов уже зафиксирован.');
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'planned_date' => ['nullable', 'date'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $stage->update($data);

        return back()->with('status', 'Этап обновлён.');
    }

    public function destroyStage(MeasureStage $stage): RedirectResponse
    {
        $measure = $this->currentMeasure();

        if ($stage->measure_id !== $measure->id) {
            abort(403);
        }

        if ($measure->stagesConfirmed()) {
            abort(403, 'Список этапов уже зафиксирован.');
        }

        $hasApprovedHistory = $stage->periodUpdates()->where('review_state', ReviewState::Approved)->exists();

        if ($hasApprovedHistory) {
            throw ValidationException::withMessages([
                'stage' => 'У этапа уже есть подтверждённые проректором отчёты — удаление разрушило бы историю выполнения.',
            ]);
        }

        $stage->delete();

        return back()->with('status', 'Этап удалён.');
    }

    private function currentMeasure(): Measure
    {
        /** @var MeasureCredential $credential */
        $credential = Auth::guard('measure')->user();

        return $credential->measure;
    }

    private function ensureDraftRows(Measure $measure, Period $period): void
    {
        if ($measure->stagesConfirmed()) {
            $currentStage = $measure->currentStage();

            if ($currentStage) {
                StagePeriodUpdate::firstOrCreate(
                    ['measure_stage_id' => $currentStage->id, 'period_id' => $period->id],
                    ['review_state' => ReviewState::Draft],
                );
            }
        }

        MeasurePeriodState::firstOrCreate(
            ['measure_id' => $measure->id, 'period_id' => $period->id],
            ['status' => MeasureStatus::NotStarted],
        );
    }

    private function isMeasureStateLocked(Measure $measure, Period $period): bool
    {
        return StagePeriodUpdate::query()
            ->whereIn('measure_stage_id', $measure->stages()->pluck('id'))
            ->where('period_id', $period->id)
            ->where('review_state', ReviewState::Submitted)
            ->exists();
    }
}
