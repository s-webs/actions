<?php

namespace App\Http\Controllers\Measure;

use App\Enums\EvidenceType;
use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\SubmittedVia;
use App\Http\Controllers\Approval\ApprovalController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Plan\MeasureStageController;
use App\Models\Evidence;
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
use Illuminate\Validation\Rule;
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
                'status' => in_array($measureState->status, [MeasureStatus::NotStarted, MeasureStatus::InProgress], true)
                    ? $measureState->status->value
                    : MeasureStatus::InProgress->value,
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
            'completedStages' => $confirmed
                ? $measure->stages
                    ->filter(fn (MeasureStage $stage) => $this->stageStatus($stage, $currentStage, true) === 'completed')
                    ->map(fn (MeasureStage $stage) => $this->completedStagePayload($stage))
                    ->values()
                : collect(),
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
            'evidences' => $this->evidencePayload($evidences),
        ];
    }

    /**
     * Карточка уже утверждённого этапа: последний Approved-отчёт (в любом периоде)
     * и все прикреплённые к этапу документы.
     *
     * @return array<string, mixed>
     */
    private function completedStagePayload(MeasureStage $stage): array
    {
        $update = $stage->periodUpdates()
            ->where('review_state', ReviewState::Approved)
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();

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
                'submitted_by_name' => $u->submitted_by_name,
                'submitted_at' => $u->submitted_at?->format('Y-m-d H:i'),
            ]),
            'evidences' => $this->evidencePayload($stage->evidences()->get()),
        ];
    }

    /**
     * @param  Collection<int, Evidence>  $evidences
     * @return Collection<int, array<string, mixed>>
     */
    private function evidencePayload($evidences)
    {
        return $evidences->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'type' => $e->type->value,
            'path_or_url' => $e->type === EvidenceType::Link ? $e->path_or_url : Storage::disk('public')->url($e->path_or_url),
        ]);
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
            'measure_status' => ['required', Rule::in([MeasureStatus::NotStarted->value, MeasureStatus::InProgress->value])],
            'risk_text' => ['nullable', 'string'],
            'needs_decision' => ['boolean'],
            'done_text' => ['nullable', 'string'],
            'files' => ['nullable', 'array', 'max:20'],
            'files.*' => [
                'file', 'max:25600',
                'mimes:doc,docx,pdf,xls,xlsx,jpg,jpeg,png,gif,bmp,webp,svg,tif,tiff,heic,heif',
            ],
            'evidence_url' => ['nullable', 'url'],
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

            $this->attachEvidence($request, $measure, $currentStage, $period);
        }

        return back()->with('status', $isSubmit ? 'Отправлено на проверку.' : 'Черновик сохранён.');
    }

    /**
     * Прикрепление документов теперь часть общей формы этапа, а не отдельное
     * действие со своей кнопкой (по опыту реального использования — два отдельных
     * submit'а на одном экране легко перепутать: файлы выбирали, но забывали
     * нажать «Прикрепить» отдельно от «Отправить на проверку»). Файлы и/или ссылка
     * уходят вместе с «Сохранить черновик» / «Отправить на проверку» одним кликом.
     */
    private function attachEvidence(Request $request, Measure $measure, MeasureStage $stage, Period $period): void
    {
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $stage->evidences()->create([
                    'measure_id' => $measure->id,
                    'period_id' => $period->id,
                    'type' => EvidenceType::File,
                    'path_or_url' => $file->store('evidence/'.$measure->id, 'public'),
                    'uploaded_via' => SubmittedVia::MeasureSession,
                ]);
            }
        }

        if ($request->filled('evidence_url')) {
            $stage->evidences()->create([
                'measure_id' => $measure->id,
                'period_id' => $period->id,
                'type' => EvidenceType::Link,
                'path_or_url' => $request->string('evidence_url')->toString(),
                'uploaded_via' => SubmittedVia::MeasureSession,
            ]);
        }
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
            'weight' => ['required', 'integer', 'min:1', 'max:95'],
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
            'weight' => ['required', 'integer', 'min:1', 'max:95'],
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
