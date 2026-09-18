<?php

namespace App\Http\Controllers\Responsibles;

use App\Http\Controllers\Controller;
use App\Models\Responsible;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Именные учётки: человек занимает должность, мероприятия остаются на должности.
 */
class ResponsibleAccountController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('create', Responsible::class);

        $responsibles = Responsible::query()
            ->with(['user'])
            ->withCount('measures')
            ->orderBy('name')
            ->get()
            ->map(fn (Responsible $responsible) => [
                'id' => $responsible->id,
                'name' => $responsible->name,
                'occupant' => $responsible->user?->name,
                'email' => $responsible->user?->email,
                'measures_count' => $responsible->measures_count,
                'has_account' => $responsible->user_id !== null,
            ]);

        return Inertia::render('responsibles/accounts/index', [
            'responsibles' => $responsibles,
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Responsible::class);

        $prefillId = $request->integer('responsible_id') ?: null;

        return Inertia::render('responsibles/accounts/create', [
            'positions' => $this->positionOptions($prefillId),
            'prefillResponsibleId' => $prefillId,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', User::class);
        Gate::authorize('create', Responsible::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'responsible_id' => ['required', 'exists:responsibles,id'],
        ]);

        $position = Responsible::query()->findOrFail($validated['responsible_id']);
        $this->ensureVacant($position);

        DB::transaction(function () use ($validated, $position) {
            $user = User::create([
                'name' => trim($validated['name']),
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);
            $user->assignRole('responsible');
            $position->update(['user_id' => $user->id]);
        });

        return redirect()->route('responsibles.accounts.index')->with('status', 'Учётная запись ответственного создана.');
    }

    public function edit(Responsible $responsible): Response
    {
        Gate::authorize('update', $responsible);

        if ($responsible->user_id === null) {
            return redirect()->route('responsibles.accounts.create', ['responsible_id' => $responsible->id]);
        }

        $responsible->load('user');

        return Inertia::render('responsibles/accounts/edit', [
            'account' => [
                'id' => $responsible->id,
                'name' => $responsible->user?->name ?? '',
                'email' => $responsible->user?->email ?? '',
                'responsible_id' => $responsible->id,
            ],
            'positions' => $this->positionOptions($responsible->id),
        ]);
    }

    public function update(Request $request, Responsible $responsible): RedirectResponse
    {
        Gate::authorize('update', $responsible);

        $user = $responsible->user;
        if ($user === null) {
            throw ValidationException::withMessages([
                'responsible_id' => 'На этой должности ещё нет учётной записи.',
            ]);
        }

        Gate::authorize('update', $user);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:8'],
            'responsible_id' => ['required', 'exists:responsibles,id'],
        ]);

        $target = Responsible::query()->findOrFail($validated['responsible_id']);
        if ($target->id !== $responsible->id) {
            $this->ensureVacant($target);
        }

        DB::transaction(function () use ($validated, $responsible, $user, $target) {
            $payload = [
                'name' => trim($validated['name']),
                'email' => $validated['email'],
            ];
            if (! empty($validated['password'])) {
                $payload['password'] = $validated['password'];
            }
            $user->update($payload);

            if ($target->id !== $responsible->id) {
                $responsible->update(['user_id' => null]);
                $target->update(['user_id' => $user->id]);
            }
        });

        return redirect()->route('responsibles.accounts.index')->with('status', 'Учётная запись обновлена.');
    }

    private function ensureVacant(Responsible $position): void
    {
        if ($position->user_id !== null) {
            throw ValidationException::withMessages([
                'responsible_id' => 'Эта должность уже занята.',
            ]);
        }
    }

    /**
     * @return list<array{id: int, name: string, occupied: bool}>
     */
    private function positionOptions(?int $includeId = null): array
    {
        return Responsible::query()
            ->orderBy('name')
            ->get(['id', 'name', 'user_id'])
            ->filter(fn (Responsible $position) => $position->user_id === null || $position->id === $includeId)
            ->map(fn (Responsible $position) => [
                'id' => $position->id,
                'name' => $position->name,
                'occupied' => $position->user_id !== null,
            ])
            ->values()
            ->all();
    }
}
