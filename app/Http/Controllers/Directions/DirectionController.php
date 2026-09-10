<?php

namespace App\Http\Controllers\Directions;

use App\Http\Controllers\Controller;
use App\Models\Direction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DirectionController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('viewAny', Direction::class);

        $directions = Direction::query()
            ->withCount('measures')
            ->orderBy('number')
            ->get()
            ->map(fn (Direction $direction) => [
                'id' => $direction->id,
                'number' => $direction->number,
                'name' => $direction->name,
                'in_summary' => $direction->in_summary,
                'measures_count' => $direction->measures_count,
            ]);

        return Inertia::render('directions/index', [
            'directions' => $directions,
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Direction::class);

        $maxNumber = (int) Direction::query()->max('number');

        return Inertia::render('directions/create', [
            'nextNumber' => min($maxNumber + 1, 255) ?: 1,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Direction::class);

        $validated = $this->validated($request);

        Direction::create($validated);

        return redirect()->route('directions.index')->with('status', 'Направление создано.');
    }

    public function edit(Direction $direction): Response
    {
        Gate::authorize('update', $direction);

        return Inertia::render('directions/edit', [
            'direction' => [
                'id' => $direction->id,
                'number' => $direction->number,
                'name' => $direction->name,
                'in_summary' => $direction->in_summary,
                'measures_count' => $direction->measures()->count(),
            ],
        ]);
    }

    public function update(Request $request, Direction $direction): RedirectResponse
    {
        Gate::authorize('update', $direction);

        $validated = $this->validated($request, $direction);

        $direction->update($validated);

        return redirect()->route('directions.index')->with('status', 'Направление обновлено.');
    }

    public function destroy(Direction $direction): RedirectResponse
    {
        Gate::authorize('delete', $direction);

        if ($direction->measures()->exists()) {
            throw ValidationException::withMessages([
                'direction' => 'Нельзя удалить направление: к нему привязаны мероприятия.',
            ]);
        }

        $direction->delete();

        return redirect()->route('directions.index')->with('status', 'Направление удалено.');
    }

    /**
     * @return array{number: int, name: string, in_summary: bool}
     */
    private function validated(Request $request, ?Direction $direction = null): array
    {
        $request->merge([
            'in_summary' => $request->boolean('in_summary'),
        ]);

        return $request->validate([
            'number' => [
                'required',
                'integer',
                'min:1',
                'max:255',
                Rule::unique('directions', 'number')->ignore($direction?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'in_summary' => ['boolean'],
        ]);
    }
}
