<?php

namespace App\Http\Controllers\Plan;

use App\Enums\MeasureStatus;
use App\Enums\ReviewState;
use App\Http\Controllers\Controller;
use App\Models\Direction;
use App\Models\Measure;
use App\Models\MeasurePeriodState;
use App\Models\Period;
use App\Models\Responsible;
use App\Services\MeasureCredentialGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ручное создание одного мероприятия через UI (дополнение к импорту Excel).
 */
class MeasureController extends Controller
{
    public function create(Request $request): Response
    {
        Gate::authorize('create', Measure::class);

        $maxNumber = (int) Measure::query()->max('number');
        $nextNumber = min($maxNumber + 1, 255);

        return Inertia::render('plan/create', [
            'directions' => Direction::orderBy('number')->get(['id', 'number', 'name']),
            'responsibles' => Responsible::query()
                ->with('user')
                ->orderBy('name')
                ->get(['id', 'name', 'user_id'])
                ->map(fn (Responsible $responsible) => [
                    'id' => $responsible->id,
                    'name' => $responsible->name,
                    'occupant' => $responsible->user?->name,
                ]),
            'nextNumber' => $nextNumber > 0 ? $nextNumber : 1,
            'credential' => $request->session()->get('credential'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Measure::class);

        $stages = collect($request->input('stages', []))
            ->map(fn ($stage) => [
                'title' => $stage['title'] ?? null,
                'planned_date' => ! empty($stage['planned_date']) ? $stage['planned_date'] : null,
                'weight' => $stage['weight'] ?? null,
            ])
            ->all();

        $request->merge([
            'responsible_id' => $request->filled('responsible_id') ? $request->integer('responsible_id') : null,
            'deadline' => $request->filled('deadline') ? $request->input('deadline') : null,
            'stages' => $stages,
        ]);

        $validated = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:255', 'unique:measures,number'],
            'title' => ['required', 'string'],
            'direction_id' => ['required', 'exists:directions,id'],
            'responsible_id' => ['nullable', 'exists:responsibles,id'],
            'deadline' => ['nullable', 'date'],
            'stages' => ['nullable', 'array'],
            'stages.*.title' => ['required', 'string', 'max:255'],
            'stages.*.planned_date' => ['nullable', 'date'],
            'stages.*.weight' => ['required', 'integer', 'min:1', 'max:95'],
        ]);

        $credential = DB::transaction(function () use ($validated) {
            $measure = Measure::create([
                'number' => $validated['number'],
                'title' => $validated['title'],
                'direction_id' => $validated['direction_id'],
                'responsible_id' => $validated['responsible_id'] ?? null,
                'deadline' => $validated['deadline'] ?? null,
            ]);

            foreach ($validated['stages'] ?? [] as $index => $stage) {
                $measure->stages()->create([
                    'title' => $stage['title'],
                    'planned_date' => $stage['planned_date'] ?? null,
                    'weight' => $stage['weight'],
                    'order' => $index + 1,
                ]);
            }

            return app(MeasureCredentialGenerator::class)->generate($measure);
        });

        return redirect()
            ->route('plan.create')
            ->with('credential', [
                'measure_number' => $validated['number'],
                'login' => $credential['login'],
                'password' => $credential['password'],
            ]);
    }

    /**
     * Админ принимает работу после утверждения всех этапов: % → 100, статус периода → done.
     */
    public function accept(Request $request, Measure $measure): RedirectResponse
    {
        Gate::authorize('accept', Measure::class);

        $measure->load('stages');

        if ($measure->stages->isEmpty() || $measure->currentStage() !== null) {
            throw ValidationException::withMessages([
                'measure' => 'Принять работу можно только когда все этапы утверждены.',
            ]);
        }

        DB::transaction(function () use ($measure, $request) {
            foreach ($measure->stages as $stage) {
                $lastApproved = $stage->periodUpdates()
                    ->where('review_state', ReviewState::Approved)
                    ->orderByDesc('approved_at')
                    ->first();

                if ($lastApproved && $lastApproved->approved_percent !== 100) {
                    $lastApproved->update([
                        'approved_percent' => 100,
                        'approved_by' => $request->user()->id,
                        'approved_at' => now(),
                    ]);
                }
            }

            $measure->recalculatePercent();

            $period = Period::current();
            MeasurePeriodState::updateOrCreate(
                [
                    'measure_id' => $measure->id,
                    'period_id' => $period->id,
                ],
                [
                    'status' => MeasureStatus::Done,
                ],
            );
        });

        return back()->with('status', 'Работа принята — мероприятие установлено на 100%.');
    }
}
