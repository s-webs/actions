<?php

namespace App\Http\Controllers\Plan;

use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Measure\WorkspaceController;
use App\Models\Measure;
use App\Models\MeasureStage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Управление этапами мероприятия — веб-сторона (роль `administrator`), структурный
 * оверрайд ([[Роли и права#Матрица]]: «проректор может выполнить любое действие
 * координатора»). Основной способ завести этапы — логин/пароль самого мероприятия
 * ({@see WorkspaceController::storeStage()}), этот
 * контроллер даёт администратору ту же возможность без входа под учётными данными
 * мероприятия — например, чтобы поправить веса нескольких мероприятий подряд.
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
