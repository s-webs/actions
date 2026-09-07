<?php

namespace App\Http\Controllers\Measure;

use App\Enums\EvidenceType;
use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Enums\SubmittedVia;
use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Models\MeasureCredential;
use App\Models\MeasurePeriodState;
use App\Models\MeasureStage;
use App\Models\Period;
use App\Models\StagePeriodUpdate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Рабочее место мероприятия (guard `measure`) —
 * [[Функциональные требования#4.7 Рабочее место мероприятия]]. Вся авторизация уже
 * закрыта самим guard'ом (task-003): один комплект учётных данных = один `measure_id`,
 * поэтому здесь нет отдельной Policy — только проверка, что этап/state, который правят,
 * ещё редактируемы (`draft`/`rework`), и что подача идёт с обязательным ФИО.
 */
class WorkspaceController extends Controller
{
    public function show(Request $request): Response
    {
        $measure = $this->currentMeasure();
        $period = Period::current();

        $this->ensureDraftRows($measure, $period);

        $measure->load([
            'direction',
            'stages' => fn ($q) => $q->orderBy('order'),
            'stages.periodUpdates' => fn ($q) => $q->where('period_id', $period->id),
            'stages.evidences' => fn ($q) => $q->where('period_id', $period->id),
        ]);

        $measureState = MeasurePeriodState::where('measure_id', $measure->id)
            ->where('period_id', $period->id)
            ->first();

        $history = StagePeriodUpdate::query()
            ->whereIn('measure_stage_id', $measure->stages->pluck('id'))
            ->whereIn('review_state', [ReviewState::Approved, ReviewState::Rejected, ReviewState::Rework])
            ->with('stage:id,title')
            ->orderByDesc('approved_at')
            ->get();

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
            'stages' => $measure->stages->map(fn (MeasureStage $stage) => [
                'id' => $stage->id,
                'order' => $stage->order,
                'title' => $stage->title,
                'planned_date' => $stage->planned_date?->format('Y-m-d'),
                'weight' => $stage->weight,
                'update' => optional($stage->periodUpdates->first(), fn (StagePeriodUpdate $u) => [
                    'id' => $u->id,
                    'done_text' => $u->done_text,
                    'next_step' => $u->next_step,
                    'next_step_date' => $u->next_step_date?->format('Y-m-d'),
                    'review_state' => $u->review_state->value,
                    'review_comment' => $u->review_comment,
                    'approved_percent' => $u->approved_percent,
                ]),
                'evidences' => $stage->evidences->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'type' => $e->type->value,
                    'path_or_url' => $e->type === EvidenceType::Link ? $e->path_or_url : Storage::disk('public')->url($e->path_or_url),
                ]),
            ]),
            'history' => $history->map(fn (StagePeriodUpdate $u) => [
                'id' => $u->id,
                'stage_title' => $u->stage->title,
                'review_state' => $u->review_state->value,
                'approved_percent' => $u->approved_percent,
                'review_comment' => $u->review_comment,
                'approved_at' => $u->approved_at?->format('Y-m-d H:i'),
            ]),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $measure = $this->currentMeasure();
        $period = Period::current();

        $isSubmit = $request->string('action')->toString() === 'submit';

        $rules = [
            'measure_status' => ['required', 'string', 'in:'.implode(',', array_map(fn ($c) => $c->value, MeasureStatus::cases()))],
            'risk_text' => ['nullable', 'string'],
            'needs_decision' => ['boolean'],
            'stages' => ['required', 'array'],
            'stages.*.id' => ['required', 'integer', 'exists:measure_stages,id'],
            'stages.*.done_text' => ['nullable', 'string'],
            'stages.*.next_step' => ['nullable', 'string'],
            'stages.*.next_step_date' => ['nullable', 'date'],
        ];

        if ($isSubmit) {
            $rules['submitted_by_name'] = ['required', 'string', 'max:255'];
        }

        $data = $request->validate($rules);

        if ($this->isMeasureStateLocked($measure, $period)) {
            throw ValidationException::withMessages([
                'measure_status' => 'Этапы уже поданы на проверку — дождитесь решения проректора.',
            ]);
        }

        MeasurePeriodState::where('measure_id', $measure->id)->where('period_id', $period->id)->update([
            'status' => $data['measure_status'],
            'risk_text' => $data['risk_text'] ?? null,
            'needs_decision' => $data['needs_decision'] ?? false,
        ]);

        foreach ($data['stages'] as $stageInput) {
            $stage = $measure->stages->firstWhere('id', $stageInput['id']);

            if (! $stage) {
                continue;
            }

            $update = StagePeriodUpdate::where('measure_stage_id', $stage->id)->where('period_id', $period->id)->first();

            if (! $update || ! in_array($update->review_state, [ReviewState::Draft, ReviewState::Rework], true)) {
                continue;
            }

            $update->update([
                'done_text' => $stageInput['done_text'] ?? null,
                'next_step' => $stageInput['next_step'] ?? null,
                'next_step_date' => $stageInput['next_step_date'] ?? null,
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

        $period = Period::current();

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required_without:url', 'nullable', 'file', 'max:20480'],
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

    private function currentMeasure(): Measure
    {
        /** @var MeasureCredential $credential */
        $credential = Auth::guard('measure')->user();

        return $credential->measure;
    }

    private function ensureDraftRows(Measure $measure, Period $period): void
    {
        foreach ($measure->stages as $stage) {
            StagePeriodUpdate::firstOrCreate(
                ['measure_stage_id' => $stage->id, 'period_id' => $period->id],
                ['review_state' => ReviewState::Draft],
            );
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
