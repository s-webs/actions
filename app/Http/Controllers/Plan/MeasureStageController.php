<?php

namespace App\Http\Controllers\Plan;

use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Models\MeasureStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Управление этапами мероприятия — координатор/проректор заводят этапы вручную
 * при наполнении плана ([[Функциональные требования#4.1.2]]); импорт (task-005)
 * намеренно не создаёт этапы сам, так как их структура не следует из xlsx напрямую.
 * Без этапов у мероприятия рабочее место (`measure/workspace`) не показывает ни
 * одной формы отчёта, поэтому это действие — обязательный шаг перед тем, как
 * выдавать координатору мероприятия учётные данные.
 */
class MeasureStageController extends Controller
{
    public function store(Request $request, Measure $measure): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'planned_date' => ['nullable', 'date'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $order = ((int) $measure->stages()->max('order')) + 1;

        $measure->stages()->create([...$data, 'order' => $order]);

        return back()->with('status', 'Этап добавлен.');
    }

    public function update(Request $request, MeasureStage $stage): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'planned_date' => ['nullable', 'date'],
            'weight' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $stage->update($data);

        return back()->with('status', 'Этап обновлён.');
    }

    public function destroy(MeasureStage $stage): RedirectResponse
    {
        Gate::authorize('manage', Measure::class);

        $hasApprovedHistory = $stage->periodUpdates()->where('review_state', ReviewState::Approved)->exists();

        if ($hasApprovedHistory) {
            throw ValidationException::withMessages([
                'stage' => 'У этапа уже есть подтверждённые проректором отчёты — удаление разрушило бы историю выполнения.',
            ]);
        }

        $stage->delete();

        return back()->with('status', 'Этап удалён.');
    }
}
