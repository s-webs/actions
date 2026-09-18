<?php

namespace App\Http\Controllers\Responsibles;

use App\Http\Controllers\Controller;
use App\Models\Measure;
use App\Models\Responsible;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Справочник должностей: к должности крепятся мероприятия.
 */
class ResponsiblePositionController extends Controller
{
    public function create(): Response
    {
        Gate::authorize('create', Responsible::class);

        return Inertia::render('responsibles/positions/create', [
            'measures' => $this->measureOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Responsible::class);

        $request->merge([
            'measure_ids' => $request->input('measure_ids', []),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'measure_ids' => ['nullable', 'array'],
            'measure_ids.*' => ['integer', 'exists:measures,id'],
        ]);

        DB::transaction(function () use ($validated) {
            $position = Responsible::create([
                'name' => trim($validated['name']),
            ]);
            $position->syncMeasures($validated['measure_ids'] ?? []);
        });

        return redirect()->route('responsibles.accounts.index')->with('status', 'Должность создана.');
    }

    public function edit(Responsible $responsible): Response
    {
        Gate::authorize('update', $responsible);

        return Inertia::render('responsibles/positions/edit', [
            'position' => [
                'id' => $responsible->id,
                'name' => $responsible->name,
                'measure_ids' => $responsible->measures()->pluck('id')->all(),
            ],
            'measures' => $this->measureOptions(),
        ]);
    }

    public function update(Request $request, Responsible $responsible): RedirectResponse
    {
        Gate::authorize('update', $responsible);

        $request->merge([
            'measure_ids' => $request->input('measure_ids', []),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'measure_ids' => ['nullable', 'array'],
            'measure_ids.*' => ['integer', 'exists:measures,id'],
        ]);

        DB::transaction(function () use ($validated, $responsible) {
            $responsible->update([
                'name' => trim($validated['name']),
            ]);
            $responsible->syncMeasures($validated['measure_ids'] ?? []);
        });

        return redirect()->route('responsibles.accounts.index')->with('status', 'Должность обновлена.');
    }

    /**
     * @return list<array{id: int, number: int, title: string, responsible_id: int|null}>
     */
    private function measureOptions(): array
    {
        return Measure::query()
            ->orderBy('number')
            ->get(['id', 'number', 'title', 'responsible_id'])
            ->map(fn (Measure $measure) => [
                'id' => $measure->id,
                'number' => $measure->number,
                'title' => $measure->title,
                'responsible_id' => $measure->responsible_id,
            ])
            ->all();
    }
}
